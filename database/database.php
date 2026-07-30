<?php

declare(strict_types=1);

const LOGIC_DATABASE_SETUP_LOCK = 'logic_app_database_setup';
const LOGIC_DATABASE_SCHEMA_VERSION = '20260731_remove_logic_type_columns';

function loadLogicDatabaseEnvironment(): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $loaded = true;
    $candidatePaths = [
        __DIR__ . '/mariadb.env',
        dirname(__DIR__) . '/.env',
    ];

    foreach ($candidatePaths as $path) {
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            continue;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = array_map('trim', explode('=', $line, 2));

            if ($name === '' || getenv($name) !== false) {
                continue;
            }

            $value = trim($value, "\"'");
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

function getLogicDatabase(): PDO
{
    static $database = null;

    if ($database instanceof PDO) {
        return $database;
    }

    loadLogicDatabaseEnvironment();

    $host = getenv('LOGIC_DB_HOST') ?: '127.0.0.1';
    $port = getenv('LOGIC_DB_PORT') ?: '3306';
    $name = getenv('LOGIC_DB_NAME') ?: 'logic_app';
    $charset = getenv('LOGIC_DB_CHARSET') ?: 'utf8mb4';
    $user = getenv('LOGIC_DB_USER') ?: 'logic_app';
    $password = getenv('LOGIC_DB_PASSWORD') ?: '';
    $socket = getenv('LOGIC_DB_SOCKET');
    $dsn = is_string($socket) && trim($socket) !== ''
        ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $name, $charset)
        : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);

    $database = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $database;
}

function getLogicDataStore(): PDO
{
    static $database = null;

    if ($database instanceof PDO) {
        return $database;
    }

    $database = getLogicDatabase();

    return $database;
}

function initializeLogicDatabase(PDO $database): void
{
    withLogicDatabaseSetupLock($database, static function () use ($database): void {
        $schema = file_get_contents(__DIR__ . '/schema.sql');

        if ($schema === false) {
            throw new RuntimeException('MariaDB 스키마를 읽을 수 없습니다.');
        }

        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            $database->exec($statement);
        }

        applyLogicDatabaseMigrations($database);
        ensureDefaultAdminUser($database);
        seedLogicDatabaseIfEmpty($database);
    });
}

function withLogicDatabaseSetupLock(PDO $database, callable $callback): void
{
    $lockStatement = $database->prepare('SELECT GET_LOCK(:lock_name, 30)');
    $lockStatement->execute([':lock_name' => LOGIC_DATABASE_SETUP_LOCK]);

    if ((int) $lockStatement->fetchColumn() !== 1) {
        throw new RuntimeException('MariaDB 초기화 잠금을 얻지 못했습니다.');
    }

    try {
        $callback();
    } finally {
        try {
            $releaseStatement = $database->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $releaseStatement->execute([':lock_name' => LOGIC_DATABASE_SETUP_LOCK]);
        } catch (Throwable $error) {
            error_log($error->__toString());
        }
    }
}

function applyLogicDatabaseMigrations(PDO $database): void
{
    $statement = $database->prepare(
        'SELECT COUNT(*) FROM logic_schema_migrations WHERE version = :version'
    );
    $statement->execute([':version' => LOGIC_DATABASE_SCHEMA_VERSION]);

    if ((int) $statement->fetchColumn() > 0) {
        return;
    }

    removeLogicTypeColumns($database);

    $insertMigration = $database->prepare(
        'INSERT INTO logic_schema_migrations (version) VALUES (:version)'
    );
    $insertMigration->execute([':version' => LOGIC_DATABASE_SCHEMA_VERSION]);
}

function tableColumnExists(PDO $database, string $table, string $column): bool
{
    $statement = $database->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND COLUMN_NAME = :column_name'
    );
    $statement->execute([
        ':table_name' => $table,
        ':column_name' => $column,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function tableIndexExists(PDO $database, string $table, string $index): bool
{
    $statement = $database->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND INDEX_NAME = :index_name'
    );
    $statement->execute([
        ':table_name' => $table,
        ':index_name' => $index,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function removeLogicTypeColumns(PDO $database): void
{
    if (tableColumnExists($database, 'problems', 'logic_type')) {
        if (tableIndexExists($database, 'problems', 'idx_problems_logic_type')) {
            $database->exec('ALTER TABLE problems DROP INDEX idx_problems_logic_type');
        }

        if (tableIndexExists($database, 'problems', 'idx_problems_problem_lookup')) {
            $database->exec('ALTER TABLE problems DROP INDEX idx_problems_problem_lookup');
        }

        $database->exec('ALTER TABLE problems DROP COLUMN logic_type');
    }

    if (!tableIndexExists($database, 'problems', 'idx_problems_problem_lookup')) {
        $database->exec('ALTER TABLE problems ADD INDEX idx_problems_problem_lookup (problem_text(255))');
    }

    if (tableColumnExists($database, 'exercise_categories', 'logic_type')) {
        $database->exec('ALTER TABLE exercise_categories DROP COLUMN logic_type');
    }
}

function ensureDefaultAdminUser(PDO $database): void
{
    $adminCount = (int) $database
        ->query("SELECT COUNT(*) FROM users WHERE username = 'admin'")
        ->fetchColumn();

    if ($adminCount > 0) {
        return;
    }

    $insertUser = $database->prepare(
        'INSERT INTO users (username, password_hash, role)
         VALUES (:username, :password_hash, :role)'
    );
    $insertUser->execute([
        ':username' => 'admin',
        ':password_hash' => password_hash('admin', PASSWORD_DEFAULT),
        ':role' => 'admin',
    ]);
}

function seedLogicDatabaseIfEmpty(PDO $database): void
{
    $problemCount = (int) $database->query('SELECT COUNT(*) FROM problems')->fetchColumn();
    $exampleCount = (int) $database->query('SELECT COUNT(*) FROM problem_examples')->fetchColumn();
    $exerciseCount = (int) $database->query('SELECT COUNT(*) FROM exercise_entries')->fetchColumn();

    if ($problemCount > 0 || $exampleCount > 0 || $exerciseCount > 0) {
        return;
    }

    seedRuleExamples($database, loadRuleExampleSeeds());
    seedGuideExamples($database, loadGuideExampleSeeds());
    seedExercises($database, loadExerciseSeeds());
}

function reseedLogicDatabase(PDO $database): void
{
    try {
        $database->exec('SET FOREIGN_KEY_CHECKS = 0');
        $database->exec('DELETE FROM exercise_entries');
        $database->exec('DELETE FROM exercise_sections');
        $database->exec('DELETE FROM exercise_categories');
        $database->exec('DELETE FROM problem_examples');
        $database->exec('DELETE FROM problems');
        $database->exec('ALTER TABLE exercise_entries AUTO_INCREMENT = 1');
        $database->exec('ALTER TABLE exercise_sections AUTO_INCREMENT = 1');
        $database->exec('ALTER TABLE exercise_categories AUTO_INCREMENT = 1');
        $database->exec('ALTER TABLE problem_examples AUTO_INCREMENT = 1');
        $database->exec('ALTER TABLE problems AUTO_INCREMENT = 1');
        $database->exec('SET FOREIGN_KEY_CHECKS = 1');

        seedRuleExamples($database, loadRuleExampleSeeds());
        seedGuideExamples($database, loadGuideExampleSeeds());
        seedExercises($database, loadExerciseSeeds());
    } catch (Throwable $error) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }

        $database->exec('SET FOREIGN_KEY_CHECKS = 1');
        throw $error;
    }
}

function writeSeedJson(string $fileName, array $data): void
{
    $path = __DIR__ . '/' . $fileName;
    $json = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    );

    if (file_put_contents($path, $json . PHP_EOL) === false) {
        throw new RuntimeException($fileName . ' 시드 파일을 쓸 수 없습니다.');
    }
}

function exportLogicDatabaseSeeds(PDO $database): array
{
    $examples = $database->query(
        'SELECT
            pe.title,
            pe.category_title,
            pe.section_title,
            pe.example_kind,
            pe.guide_key,
            pe.rule_key,
            pe.variant_index,
            p.problem_text,
            pe.answer_text
         FROM problem_examples pe
         INNER JOIN problems p ON p.id = pe.problem_id
         ORDER BY pe.example_kind ASC, pe.rule_key ASC, pe.variant_index ASC, pe.id ASC'
    )->fetchAll();
    $ruleExamples = [];
    $guideExamples = [];

    foreach ($examples as $example) {
        if ($example['example_kind'] === 'guide') {
            $guideExamples[] = [
                'guide_key' => $example['guide_key'],
                'title' => $example['title'],
                'category' => $example['category_title'],
                'section' => $example['section_title'],
                'problem' => $example['problem_text'],
                'answer' => $example['answer_text'],
            ];
            continue;
        }

        $ruleExamples[] = [
            'title' => $example['title'],
            'category' => $example['category_title'],
            'section' => $example['section_title'],
            'rule_key' => $example['rule_key'],
            'variant_index' => (int) $example['variant_index'],
            'problem' => $example['problem_text'],
            'answer' => $example['answer_text'],
        ];
    }

    $exerciseSeeds = array_map(static fn (array $category): array => [
        'title' => $category['title'],
        'sort_order' => (int) $category['sort_order'],
        'sections' => array_map(static fn (array $section): array => [
            'title' => $section['title'],
            'sort_order' => (int) $section['sort_order'],
            'items' => array_map(static fn (array $item): array => [
                'title' => $item['title'],
                'problem' => $item['problem'],
                'sort_order' => (int) $item['sort_order'],
            ], $section['items']),
        ], $category['sections']),
    ], getExerciseCatalog($database));

    writeSeedJson('rule_examples.json', $ruleExamples);
    writeSeedJson('guide_examples.json', $guideExamples);
    writeSeedJson('exercises.json', $exerciseSeeds);

    return [
        'ruleExamples' => count($ruleExamples),
        'guideExamples' => count($guideExamples),
        'exerciseCategories' => count($exerciseSeeds),
        'exerciseEntries' => array_sum(array_map(
            static fn (array $category): int => array_sum(array_map(
                static fn (array $section): int => count($section['items']),
                $category['sections'],
            )),
            $exerciseSeeds,
        )),
    ];
}

function loadRuleExampleSeeds(): array
{
    return loadSeedJson('rule_examples.json', '추론 규칙 예제 초기 데이터');
}

function loadGuideExampleSeeds(): array
{
    return loadSeedJson('guide_examples.json', '입력 가이드 예제 초기 데이터');
}

function loadExerciseSeeds(): array
{
    return loadSeedJson('exercises.json', '연습문제 초기 데이터');
}

function loadSeedJson(string $fileName, string $label): array
{
    $seedJson = file_get_contents(__DIR__ . '/' . $fileName);

    if ($seedJson === false) {
        throw new RuntimeException($label . '를 읽을 수 없습니다.');
    }

    $data = json_decode($seedJson, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($data)) {
        throw new RuntimeException($label . ' 형식이 올바르지 않습니다.');
    }

    return $data;
}

function findOrCreateProblem(PDO $database, string $title, string $problemText): int
{
    $findProblem = $database->prepare(
        'SELECT id FROM problems
         WHERE problem_text = :problem_text
         ORDER BY id ASC
         LIMIT 1'
    );
    $findProblem->execute([
        ':problem_text' => $problemText,
    ]);
    $problemId = $findProblem->fetchColumn();

    if ($problemId !== false) {
        return (int) $problemId;
    }

    $insertProblem = $database->prepare(
        'INSERT INTO problems (title, problem_text)
         VALUES (:title, :problem_text)'
    );
    $insertProblem->execute([
        ':title' => $title,
        ':problem_text' => $problemText,
    ]);

    return (int) $database->lastInsertId();
}

function seedRuleExamples(PDO $database, array $examples, bool $manageTransaction = true): void
{
    $insertExample = $database->prepare(
        'INSERT INTO problem_examples
            (problem_id, title, category_title, section_title, example_kind, guide_key, rule_key, variant_index, answer_text)
         VALUES
            (:problem_id, :title, :category_title, :section_title, :example_kind, :guide_key, :rule_key, :variant_index, :answer_text)
         ON DUPLICATE KEY UPDATE
            problem_id = VALUES(problem_id),
            title = VALUES(title),
            category_title = VALUES(category_title),
            section_title = VALUES(section_title),
            example_kind = VALUES(example_kind),
            guide_key = VALUES(guide_key),
            answer_text = VALUES(answer_text)'
    );

    runInOptionalTransaction($database, $manageTransaction, function () use ($database, $examples, $insertExample): void {
        foreach ($examples as $example) {
            $problemId = findOrCreateProblem(
                $database,
                $example['rule_key'] . ' 예제 ' . ((int) $example['variant_index'] + 1),
                $example['problem']
            );
            $insertExample->execute([
                ':problem_id' => $problemId,
                ':title' => $example['title'],
                ':category_title' => $example['category'],
                ':section_title' => $example['section'],
                ':example_kind' => 'rule',
                ':guide_key' => null,
                ':rule_key' => $example['rule_key'],
                ':variant_index' => (int) $example['variant_index'],
                ':answer_text' => $example['answer'],
            ]);
        }
    });
}

function seedGuideExamples(PDO $database, array $examples, bool $manageTransaction = true): void
{
    $upsertExample = $database->prepare(
        'INSERT INTO problem_examples
            (problem_id, title, category_title, section_title, example_kind, guide_key, rule_key, variant_index, answer_text)
         VALUES
            (:problem_id, :title, :category_title, :section_title, :example_kind, :guide_key, :rule_key, :variant_index, :answer_text)
         ON DUPLICATE KEY UPDATE
            problem_id = VALUES(problem_id),
            title = VALUES(title),
            category_title = VALUES(category_title),
            section_title = VALUES(section_title),
            example_kind = VALUES(example_kind),
            rule_key = VALUES(rule_key),
            variant_index = VALUES(variant_index),
            answer_text = VALUES(answer_text)'
    );

    runInOptionalTransaction($database, $manageTransaction, function () use ($database, $examples, $upsertExample): void {
        foreach ($examples as $example) {
            $problemId = findOrCreateProblem($database, $example['title'], $example['problem']);
            $upsertExample->execute([
                ':problem_id' => $problemId,
                ':title' => $example['title'],
                ':category_title' => $example['category'],
                ':section_title' => $example['section'],
                ':example_kind' => 'guide',
                ':guide_key' => $example['guide_key'],
                ':rule_key' => 'guide:' . $example['guide_key'],
                ':variant_index' => 0,
                ':answer_text' => $example['answer'],
            ]);
        }
    });
}

function seedExercises(PDO $database, array $categories, bool $manageTransaction = true): void
{
    $upsertCategory = $database->prepare(
        'INSERT INTO exercise_categories (title, sort_order)
         VALUES (:title, :sort_order)
         ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)'
    );
    $findCategory = $database->prepare('SELECT id FROM exercise_categories WHERE title = :title');
    $upsertSection = $database->prepare(
        'INSERT INTO exercise_sections (category_id, title, sort_order)
         VALUES (:category_id, :title, :sort_order)
         ON DUPLICATE KEY UPDATE title = VALUES(title), sort_order = VALUES(sort_order)'
    );
    $findSection = $database->prepare(
        'SELECT id FROM exercise_sections WHERE category_id = :category_id AND sort_order = :sort_order'
    );
    $upsertEntry = $database->prepare(
        'INSERT INTO exercise_entries (problem_id, section_id, title, sort_order)
         VALUES (:problem_id, :section_id, :title, :sort_order)
         ON DUPLICATE KEY UPDATE problem_id = VALUES(problem_id), title = VALUES(title), sort_order = VALUES(sort_order)'
    );

    runInOptionalTransaction($database, $manageTransaction, function () use (
        $database,
        $categories,
        $upsertCategory,
        $findCategory,
        $upsertSection,
        $findSection,
        $upsertEntry
    ): void {
        foreach ($categories as $category) {
            $upsertCategory->execute([
                ':title' => $category['title'],
                ':sort_order' => (int) $category['sort_order'],
            ]);
            $findCategory->execute([':title' => $category['title']]);
            $categoryId = (int) $findCategory->fetchColumn();

            foreach ($category['sections'] as $section) {
                $upsertSection->execute([
                    ':category_id' => $categoryId,
                    ':title' => $section['title'],
                    ':sort_order' => (int) $section['sort_order'],
                ]);
                $findSection->execute([
                    ':category_id' => $categoryId,
                    ':sort_order' => (int) $section['sort_order'],
                ]);
                $sectionId = (int) $findSection->fetchColumn();

                foreach ($section['items'] as $item) {
                    $problemId = findOrCreateProblem($database, $item['title'], $item['problem']);
                    $upsertEntry->execute([
                        ':problem_id' => $problemId,
                        ':section_id' => $sectionId,
                        ':title' => $item['title'],
                        ':sort_order' => (int) $item['sort_order'],
                    ]);
                }
            }
        }
    });
}

function runInOptionalTransaction(PDO $database, bool $manageTransaction, callable $callback): void
{
    if ($manageTransaction) {
        $database->beginTransaction();
    }

    try {
        $callback();

        if ($manageTransaction) {
            $database->commit();
        }
    } catch (Throwable $error) {
        if ($manageTransaction && $database->inTransaction()) {
            $database->rollBack();
        }

        throw $error;
    }
}

function getExerciseCatalog($database): array
{
    if ($database instanceof LogicSeedStore) {
        return $database->getExerciseCatalog();
    }

    $rows = $database->query(
        'SELECT
            category.id AS category_id,
            category.title AS category_title,
            category.sort_order AS category_sort_order,
            section.id AS section_id,
            section.title AS section_title,
            section.sort_order AS section_sort_order,
            entry.id AS entry_id,
            entry.title AS entry_title,
            entry.sort_order AS entry_sort_order,
            problem.problem_text
         FROM exercise_categories AS category
         INNER JOIN exercise_sections AS section ON section.category_id = category.id
         INNER JOIN exercise_entries AS entry ON entry.section_id = section.id
         INNER JOIN problems AS problem ON problem.id = entry.problem_id
         ORDER BY category.sort_order, category.id, section.sort_order, section.id, entry.sort_order, entry.id'
    )->fetchAll();
    $categories = [];
    $categoryIndexes = [];
    $sectionIndexes = [];

    foreach ($rows as $row) {
        $categoryId = (int) $row['category_id'];
        $sectionId = (int) $row['section_id'];

        if (!array_key_exists($categoryId, $categoryIndexes)) {
            $categoryIndexes[$categoryId] = count($categories);
            $categories[] = [
                'id' => $categoryId,
                'title' => $row['category_title'],
                'sort_order' => (int) $row['category_sort_order'],
                'sections' => [],
            ];
        }

        $categoryIndex = $categoryIndexes[$categoryId];

        if (!array_key_exists($sectionId, $sectionIndexes)) {
            $sectionIndexes[$sectionId] = [
                'category_index' => $categoryIndex,
                'section_index' => count($categories[$categoryIndex]['sections']),
            ];
            $categories[$categoryIndex]['sections'][] = [
                'id' => $sectionId,
                'title' => $row['section_title'],
                'sort_order' => (int) $row['section_sort_order'],
                'items' => [],
            ];
        }

        $sectionIndex = $sectionIndexes[$sectionId]['section_index'];
        $categories[$categoryIndex]['sections'][$sectionIndex]['items'][] = [
            'id' => (int) $row['entry_id'],
            'title' => $row['entry_title'],
            'problem' => $row['problem_text'],
            'sort_order' => (int) $row['entry_sort_order'],
        ];
    }

    return $categories;
}

class LogicSeedStore
{
    private array $ruleExamples;
    private array $guideExamples;
    private array $exerciseCategories;

    public function __construct()
    {
        $this->reload();
    }

    public function reload(): void
    {
        $this->ruleExamples = loadRuleExampleSeeds();
        $this->guideExamples = loadGuideExampleSeeds();
        $this->exerciseCategories = loadExerciseSeeds();
    }

    public function reseed(): void
    {
        $this->reload();
    }

    public function exportSeeds(): array
    {
        $this->writeAllSeeds();

        return $this->counts();
    }

    public function getExerciseCatalog(): array
    {
        return $this->withExerciseIds($this->exerciseCategories);
    }

    public function getExercise(int $id): ?array
    {
        foreach ($this->getExerciseCatalog() as $category) {
            foreach ($category['sections'] as $section) {
                foreach ($section['items'] as $item) {
                    if ((int) $item['id'] === $id) {
                        return [
                            'id' => $id,
                            'title' => $item['title'],
                            'problem' => $item['problem'],
                            'section' => $section['title'],
                            'category' => $category['title'],
                        ];
                    }
                }
            }
        }

        return null;
    }

    public function getRuleExamples(): array
    {
        return array_values(array_map(
            fn (array $example, int $index): array => $this->formatExample($example, 1000 + $index, 'rule'),
            $this->ruleExamples,
            array_keys($this->ruleExamples),
        ));
    }

    public function getGuideExamples(): array
    {
        return array_values(array_map(
            fn (array $example, int $index): array => $this->formatExample($example, 2000 + $index, 'guide'),
            $this->guideExamples,
            array_keys($this->guideExamples),
        ));
    }

    public function getAdminExamples(): array
    {
        return array_merge($this->getGuideExamples(), $this->getRuleExamples());
    }

    public function verifyAdminLogin(string $username, string $password): bool
    {
        return $username === 'admin' && password_verify($password, $this->adminPasswordHash());
    }

    public function updateAdminPassword(string $password): void
    {
        $payload = json_encode([
            'username' => 'admin',
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($payload === false || file_put_contents($this->adminAuthPath(), $payload . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('관리자 암호 파일을 저장할 수 없습니다.');
        }
    }

    public function findExample(int $id, ?string $kind = null): ?array
    {
        foreach ($this->getAdminExamples() as $example) {
            if ((int) $example['id'] === $id && ($kind === null || $example['kind'] === $kind)) {
                return $example;
            }
        }

        return null;
    }

    public function getClassifications(): array
    {
        return array_map(static fn (array $category): array => [
            'id' => (int) ($category['id'] ?? $category['sort_order']),
            'title' => $category['title'],
            'sort_order' => (int) $category['sort_order'],
            'sections' => array_map(static fn (array $section): array => [
                'id' => (int) ($section['id'] ?? $section['sort_order']),
                'title' => $section['title'],
                'sort_order' => (int) $section['sort_order'],
            ], $category['sections'] ?? []),
        ], $this->getExerciseCatalog());
    }

    public function upsertExample(array $data, ?int $id = null): int
    {
        $kind = (string) ($data['kind'] ?? 'rule');
        $target = $kind === 'guide' ? 'guideExamples' : 'ruleExamples';
        $examples = $this->{$target};
        $index = $id === null ? null : $this->exampleIndexById($examples, $kind, $id);
        $newExample = [
            'title' => trim((string) $data['title']),
            'category' => trim((string) ($data['category'] ?? '')),
            'section' => trim((string) ($data['section'] ?? '')),
            'problem' => trim((string) $data['problem']),
            'answer' => trim((string) $data['answer']),
        ];

        if ($kind === 'guide') {
            $newExample = ['guide_key' => trim((string) $data['guideKey'])] + $newExample;
        } else {
            $newExample += [
                'rule_key' => trim((string) $data['ruleKey']),
                'variant_index' => (int) ($data['variantIndex'] ?? 0),
            ];
        }

        if ($index === null) {
            $examples[] = $newExample;
            $index = array_key_last($examples);
        } else {
            $examples[$index] = $newExample;
        }

        $this->{$target} = array_values($examples);
        $this->writeAllSeeds();

        return ($kind === 'guide' ? 2000 : 1000) + (int) $index;
    }

    public function upsertExercise(array $data, ?int $id = null): int
    {
        $categoryTitle = trim((string) $data['category']);
        $sectionTitle = trim((string) $data['section']);
        $categoryIndex = $this->findOrCreateCategoryIndex($categoryTitle);
        $sectionIndex = $this->findOrCreateSectionIndex($categoryIndex, $sectionTitle);
        $item = [
            'title' => trim((string) $data['title']),
            'problem' => trim((string) $data['problem']),
            'sort_order' => $id === null
                ? $this->nextItemSortOrder($categoryIndex, $sectionIndex)
                : 100,
        ];

        if ($id !== null) {
            $removed = $this->removeExerciseById($id);
            $item['sort_order'] = (int) ($removed['sort_order'] ?? $item['sort_order']);
        }

        $this->exerciseCategories[$categoryIndex]['sections'][$sectionIndex]['items'][] = $item;
        $this->sortExercises();
        $this->writeAllSeeds();

        $catalog = $this->getExerciseCatalog();
        $section = $catalog[$categoryIndex]['sections'][$sectionIndex];
        return (int) end($section['items'])['id'];
    }

    public function upsertClassification(array $data, ?int $id = null): int
    {
        $type = (string) ($data['type'] ?? 'category');
        $title = trim((string) $data['title']);

        if ($type === 'category') {
            $index = $id === null ? $this->findCategoryIndexByTitle($title) : $this->findCategoryIndexById($id);
            if ($index === null) {
                $this->exerciseCategories[] = [
                    'title' => $title,
                    'sort_order' => $this->nextCategorySortOrder(),
                    'sections' => [],
                ];
                $index = array_key_last($this->exerciseCategories);
            } else {
                $this->exerciseCategories[$index]['title'] = $title;
            }
            $this->writeAllSeeds();
            return (int) $this->getExerciseCatalog()[$index]['id'];
        }

        $categoryIndex = $this->findCategoryIndexById((int) $data['categoryId']);
        if ($categoryIndex === null) {
            throw new RuntimeException('section에는 categoryId가 필요합니다.');
        }

        $sectionIndex = $id === null ? $this->findSectionIndexByTitle($categoryIndex, $title) : $this->findSectionIndexById($categoryIndex, $id);
        if ($sectionIndex === null) {
            $this->exerciseCategories[$categoryIndex]['sections'][] = [
                'title' => $title,
                'sort_order' => $this->nextSectionSortOrder($categoryIndex),
                'items' => [],
            ];
            $sectionIndex = array_key_last($this->exerciseCategories[$categoryIndex]['sections']);
        } else {
            $this->exerciseCategories[$categoryIndex]['sections'][$sectionIndex]['title'] = $title;
        }

        $this->writeAllSeeds();
        return (int) $this->getExerciseCatalog()[$categoryIndex]['sections'][$sectionIndex]['id'];
    }

    public function reorderExercises(int $sectionId, array $ids): void
    {
        foreach ($this->exerciseCategories as $categoryIndex => $category) {
            foreach (($category['sections'] ?? []) as $sectionIndex => $section) {
                if ($this->sectionId((int) $categoryIndex, (int) $sectionIndex) !== $sectionId) {
                    continue;
                }

                $itemsById = [];
                foreach (($section['items'] ?? []) as $itemIndex => $item) {
                    $itemsById[$this->itemId((int) $categoryIndex, (int) $sectionIndex, (int) $itemIndex)] = $item;
                }

                $ordered = [];
                foreach (array_values(array_map('intval', $ids)) as $index => $itemId) {
                    if (isset($itemsById[$itemId])) {
                        $item = $itemsById[$itemId];
                        $item['sort_order'] = ($index + 1) * 100;
                        $ordered[] = $item;
                    }
                }

                $this->exerciseCategories[$categoryIndex]['sections'][$sectionIndex]['items'] = $ordered;
                $this->writeAllSeeds();
                return;
            }
        }
    }

    public function reorderSections(int $categoryId, array $ids): void
    {
        $categoryIndex = $this->findCategoryIndexById($categoryId);
        if ($categoryIndex === null) {
            return;
        }

        $sectionsById = [];
        foreach (($this->exerciseCategories[$categoryIndex]['sections'] ?? []) as $sectionIndex => $section) {
            $sectionsById[$this->sectionId($categoryIndex, (int) $sectionIndex)] = $section;
        }

        $ordered = [];
        foreach (array_values(array_map('intval', $ids)) as $index => $sectionId) {
            if (isset($sectionsById[$sectionId])) {
                $section = $sectionsById[$sectionId];
                $section['sort_order'] = ($index + 1) * 100;
                $ordered[] = $section;
            }
        }

        $this->exerciseCategories[$categoryIndex]['sections'] = $ordered;
        $this->writeAllSeeds();
    }

    public function delete(string $resource, int $id, string $classificationType = 'category'): bool
    {
        if ($resource === 'examples') {
            $kind = $id >= 2000 ? 'guide' : 'rule';
            $target = $kind === 'guide' ? 'guideExamples' : 'ruleExamples';
            $index = $this->exampleIndexById($this->{$target}, $kind, $id);
            if ($index === null) {
                return false;
            }
            array_splice($this->{$target}, $index, 1);
            $this->writeAllSeeds();
            return true;
        }

        if ($resource === 'classifications') {
            if ($classificationType === 'section') {
                foreach ($this->exerciseCategories as $categoryIndex => $category) {
                    $sectionIndex = $this->findSectionIndexById((int) $categoryIndex, $id);
                    if ($sectionIndex !== null) {
                        array_splice($this->exerciseCategories[$categoryIndex]['sections'], $sectionIndex, 1);
                        $this->writeAllSeeds();
                        return true;
                    }
                }
                return false;
            }

            $categoryIndex = $this->findCategoryIndexById($id);
            if ($categoryIndex === null) {
                return false;
            }
            array_splice($this->exerciseCategories, $categoryIndex, 1);
            $this->writeAllSeeds();
            return true;
        }

        $removed = $this->removeExerciseById($id);
        if ($removed === null) {
            return false;
        }
        $this->writeAllSeeds();
        return true;
    }

    private function formatExample(array $example, int $id, string $kind): array
    {
        return [
            'id' => $id,
            'title' => $example['title'],
            'category' => $example['category'] ?? '',
            'section' => $example['section'] ?? '',
            'kind' => $kind,
            'guideKey' => $example['guide_key'] ?? null,
            'ruleKey' => $kind === 'guide' ? 'guide:' . ($example['guide_key'] ?? '') : ($example['rule_key'] ?? ''),
            'variantIndex' => (int) ($example['variant_index'] ?? 0),
            'problem' => $example['problem'],
            'answer' => $example['answer'],
        ];
    }

    private function withExerciseIds(array $categories): array
    {
        foreach ($categories as $categoryIndex => $category) {
            $category['id'] = $this->categoryId((int) $categoryIndex);
            $category['sections'] = $category['sections'] ?? [];
            foreach ($category['sections'] as $sectionIndex => $section) {
                $section['id'] = $this->sectionId((int) $categoryIndex, (int) $sectionIndex);
                $section['items'] = $section['items'] ?? [];
                foreach ($section['items'] as $itemIndex => $item) {
                    $item['id'] = $this->itemId((int) $categoryIndex, (int) $sectionIndex, (int) $itemIndex);
                    $section['items'][$itemIndex] = $item;
                }
                $category['sections'][$sectionIndex] = $section;
            }
            $categories[$categoryIndex] = $category;
        }

        return $categories;
    }

    private function categoryId(int $categoryIndex): int
    {
        return 100 + $categoryIndex;
    }

    private function sectionId(int $categoryIndex, int $sectionIndex): int
    {
        return 10000 + ($categoryIndex * 100) + $sectionIndex;
    }

    private function itemId(int $categoryIndex, int $sectionIndex, int $itemIndex): int
    {
        return 100000 + ($categoryIndex * 10000) + ($sectionIndex * 100) + $itemIndex;
    }

    private function exampleIndexById(array $examples, string $kind, int $id): ?int
    {
        $base = $kind === 'guide' ? 2000 : 1000;
        $index = $id - $base;
        return array_key_exists($index, $examples) ? $index : null;
    }

    private function findCategoryIndexById(int $id): ?int
    {
        $index = $id - 100;
        return array_key_exists($index, $this->exerciseCategories) ? $index : null;
    }

    private function findCategoryIndexByTitle(string $title): ?int
    {
        foreach ($this->exerciseCategories as $index => $category) {
            if (($category['title'] ?? '') === $title) {
                return (int) $index;
            }
        }

        return null;
    }

    private function findSectionIndexById(int $categoryIndex, int $id): ?int
    {
        $index = $id - 10000 - ($categoryIndex * 100);
        return array_key_exists($index, $this->exerciseCategories[$categoryIndex]['sections'] ?? []) ? $index : null;
    }

    private function findSectionIndexByTitle(int $categoryIndex, string $title): ?int
    {
        foreach (($this->exerciseCategories[$categoryIndex]['sections'] ?? []) as $index => $section) {
            if (($section['title'] ?? '') === $title) {
                return (int) $index;
            }
        }

        return null;
    }

    private function findOrCreateCategoryIndex(string $title): int
    {
        $index = $this->findCategoryIndexByTitle($title);
        if ($index !== null) {
            return $index;
        }

        $this->exerciseCategories[] = [
            'title' => $title,
            'sort_order' => $this->nextCategorySortOrder(),
            'sections' => [],
        ];

        return (int) array_key_last($this->exerciseCategories);
    }

    private function findOrCreateSectionIndex(int $categoryIndex, string $title): int
    {
        $index = $this->findSectionIndexByTitle($categoryIndex, $title);
        if ($index !== null) {
            return $index;
        }

        $this->exerciseCategories[$categoryIndex]['sections'][] = [
            'title' => $title,
            'sort_order' => $this->nextSectionSortOrder($categoryIndex),
            'items' => [],
        ];

        return (int) array_key_last($this->exerciseCategories[$categoryIndex]['sections']);
    }

    private function removeExerciseById(int $id): ?array
    {
        foreach ($this->exerciseCategories as $categoryIndex => $category) {
            foreach (($category['sections'] ?? []) as $sectionIndex => $section) {
                foreach (($section['items'] ?? []) as $itemIndex => $item) {
                    if ($this->itemId((int) $categoryIndex, (int) $sectionIndex, (int) $itemIndex) === $id) {
                        array_splice($this->exerciseCategories[$categoryIndex]['sections'][$sectionIndex]['items'], $itemIndex, 1);
                        return $item;
                    }
                }
            }
        }

        return null;
    }

    private function nextCategorySortOrder(): int
    {
        return $this->nextSortOrder($this->exerciseCategories);
    }

    private function nextSectionSortOrder(int $categoryIndex): int
    {
        return $this->nextSortOrder($this->exerciseCategories[$categoryIndex]['sections'] ?? []);
    }

    private function nextItemSortOrder(int $categoryIndex, int $sectionIndex): int
    {
        return $this->nextSortOrder($this->exerciseCategories[$categoryIndex]['sections'][$sectionIndex]['items'] ?? []);
    }

    private function nextSortOrder(array $items): int
    {
        $max = 0;
        foreach ($items as $item) {
            $max = max($max, (int) ($item['sort_order'] ?? 0));
        }

        return $max + 100;
    }

    private function sortExercises(): void
    {
        usort($this->exerciseCategories, static fn (array $a, array $b): int => ((int) $a['sort_order']) <=> ((int) $b['sort_order']));
        foreach ($this->exerciseCategories as &$category) {
            usort($category['sections'], static fn (array $a, array $b): int => ((int) $a['sort_order']) <=> ((int) $b['sort_order']));
            foreach ($category['sections'] as &$section) {
                usort($section['items'], static fn (array $a, array $b): int => ((int) $a['sort_order']) <=> ((int) $b['sort_order']));
            }
        }
    }

    private function adminAuthPath(): string
    {
        return __DIR__ . '/admin-auth.json';
    }

    private function adminPasswordHash(): string
    {
        $path = $this->adminAuthPath();

        if (is_readable($path)) {
            $payload = json_decode((string) file_get_contents($path), true);
            $hash = is_array($payload) ? ($payload['password_hash'] ?? null) : null;

            if (is_string($hash) && $hash !== '') {
                return $hash;
            }
        }

        return password_hash('admin', PASSWORD_DEFAULT);
    }

    private function writeAllSeeds(): void
    {
        writeSeedJson('rule_examples.json', $this->ruleExamples);
        writeSeedJson('guide_examples.json', $this->guideExamples);
        writeSeedJson('exercises.json', $this->exerciseCategories);
    }

    private function counts(): array
    {
        return [
            'ruleExamples' => count($this->ruleExamples),
            'guideExamples' => count($this->guideExamples),
            'exerciseCategories' => count($this->exerciseCategories),
            'exerciseEntries' => array_sum(array_map(
                static fn (array $category): int => array_sum(array_map(
                    static fn (array $section): int => count($section['items'] ?? []),
                    $category['sections'] ?? [],
                )),
                $this->exerciseCategories,
            )),
        ];
    }
}

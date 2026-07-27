<?php

declare(strict_types=1);

function logicDatabasePath(): string
{
    $configuredPath = getenv('LOGIC_DB_PATH');

    if (is_string($configuredPath) && trim($configuredPath) !== '') {
        return $configuredPath;
    }

    return __DIR__ . '/logic.sqlite';
}

function getLogicDatabase(): PDO
{
    static $database = null;

    if ($database instanceof PDO) {
        return $database;
    }

    $databasePath = logicDatabasePath();
    $databaseDirectory = dirname($databasePath);

    if (!is_dir($databaseDirectory) && !mkdir($databaseDirectory, 0775, true) && !is_dir($databaseDirectory)) {
        throw new RuntimeException('SQLite 데이터 디렉터리를 만들 수 없습니다.');
    }

    $database = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $database->exec('PRAGMA foreign_keys = ON');

    initializeLogicDatabase($database);

    return $database;
}

function initializeLogicDatabase(PDO $database): void
{
    $schema = file_get_contents(__DIR__ . '/schema.sql');

    if ($schema === false) {
        throw new RuntimeException('SQLite 스키마를 읽을 수 없습니다.');
    }

    $database->exec($schema);
    ensureProblemExampleTitleColumn($database);
    ensureProblemExampleColumns($database);

    $examples = loadRuleExampleSeeds();
    $guideExamples = loadGuideExampleSeeds();
    $exerciseCategories = loadExerciseSeeds();

    $exampleCount = (int) $database
        ->query('SELECT COUNT(*) FROM problem_examples')
        ->fetchColumn();

    if ($exampleCount > 0) {
        syncRuleExampleMetadata($database, $examples);
    } else {
        seedRuleExamples($database, $examples);
    }

    syncGuideExamples($database, $guideExamples);
    dropLegacyGuideExamplesTable($database);
    syncExercises($database, $exerciseCategories);
}

function ensureProblemExampleColumns(PDO $database): void
{
    ensureTextColumn($database, 'problem_examples', 'category_title', "TEXT NOT NULL DEFAULT ''");
    ensureTextColumn($database, 'problem_examples', 'section_title', "TEXT NOT NULL DEFAULT ''");
    ensureTextColumn($database, 'problem_examples', 'example_kind', "TEXT NOT NULL DEFAULT 'rule'");
    ensureTextColumn($database, 'problem_examples', 'guide_key', 'TEXT DEFAULT NULL');
    $database->exec(
        'CREATE INDEX IF NOT EXISTS idx_problem_examples_example_kind
         ON problem_examples(example_kind)'
    );
    $database->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_problem_examples_guide_key
         ON problem_examples(guide_key)'
    );
}

function ensureTextColumn(PDO $database, string $table, string $column, string $definition): void
{
    $allowedTables = ['problem_examples'];
    $allowedColumns = ['category_title', 'section_title', 'example_kind', 'guide_key'];

    if (!in_array($table, $allowedTables, true) || !in_array($column, $allowedColumns, true)) {
        throw new InvalidArgumentException('허용되지 않은 예제 분류 열입니다.');
    }

    $columns = $database->query(sprintf('PRAGMA table_info(%s)', $table))->fetchAll();

    foreach ($columns as $existingColumn) {
        if ($existingColumn['name'] === $column) {
            return;
        }
    }

    $database->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
}

function dropLegacyGuideExamplesTable(PDO $database): void
{
    $database->exec('DROP TABLE IF EXISTS guide_examples');
}

function ensureProblemExampleTitleColumn(PDO $database): void
{
    $columns = $database->query('PRAGMA table_info(problem_examples)')->fetchAll();
    $hasTitleColumn = false;

    foreach ($columns as $column) {
        if ($column['name'] === 'title') {
            $hasTitleColumn = true;
            break;
        }
    }

    if (!$hasTitleColumn) {
        $database->exec(
            "ALTER TABLE problem_examples
             ADD COLUMN title TEXT NOT NULL DEFAULT ''"
        );
    }
}

/**
 * @return array<int, array{
 *     title: string,
 *     category: string,
 *     section: string,
 *     rule_key: string,
 *     variant_index: int,
 *     problem: string,
 *     answer: string,
 *     logic_type: string
 * }>
 */
function loadRuleExampleSeeds(): array
{
    $seedPath = __DIR__ . '/rule_examples.json';
    $seedJson = file_get_contents($seedPath);

    if ($seedJson === false) {
        throw new RuntimeException('추론 규칙 예제 초기 데이터를 읽을 수 없습니다.');
    }

    $examples = json_decode($seedJson, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($examples)) {
        throw new RuntimeException('추론 규칙 예제 초기 데이터 형식이 올바르지 않습니다.');
    }

    return $examples;
}

/**
 * @return array<int, array{
 *     guide_key: string,
 *     title: string,
 *     category: string,
 *     section: string,
 *     problem: string,
 *     answer: string,
 *     logic_type: string
 * }>
 */
function loadGuideExampleSeeds(): array
{
    $seedPath = __DIR__ . '/guide_examples.json';
    $seedJson = file_get_contents($seedPath);

    if ($seedJson === false) {
        throw new RuntimeException('입력 가이드 예제 초기 데이터를 읽을 수 없습니다.');
    }

    $examples = json_decode($seedJson, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($examples)) {
        throw new RuntimeException('입력 가이드 예제 초기 데이터 형식이 올바르지 않습니다.');
    }

    return $examples;
}

/**
 * @return array<int, array{
 *     title: string,
 *     logic_type: string,
 *     sort_order: int,
 *     sections: array<int, array{
 *         title: string,
 *         sort_order: int,
 *         items: array<int, array{
 *             title: string,
 *             problem: string,
 *             sort_order: int
 *         }>
 *     }>
 * }>
 */
function loadExerciseSeeds(): array
{
    $seedPath = __DIR__ . '/exercises.json';
    $seedJson = file_get_contents($seedPath);

    if ($seedJson === false) {
        throw new RuntimeException('연습문제 초기 데이터를 읽을 수 없습니다.');
    }

    $categories = json_decode($seedJson, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($categories)) {
        throw new RuntimeException('연습문제 초기 데이터 형식이 올바르지 않습니다.');
    }

    return $categories;
}

/**
 * @param array<int, array{
 *     title: string,
 *     logic_type: string,
 *     sort_order: int,
 *     sections: array<int, array{
 *         title: string,
 *         sort_order: int,
 *         items: array<int, array{
 *             title: string,
 *             problem: string,
 *             sort_order: int
 *         }>
 *     }>
 * }> $categories
 */
function syncExercises(PDO $database, array $categories): void
{
    $upsertCategory = $database->prepare(
        'INSERT INTO exercise_categories (title, logic_type, sort_order)
         VALUES (:title, :logic_type, :sort_order)
         ON CONFLICT(title) DO UPDATE SET
            logic_type = excluded.logic_type,
            sort_order = excluded.sort_order'
    );
    $findCategory = $database->prepare(
        'SELECT id FROM exercise_categories WHERE title = :title'
    );
    $upsertSection = $database->prepare(
        'INSERT INTO exercise_sections (category_id, title, sort_order)
         VALUES (:category_id, :title, :sort_order)
         ON CONFLICT(category_id, sort_order) DO UPDATE SET
            title = excluded.title,
            sort_order = excluded.sort_order'
    );
    $findSection = $database->prepare(
        'SELECT id FROM exercise_sections
         WHERE category_id = :category_id AND sort_order = :sort_order'
    );
    $findProblem = $database->prepare(
        'SELECT id FROM problems
         WHERE problem_text = :problem_text AND logic_type = :logic_type
         ORDER BY id ASC
         LIMIT 1'
    );
    $insertProblem = $database->prepare(
        'INSERT INTO problems (title, problem_text, logic_type)
         VALUES (:title, :problem_text, :logic_type)'
    );
    $upsertEntry = $database->prepare(
        'INSERT INTO exercise_entries
            (problem_id, section_id, title, sort_order)
         VALUES
            (:problem_id, :section_id, :title, :sort_order)
         ON CONFLICT(section_id, sort_order) DO UPDATE SET
            problem_id = excluded.problem_id,
            title = excluded.title,
            sort_order = excluded.sort_order'
    );

    $database->beginTransaction();

    try {
        foreach ($categories as $category) {
            $upsertCategory->execute([
                ':title' => $category['title'],
                ':logic_type' => $category['logic_type'],
                ':sort_order' => $category['sort_order'],
            ]);
            $findCategory->execute([':title' => $category['title']]);
            $categoryId = (int) $findCategory->fetchColumn();

            foreach ($category['sections'] as $section) {
                $upsertSection->execute([
                    ':category_id' => $categoryId,
                    ':title' => $section['title'],
                    ':sort_order' => $section['sort_order'],
                ]);
                $findSection->execute([
                    ':category_id' => $categoryId,
                    ':sort_order' => $section['sort_order'],
                ]);
                $sectionId = (int) $findSection->fetchColumn();

                foreach ($section['items'] as $item) {
                    $findProblem->execute([
                        ':problem_text' => $item['problem'],
                        ':logic_type' => $category['logic_type'],
                    ]);
                    $problemId = $findProblem->fetchColumn();

                    if ($problemId === false) {
                        $insertProblem->execute([
                            ':title' => $item['title'],
                            ':problem_text' => $item['problem'],
                            ':logic_type' => $category['logic_type'],
                        ]);
                        $problemId = (int) $database->lastInsertId();
                    }

                    $upsertEntry->execute([
                        ':problem_id' => (int) $problemId,
                        ':section_id' => $sectionId,
                        ':title' => $item['title'],
                        ':sort_order' => $item['sort_order'],
                    ]);
                }
            }
        }

        $database->commit();
    } catch (Throwable $error) {
        $database->rollBack();
        throw $error;
    }
}

/**
 * @return array<int, array{
 *     id: int,
 *     title: string,
 *     logic_type: string,
 *     sections: array<int, array{
 *         id: int,
 *         title: string,
 *         items: array<int, array{id: int, title: string, problem: string}>
 *     }>
 * }>
 */
function getExerciseCatalog(PDO $database): array
{
    $rows = $database->query(
        'SELECT
            category.id AS category_id,
            category.title AS category_title,
            category.logic_type,
            section.id AS section_id,
            section.title AS section_title,
            entry.id AS entry_id,
            entry.title AS entry_title,
            problem.problem_text
         FROM exercise_categories AS category
         INNER JOIN exercise_sections AS section
            ON section.category_id = category.id
         INNER JOIN exercise_entries AS entry
            ON entry.section_id = section.id
         INNER JOIN problems AS problem
            ON problem.id = entry.problem_id
         ORDER BY
            category.sort_order,
            category.id,
            section.sort_order,
            section.id,
            entry.sort_order,
            entry.id'
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
                'logic_type' => $row['logic_type'],
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
                'items' => [],
            ];
        }

        $sectionIndex = $sectionIndexes[$sectionId]['section_index'];
        $categories[$categoryIndex]['sections'][$sectionIndex]['items'][] = [
            'id' => (int) $row['entry_id'],
            'title' => $row['entry_title'],
            'problem' => $row['problem_text'],
        ];
    }

    return $categories;
}

/**
 * @param array<int, array{
 *     guide_key: string,
 *     title: string,
 *     category: string,
 *     section: string,
 *     problem: string,
 *     answer: string,
 *     logic_type: string
 * }> $examples
 */
function syncGuideExamples(PDO $database, array $examples): void
{
    $findProblem = $database->prepare(
        'SELECT id FROM problems
         WHERE problem_text = :problem_text AND logic_type = :logic_type
         ORDER BY id ASC
         LIMIT 1'
    );
    $insertProblem = $database->prepare(
        'INSERT INTO problems (title, problem_text, logic_type)
         VALUES (:title, :problem_text, :logic_type)'
    );
    $upsertExample = $database->prepare(
        'INSERT INTO problem_examples
            (
                problem_id,
                title,
                category_title,
                section_title,
                example_kind,
                guide_key,
                rule_key,
                variant_index,
                answer_text
            )
         VALUES
            (
                :problem_id,
                :title,
                :category_title,
                :section_title,
                :example_kind,
                :guide_key,
                :rule_key,
                :variant_index,
                :answer_text
            )
         ON CONFLICT(guide_key) DO UPDATE SET
            problem_id = excluded.problem_id,
            title = excluded.title,
            category_title = excluded.category_title,
            section_title = excluded.section_title,
            example_kind = excluded.example_kind,
            rule_key = excluded.rule_key,
            variant_index = excluded.variant_index,
            answer_text = excluded.answer_text'
    );

    $database->beginTransaction();

    try {
        foreach ($examples as $example) {
            $findProblem->execute([
                ':problem_text' => $example['problem'],
                ':logic_type' => $example['logic_type'],
            ]);
            $problemId = $findProblem->fetchColumn();

            if ($problemId === false) {
                $insertProblem->execute([
                    ':title' => $example['title'],
                    ':problem_text' => $example['problem'],
                    ':logic_type' => $example['logic_type'],
                ]);
                $problemId = (int) $database->lastInsertId();
            }

            $upsertExample->execute([
                ':problem_id' => (int) $problemId,
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

        $database->commit();
    } catch (Throwable $error) {
        $database->rollBack();
        throw $error;
    }
}

/**
 * @param array<int, array{
 *     title: string,
 *     category: string,
 *     section: string,
 *     rule_key: string,
 *     variant_index: int,
 *     problem: string,
 *     answer: string,
 *     logic_type: string
 * }> $examples
 */
function syncRuleExampleMetadata(PDO $database, array $examples): void
{
    $updateMetadata = $database->prepare(
        'UPDATE problem_examples
         SET
            title = :title,
            category_title = :category_title,
            section_title = :section_title,
            example_kind = :example_kind,
            guide_key = NULL
         WHERE rule_key = :rule_key AND variant_index = :variant_index'
    );

    $database->beginTransaction();

    try {
        foreach ($examples as $example) {
            $updateMetadata->execute([
                ':title' => $example['title'],
                ':category_title' => $example['category'],
                ':section_title' => $example['section'],
                ':example_kind' => 'rule',
                ':rule_key' => $example['rule_key'],
                ':variant_index' => $example['variant_index'],
            ]);
        }

        $database->commit();
    } catch (Throwable $error) {
        $database->rollBack();
        throw $error;
    }
}

/**
 * @param array<int, array{
 *     title: string,
 *     category: string,
 *     section: string,
 *     rule_key: string,
 *     variant_index: int,
 *     problem: string,
 *     answer: string,
 *     logic_type: string
 * }> $examples
 */
function seedRuleExamples(PDO $database, array $examples): void
{
    $findProblem = $database->prepare(
        'SELECT id FROM problems
         WHERE problem_text = :problem_text AND logic_type = :logic_type
         ORDER BY id ASC
         LIMIT 1'
    );
    $insertProblem = $database->prepare(
        'INSERT INTO problems (title, problem_text, logic_type)
         VALUES (:title, :problem_text, :logic_type)'
    );
    $insertExample = $database->prepare(
        'INSERT INTO problem_examples
            (
                problem_id,
                title,
                category_title,
                section_title,
                example_kind,
                guide_key,
                rule_key,
                variant_index,
                answer_text
            )
         VALUES
            (
                :problem_id,
                :title,
                :category_title,
                :section_title,
                :example_kind,
                :guide_key,
                :rule_key,
                :variant_index,
                :answer_text
            )'
    );

    $database->beginTransaction();

    try {
        foreach ($examples as $example) {
            $findProblem->execute([
                ':problem_text' => $example['problem'],
                ':logic_type' => $example['logic_type'],
            ]);
            $problemId = $findProblem->fetchColumn();

            if ($problemId === false) {
                $insertProblem->execute([
                    ':title' => $example['rule_key'] . ' 예제 ' . ($example['variant_index'] + 1),
                    ':problem_text' => $example['problem'],
                    ':logic_type' => $example['logic_type'],
                ]);
                $problemId = (int) $database->lastInsertId();
            }

            $insertExample->execute([
                ':problem_id' => (int) $problemId,
                ':title' => $example['title'],
                ':category_title' => $example['category'],
                ':section_title' => $example['section'],
                ':example_kind' => 'rule',
                ':guide_key' => null,
                ':rule_key' => $example['rule_key'],
                ':variant_index' => $example['variant_index'],
                ':answer_text' => $example['answer'],
            ]);
        }

        $database->commit();
    } catch (Throwable $error) {
        $database->rollBack();
        throw $error;
    }
}

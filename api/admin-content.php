<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respondWithAdminJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function requireAdminSession(): void
{
    if (!currentLogicUserIsAdmin()) {
        respondWithAdminJson(['error' => '관리자 로그인이 필요합니다.'], 401);
    }
}

function readJsonBody(): array
{
    $body = file_get_contents('php://input');

    if ($body === false || trim($body) === '') {
        return [];
    }

    $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($data)) {
        respondWithAdminJson(['error' => 'JSON 객체가 필요합니다.'], 400);
    }

    return $data;
}

function requiredString(array $data, string $key): string
{
    $value = $data[$key] ?? null;

    if (!is_string($value) || trim($value) === '') {
        respondWithAdminJson(['error' => $key . ' 값이 필요합니다.'], 400);
    }

    return trim($value);
}

function optionalString(array $data, string $key, string $default = ''): string
{
    $value = $data[$key] ?? $default;

    if (!is_string($value)) {
        respondWithAdminJson(['error' => $key . ' 값은 문자열이어야 합니다.'], 400);
    }

    return trim($value);
}

function requiredInt(array $data, string $key): int
{
    $value = $data[$key] ?? null;

    if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) {
        respondWithAdminJson(['error' => $key . ' 값은 정수여야 합니다.'], 400);
    }

    return (int) $value;
}

function requestId(): int
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($id === false || $id === null || $id < 1) {
        respondWithAdminJson(['error' => '올바른 id가 필요합니다.'], 400);
    }

    return $id;
}

function updateAdminPassword($database, array $data): void
{
    $user = currentLogicUser();

    if (!$user) {
        respondWithAdminJson(['error' => '관리자 로그인이 필요합니다.'], 401);
    }

    $currentPassword = requiredString($data, 'currentPassword');
    $newPassword = requiredString($data, 'newPassword');
    $confirmPassword = requiredString($data, 'confirmPassword');

    if ($newPassword !== $confirmPassword) {
        respondWithAdminJson(['error' => '새 암호 확인이 일치하지 않습니다.'], 400);
    }

    if (strlen($newPassword) < 4) {
        respondWithAdminJson(['error' => '새 암호는 4자 이상이어야 합니다.'], 400);
    }

    if ($database instanceof LogicSeedStore) {
        if (!$database->verifyAdminLogin('admin', $currentPassword)) {
            respondWithAdminJson(['error' => '현재 암호가 올바르지 않습니다.'], 400);
        }

        $database->updateAdminPassword($newPassword);
        return;
    }

    $statement = $database->prepare(
        'SELECT password_hash
         FROM users
         WHERE id = :id AND role = :role
         LIMIT 1'
    );
    $statement->execute([
        ':id' => (int) $user['id'],
        ':role' => 'admin',
    ]);
    $storedHash = $statement->fetchColumn();

    if (!is_string($storedHash) || !password_verify($currentPassword, $storedHash)) {
        respondWithAdminJson(['error' => '현재 암호가 올바르지 않습니다.'], 400);
    }

    $update = $database->prepare(
        'UPDATE users
         SET password_hash = :password_hash
         WHERE id = :id'
    );
    $update->execute([
        ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ':id' => (int) $user['id'],
    ]);
}

function fetchAdminExamples($database): array
{
    if ($database instanceof LogicSeedStore) {
        return $database->getAdminExamples();
    }

    $rows = $database->query(
        'SELECT
            pe.id,
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

    return array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'category' => $row['category_title'],
        'section' => $row['section_title'],
        'kind' => $row['example_kind'],
        'guideKey' => $row['guide_key'],
        'ruleKey' => $row['rule_key'],
        'variantIndex' => (int) $row['variant_index'],
        'problem' => $row['problem_text'],
        'answer' => $row['answer_text'],
    ], $rows);
}

function upsertAdminExample($database, array $data, ?int $id = null): int
{
    $kind = optionalString($data, 'kind', 'rule') ?: 'rule';

    if (!in_array($kind, ['rule', 'guide'], true)) {
        respondWithAdminJson(['error' => 'kind는 rule 또는 guide여야 합니다.'], 400);
    }

    $title = requiredString($data, 'title');
    $problem = requiredString($data, 'problem');
    $answer = requiredString($data, 'answer');
    $category = optionalString($data, 'category');
    $section = optionalString($data, 'section');
    $guideKey = optionalString($data, 'guideKey');
    $ruleKey = optionalString($data, 'ruleKey', $kind === 'guide' ? 'guide:' . $guideKey : '');
    $variantIndex = array_key_exists('variantIndex', $data) ? requiredInt($data, 'variantIndex') : 0;

    if ($ruleKey === '') {
        respondWithAdminJson(['error' => 'ruleKey 값이 필요합니다.'], 400);
    }

    if ($kind === 'guide' && $guideKey === '') {
        respondWithAdminJson(['error' => 'guide 예제에는 guideKey 값이 필요합니다.'], 400);
    }

    if ($database instanceof LogicSeedStore) {
        return $database->upsertExample([
            'kind' => $kind,
            'title' => $title,
            'problem' => $problem,
            'answer' => $answer,
            'category' => $category,
            'section' => $section,
            'guideKey' => $guideKey,
            'ruleKey' => $ruleKey,
            'variantIndex' => $variantIndex,
        ], $id);
    }

    $database->beginTransaction();

    try {
        $problemId = findOrCreateProblem($database, $title, $problem);

        if ($id === null) {
            $statement = $database->prepare(
                'INSERT INTO problem_examples
                    (problem_id, title, category_title, section_title, example_kind, guide_key, rule_key, variant_index, answer_text)
                 VALUES
                    (:problem_id, :title, :category_title, :section_title, :example_kind, :guide_key, :rule_key, :variant_index, :answer_text)'
            );
            $params = [];
        } else {
            $statement = $database->prepare(
                'UPDATE problem_examples
                 SET
                    problem_id = :problem_id,
                    title = :title,
                    category_title = :category_title,
                    section_title = :section_title,
                    example_kind = :example_kind,
                    guide_key = :guide_key,
                    rule_key = :rule_key,
                    variant_index = :variant_index,
                    answer_text = :answer_text
                 WHERE id = :id'
            );
            $params = [':id' => $id];
        }

        $statement->execute($params + [
            ':problem_id' => $problemId,
            ':title' => $title,
            ':category_title' => $category,
            ':section_title' => $section,
            ':example_kind' => $kind,
            ':guide_key' => $guideKey === '' ? null : $guideKey,
            ':rule_key' => $ruleKey,
            ':variant_index' => $variantIndex,
            ':answer_text' => $answer,
        ]);

        if ($id !== null && $statement->rowCount() === 0) {
            $exists = $database->prepare('SELECT COUNT(*) FROM problem_examples WHERE id = :id');
            $exists->execute([':id' => $id]);

            if ((int) $exists->fetchColumn() === 0) {
                respondWithAdminJson(['error' => '해당 예제를 찾을 수 없습니다.'], 404);
            }
        }

        $newId = $id ?? (int) $database->lastInsertId();
        $database->commit();

        return $newId;
    } catch (Throwable $error) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }

        throw $error;
    }
}

function fetchAdminExercises($database): array
{
    return getExerciseCatalog($database);
}

function fetchAdminClassifications($database): array
{
    if ($database instanceof LogicSeedStore) {
        return $database->getClassifications();
    }

    $rows = $database->query(
        'SELECT
            category.id AS category_id,
            category.title AS category_title,
            category.sort_order AS category_sort_order,
            section.id AS section_id,
            section.title AS section_title,
            section.sort_order AS section_sort_order
         FROM exercise_categories AS category
         LEFT JOIN exercise_sections AS section ON section.category_id = category.id
         ORDER BY category.sort_order, category.id, section.sort_order, section.id'
    )->fetchAll();
    $categories = [];
    $categoryIndexes = [];

    foreach ($rows as $row) {
        $categoryId = (int) $row['category_id'];

        if (!array_key_exists($categoryId, $categoryIndexes)) {
            $categoryIndexes[$categoryId] = count($categories);
            $categories[] = [
                'id' => $categoryId,
                'title' => $row['category_title'],
                'sort_order' => (int) $row['category_sort_order'],
                'sections' => [],
            ];
        }

        if ($row['section_id'] === null) {
            continue;
        }

        $categories[$categoryIndexes[$categoryId]]['sections'][] = [
            'id' => (int) $row['section_id'],
            'title' => $row['section_title'],
            'sort_order' => (int) $row['section_sort_order'],
        ];
    }

    return $categories;
}

function nextSortOrder(PDO $database, string $table, string $whereClause = '', array $params = []): int
{
    $statement = $database->prepare(
        sprintf('SELECT COALESCE(MAX(sort_order), 0) + 100 FROM %s %s', $table, $whereClause)
    );
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}

function findOrCreateExerciseCategory(PDO $database, string $title): int
{
    $statement = $database->prepare('SELECT id FROM exercise_categories WHERE title = :title');
    $statement->execute([':title' => $title]);
    $id = $statement->fetchColumn();

    if ($id !== false) {
        return (int) $id;
    }

    $insert = $database->prepare(
        'INSERT INTO exercise_categories (title, sort_order)
         VALUES (:title, :sort_order)'
    );
    $insert->execute([
        ':title' => $title,
        ':sort_order' => nextSortOrder($database, 'exercise_categories'),
    ]);

    return (int) $database->lastInsertId();
}

function findOrCreateExerciseSection(PDO $database, int $categoryId, string $title): int
{
    $statement = $database->prepare(
        'SELECT id FROM exercise_sections WHERE category_id = :category_id AND title = :title'
    );
    $statement->execute([
        ':category_id' => $categoryId,
        ':title' => $title,
    ]);
    $id = $statement->fetchColumn();

    if ($id !== false) {
        return (int) $id;
    }

    $insert = $database->prepare(
        'INSERT INTO exercise_sections (category_id, title, sort_order)
         VALUES (:category_id, :title, :sort_order)'
    );
    $insert->execute([
        ':category_id' => $categoryId,
        ':title' => $title,
        ':sort_order' => nextSortOrder(
            $database,
            'exercise_sections',
            'WHERE category_id = :category_id',
            [':category_id' => $categoryId],
        ),
    ]);

    return (int) $database->lastInsertId();
}

function upsertAdminClassification($database, array $data, ?int $id = null): int
{
    $type = optionalString($data, 'type', 'category') ?: 'category';
    $title = requiredString($data, 'title');

    if ($type === 'category') {
        if ($database instanceof LogicSeedStore) {
            return $database->upsertClassification(['type' => 'category', 'title' => $title], $id);
        }

        if ($id === null) {
            return findOrCreateExerciseCategory($database, $title);
        }

        $statement = $database->prepare('UPDATE exercise_categories SET title = :title WHERE id = :id');
        $statement->execute([':title' => $title, ':id' => $id]);
        return $id;
    }

    if ($type !== 'section') {
        respondWithAdminJson(['error' => 'type은 category 또는 section이어야 합니다.'], 400);
    }

    $categoryId = array_key_exists('categoryId', $data) ? requiredInt($data, 'categoryId') : 0;

    if ($categoryId < 1) {
        respondWithAdminJson(['error' => 'section에는 categoryId가 필요합니다.'], 400);
    }

    if ($database instanceof LogicSeedStore) {
        return $database->upsertClassification([
            'type' => 'section',
            'title' => $title,
            'categoryId' => $categoryId,
        ], $id);
    }

    if ($id === null) {
        return findOrCreateExerciseSection($database, $categoryId, $title);
    }

    $statement = $database->prepare(
        'UPDATE exercise_sections
         SET category_id = :category_id, title = :title
         WHERE id = :id'
    );
    $statement->execute([
        ':category_id' => $categoryId,
        ':title' => $title,
        ':id' => $id,
    ]);

    return $id;
}

function reorderAdminExercises($database, array $data): void
{
    $sectionId = array_key_exists('sectionId', $data) ? requiredInt($data, 'sectionId') : 0;
    $ids = $data['ids'] ?? null;

    if ($sectionId < 1 || !is_array($ids)) {
        respondWithAdminJson(['error' => 'sectionId와 ids 배열이 필요합니다.'], 400);
    }

    $ids = array_values(array_map('intval', $ids));

    if ($database instanceof LogicSeedStore) {
        $database->reorderExercises($sectionId, $ids);
        return;
    }

    $database->beginTransaction();

    try {
        $temporary = $database->prepare(
            'UPDATE exercise_entries
             SET sort_order = :sort_order
             WHERE id = :id AND section_id = :section_id'
        );
        foreach ($ids as $index => $id) {
            $temporary->execute([
                ':sort_order' => -100000 - $index,
                ':id' => $id,
                ':section_id' => $sectionId,
            ]);
        }

        foreach ($ids as $index => $id) {
            $temporary->execute([
                ':sort_order' => ($index + 1) * 100,
                ':id' => $id,
                ':section_id' => $sectionId,
            ]);
        }

        $database->commit();
    } catch (Throwable $error) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }

        throw $error;
    }
}

function reorderAdminSections($database, array $data): void
{
    $categoryId = array_key_exists('categoryId', $data) ? requiredInt($data, 'categoryId') : 0;
    $ids = $data['ids'] ?? null;

    if ($categoryId < 1 || !is_array($ids)) {
        respondWithAdminJson(['error' => 'categoryId와 ids 배열이 필요합니다.'], 400);
    }

    $ids = array_values(array_map('intval', $ids));

    if ($database instanceof LogicSeedStore) {
        $database->reorderSections($categoryId, $ids);
        return;
    }

    $database->beginTransaction();

    try {
        $update = $database->prepare(
            'UPDATE exercise_sections
             SET sort_order = :sort_order
             WHERE id = :id AND category_id = :category_id'
        );

        foreach ($ids as $index => $id) {
            $update->execute([
                ':sort_order' => -100000 - $index,
                ':id' => $id,
                ':category_id' => $categoryId,
            ]);
        }

        foreach ($ids as $index => $id) {
            $update->execute([
                ':sort_order' => ($index + 1) * 100,
                ':id' => $id,
                ':category_id' => $categoryId,
            ]);
        }

        $database->commit();
    } catch (Throwable $error) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }

        throw $error;
    }
}

function upsertAdminExercise($database, array $data, ?int $id = null): int
{
    $categoryTitle = requiredString($data, 'category');
    $sectionTitle = requiredString($data, 'section');
    $title = requiredString($data, 'title');
    $problem = requiredString($data, 'problem');

    if ($database instanceof LogicSeedStore) {
        return $database->upsertExercise([
            'category' => $categoryTitle,
            'section' => $sectionTitle,
            'title' => $title,
            'problem' => $problem,
        ], $id);
    }

    $database->beginTransaction();

    try {
        $categoryId = findOrCreateExerciseCategory($database, $categoryTitle);
        $sectionId = findOrCreateExerciseSection($database, $categoryId, $sectionTitle);
        $problemId = findOrCreateProblem($database, $title, $problem);
        $sortOrder = $id === null
            ? nextSortOrder($database, 'exercise_entries', 'WHERE section_id = :section_id', [':section_id' => $sectionId])
            : null;

        if ($id === null) {
            $entry = $database->prepare(
                'INSERT INTO exercise_entries (problem_id, section_id, title, sort_order)
                 VALUES (:problem_id, :section_id, :title, :sort_order)'
            );
            $params = [];
        } else {
            $entry = $database->prepare(
                'UPDATE exercise_entries
                 SET problem_id = :problem_id, section_id = :section_id, title = :title
                 WHERE id = :id'
            );
            $params = [':id' => $id];
        }

        $entry->execute($params + [
            ':problem_id' => $problemId,
            ':section_id' => $sectionId,
            ':title' => $title,
        ] + ($id === null ? [':sort_order' => $sortOrder] : []));

        $newId = $id ?? (int) $database->lastInsertId();
        $database->commit();

        return $newId;
    } catch (Throwable $error) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }

        throw $error;
    }
}

try {
    requireAdminSession();

    $database = getLogicDataStore();
    $resource = $_GET['resource'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (!in_array($resource, ['examples', 'exercises', 'classifications', 'exercise-order', 'section-order', 'seed-import', 'seed-export', 'password'], true)) {
        respondWithAdminJson(['error' => 'resource는 examples, exercises, classifications, exercise-order, section-order, seed-import, seed-export, password 중 하나여야 합니다.'], 400);
    }

    if ($resource === 'password') {
        if ($method !== 'POST') {
            header('Allow: POST');
            respondWithAdminJson(['error' => 'POST 요청만 허용됩니다.'], 405);
        }

        updateAdminPassword($database, readJsonBody());
        respondWithAdminJson(['ok' => true]);
    }

    if ($resource === 'seed-import') {
        if ($method !== 'POST') {
            header('Allow: POST');
            respondWithAdminJson(['error' => 'POST 요청만 허용됩니다.'], 405);
        }

        if ($database instanceof LogicSeedStore) {
            $database->reseed();
        } else {
            reseedLogicDatabase($database);
        }
        respondWithAdminJson(['ok' => true]);
    }

    if ($resource === 'seed-export') {
        if ($method !== 'POST') {
            header('Allow: POST');
            respondWithAdminJson(['error' => 'POST 요청만 허용됩니다.'], 405);
        }

        $counts = $database instanceof LogicSeedStore
            ? $database->exportSeeds()
            : exportLogicDatabaseSeeds($database);
        respondWithAdminJson(['ok' => true, 'counts' => $counts]);
    }

    if ($resource === 'exercise-order') {
        if ($method !== 'POST') {
            header('Allow: POST');
            respondWithAdminJson(['error' => 'POST 요청만 허용됩니다.'], 405);
        }

        reorderAdminExercises($database, readJsonBody());
        respondWithAdminJson(['ok' => true]);
    }

    if ($resource === 'section-order') {
        if ($method !== 'POST') {
            header('Allow: POST');
            respondWithAdminJson(['error' => 'POST 요청만 허용됩니다.'], 405);
        }

        reorderAdminSections($database, readJsonBody());
        respondWithAdminJson(['ok' => true]);
    }

    if ($method === 'GET') {
        if ($resource === 'classifications') {
            respondWithAdminJson(['classifications' => fetchAdminClassifications($database)]);
        }

        respondWithAdminJson([
            $resource => $resource === 'examples' ? fetchAdminExamples($database) : fetchAdminExercises($database),
        ]);
    }

    if ($method === 'POST') {
        $body = readJsonBody();
        $id = match ($resource) {
            'examples' => upsertAdminExample($database, $body),
            'classifications' => upsertAdminClassification($database, $body),
            default => upsertAdminExercise($database, $body),
        };
        respondWithAdminJson(['ok' => true, 'id' => $id], 201);
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        $body = readJsonBody();
        $id = match ($resource) {
            'examples' => upsertAdminExample($database, $body, requestId()),
            'classifications' => upsertAdminClassification($database, $body, requestId()),
            default => upsertAdminExercise($database, $body, requestId()),
        };
        respondWithAdminJson(['ok' => true, 'id' => $id]);
    }

    if ($method === 'DELETE') {
        $deleteBody = $resource === 'classifications' ? readJsonBody() : [];
        if ($database instanceof LogicSeedStore) {
            $deleted = $database->delete(
                $resource,
                requestId(),
                $resource === 'classifications' ? optionalString($deleteBody, 'type', 'category') : 'category',
            );

            if (!$deleted) {
                respondWithAdminJson(['error' => '삭제할 항목을 찾을 수 없습니다.'], 404);
            }

            respondWithAdminJson(['ok' => true]);
        }

        $table = match ($resource) {
            'examples' => 'problem_examples',
            'classifications' => optionalString($deleteBody, 'type', 'category') === 'section'
                ? 'exercise_sections'
                : 'exercise_categories',
            default => 'exercise_entries',
        };
        $statement = $database->prepare(sprintf('DELETE FROM %s WHERE id = :id', $table));
        $statement->execute([':id' => requestId()]);

        if ($statement->rowCount() === 0) {
            respondWithAdminJson(['error' => '삭제할 항목을 찾을 수 없습니다.'], 404);
        }

        respondWithAdminJson(['ok' => true]);
    }

    header('Allow: GET, POST, PUT, PATCH, DELETE');
    respondWithAdminJson(['error' => '지원하지 않는 요청 방식입니다.'], 405);
} catch (JsonException) {
    respondWithAdminJson(['error' => 'JSON 형식이 올바르지 않습니다.'], 400);
} catch (PDOException $error) {
    error_log($error->__toString());
    respondWithAdminJson(['error' => 'DB 작업에 실패했습니다. 중복된 키나 연결 설정을 확인하세요.'], 500);
} catch (Throwable $error) {
    error_log($error->__toString());
    respondWithAdminJson(['error' => '관리 작업에 실패했습니다.'], 500);
}

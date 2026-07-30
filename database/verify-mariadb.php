<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/database.php';

function verify(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $database = getLogicDatabase();
    reseedLogicDatabase($database);

    $counts = [
        'problem_examples' => (int) $database->query('SELECT COUNT(*) FROM problem_examples')->fetchColumn(),
        'exercise_categories' => (int) $database->query('SELECT COUNT(*) FROM exercise_categories')->fetchColumn(),
        'exercise_sections' => (int) $database->query('SELECT COUNT(*) FROM exercise_sections')->fetchColumn(),
        'exercise_entries' => (int) $database->query('SELECT COUNT(*) FROM exercise_entries')->fetchColumn(),
    ];

    verify($counts['problem_examples'] > 0, '예제 시드가 비어 있습니다.');
    verify($counts['exercise_categories'] > 0, '연습문제 범주 시드가 비어 있습니다.');
    verify($counts['exercise_sections'] > 0, '연습문제 섹션 시드가 비어 있습니다.');
    verify($counts['exercise_entries'] > 0, '연습문제 시드가 비어 있습니다.');

    $problemId = findOrCreateProblem($database, '검증 예제', 'P ⊢ P');
    $database->prepare(
        'INSERT INTO problem_examples
            (problem_id, title, category_title, section_title, example_kind, guide_key, rule_key, variant_index, answer_text)
         VALUES
            (:problem_id, :title, :category_title, :section_title, :example_kind, :guide_key, :rule_key, :variant_index, :answer_text)'
    )->execute([
        ':problem_id' => $problemId,
        ':title' => '검증 예제',
        ':category_title' => '검증',
        ':section_title' => 'CRUD',
        ':example_kind' => 'rule',
        ':guide_key' => null,
        ':rule_key' => '__verify__',
        ':variant_index' => 0,
        ':answer_text' => '1. P 전제',
    ]);
    $exampleId = (int) $database->lastInsertId();

    $database->prepare('UPDATE problem_examples SET title = :title WHERE id = :id')
        ->execute([':title' => '검증 예제 수정', ':id' => $exampleId]);
    $updatedTitle = $database->prepare('SELECT title FROM problem_examples WHERE id = :id');
    $updatedTitle->execute([':id' => $exampleId]);
    verify($updatedTitle->fetchColumn() === '검증 예제 수정', '예제 수정 검증에 실패했습니다.');

    $database->prepare('DELETE FROM problem_examples WHERE id = :id')->execute([':id' => $exampleId]);
    $deletedExample = $database->prepare('SELECT COUNT(*) FROM problem_examples WHERE id = :id');
    $deletedExample->execute([':id' => $exampleId]);
    verify((int) $deletedExample->fetchColumn() === 0, '예제 삭제 검증에 실패했습니다.');

    $database->prepare(
        'INSERT INTO exercise_categories (title, sort_order)
         VALUES (:title, :sort_order)
         ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)'
    )->execute([
        ':title' => '검증',
        ':sort_order' => 999,
    ]);
    $categoryId = (int) $database->lastInsertId();

    if ($categoryId === 0) {
        $category = $database->prepare('SELECT id FROM exercise_categories WHERE title = :title');
        $category->execute([':title' => '검증']);
        $categoryId = (int) $category->fetchColumn();
    }

    $database->prepare(
        'INSERT INTO exercise_sections (category_id, title, sort_order)
         VALUES (:category_id, :title, :sort_order)
         ON DUPLICATE KEY UPDATE title = VALUES(title), sort_order = VALUES(sort_order)'
    )->execute([
        ':category_id' => $categoryId,
        ':title' => 'CRUD',
        ':sort_order' => 999,
    ]);
    $sectionId = (int) $database->lastInsertId();

    if ($sectionId === 0) {
        $section = $database->prepare(
            'SELECT id FROM exercise_sections WHERE category_id = :category_id AND title = :title'
        );
        $section->execute([':category_id' => $categoryId, ':title' => 'CRUD']);
        $sectionId = (int) $section->fetchColumn();
    }

    $exerciseProblemId = findOrCreateProblem($database, '검증 연습문제', 'Q ⊢ Q');
    $database->prepare(
        'INSERT INTO exercise_entries (problem_id, section_id, title, sort_order)
         VALUES (:problem_id, :section_id, :title, :sort_order)'
    )->execute([
        ':problem_id' => $exerciseProblemId,
        ':section_id' => $sectionId,
        ':title' => '검증 연습문제',
        ':sort_order' => 999,
    ]);
    $entryId = (int) $database->lastInsertId();

    $database->prepare('UPDATE exercise_entries SET title = :title WHERE id = :id')
        ->execute([':title' => '검증 연습문제 수정', ':id' => $entryId]);
    $updatedEntry = $database->prepare('SELECT title FROM exercise_entries WHERE id = :id');
    $updatedEntry->execute([':id' => $entryId]);
    verify($updatedEntry->fetchColumn() === '검증 연습문제 수정', '연습문제 수정 검증에 실패했습니다.');

    $database->prepare('DELETE FROM exercise_entries WHERE id = :id')->execute([':id' => $entryId]);
    $deletedEntry = $database->prepare('SELECT COUNT(*) FROM exercise_entries WHERE id = :id');
    $deletedEntry->execute([':id' => $entryId]);
    verify((int) $deletedEntry->fetchColumn() === 0, '연습문제 삭제 검증에 실패했습니다.');

    reseedLogicDatabase($database);

    echo json_encode([
        'ok' => true,
        'seed_counts' => $counts,
        'crud' => [
            'examples' => 'insert/update/delete verified',
            'exercises' => 'insert/update/delete verified',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}

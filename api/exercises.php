<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'GET 요청만 지원합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $database = getLogicDataStore();
    $rawId = $_GET['id'] ?? null;

    if ($rawId === null) {
        echo json_encode(
            ['categories' => getExerciseCatalog($database)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        exit;
    }

    if (!ctype_digit((string) $rawId) || (int) $rawId < 1) {
        http_response_code(400);
        echo json_encode(['error' => '올바른 problem id가 필요합니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($database instanceof LogicSeedStore) {
        $exercise = $database->getExercise((int) $rawId);

        if ($exercise === null) {
            http_response_code(404);
            echo json_encode(['error' => '해당 연습문제를 찾을 수 없습니다.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode($exercise, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $statement = $database->prepare(
        'SELECT
            entry.id,
            entry.title,
            problem.problem_text,
            section.title AS section_title,
            category.title AS category_title
         FROM exercise_entries AS entry
         INNER JOIN problems AS problem
            ON problem.id = entry.problem_id
         INNER JOIN exercise_sections AS section
            ON section.id = entry.section_id
         INNER JOIN exercise_categories AS category
            ON category.id = section.category_id
         WHERE entry.id = :id'
    );
    $statement->execute([':id' => (int) $rawId]);
    $exercise = $statement->fetch();

    if ($exercise === false) {
        http_response_code(404);
        echo json_encode(['error' => '해당 연습문제를 찾을 수 없습니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'id' => (int) $exercise['id'],
        'title' => $exercise['title'],
        'problem' => $exercise['problem_text'],
        'section' => $exercise['section_title'],
        'category' => $exercise['category_title'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    error_log((string) $error);
    http_response_code(500);
    echo json_encode(['error' => '연습문제 데이터를 불러오지 못했습니다.'], JSON_UNESCAPED_UNICODE);
}

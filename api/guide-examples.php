<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/database/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respondWithGuideExampleJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    respondWithGuideExampleJson(['error' => 'GET 요청만 허용됩니다.'], 405);
}

try {
    $database = getLogicDatabase();
    $requestedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (isset($_GET['id'])) {
        if ($requestedId === false || $requestedId === null || $requestedId < 1) {
            respondWithGuideExampleJson(['error' => '올바른 guide example id가 필요합니다.'], 400);
        }

        $statement = $database->prepare(
            "SELECT
                pe.id,
                pe.guide_key,
                pe.title,
                pe.category_title,
                pe.section_title,
                p.problem_text,
                pe.answer_text
             FROM problem_examples pe
             INNER JOIN problems p ON p.id = pe.problem_id
             WHERE pe.id = :id AND pe.example_kind = 'guide'"
        );
        $statement->execute([':id' => $requestedId]);
        $example = $statement->fetch();

        if ($example === false) {
            respondWithGuideExampleJson(['error' => '해당 입력 가이드 예제를 찾을 수 없습니다.'], 404);
        }

        respondWithGuideExampleJson([
            'id' => (int) $example['id'],
            'key' => $example['guide_key'],
            'title' => $example['title'],
            'category' => $example['category_title'],
            'section' => $example['section_title'],
            'problem' => $example['problem_text'],
            'answer' => $example['answer_text'],
        ]);
    }

    $examples = $database
        ->query(
            "SELECT
                id,
                guide_key,
                title,
                category_title,
                section_title
             FROM problem_examples
             WHERE example_kind = 'guide'
             ORDER BY id ASC"
        )
        ->fetchAll();

    respondWithGuideExampleJson([
        'examples' => array_map(
            static fn (array $example): array => [
                'id' => (int) $example['id'],
                'key' => $example['guide_key'],
                'title' => $example['title'],
                'category' => $example['category_title'],
                'section' => $example['section_title'],
            ],
            $examples
        ),
    ]);
} catch (Throwable $error) {
    error_log($error->__toString());
    respondWithGuideExampleJson(['error' => '입력 가이드 예제를 불러오지 못했습니다.'], 500);
}

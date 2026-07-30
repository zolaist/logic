<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/database/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respondWithJson(array $payload, int $status = 200): never
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
    respondWithJson(['error' => 'GET 요청만 허용됩니다.'], 405);
}

try {
    $database = getLogicDataStore();
    $requestedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (isset($_GET['id'])) {
        if ($requestedId === false || $requestedId === null || $requestedId < 1) {
            respondWithJson(['error' => '올바른 example id가 필요합니다.'], 400);
        }

        if ($database instanceof LogicSeedStore) {
            $example = $database->findExample($requestedId, 'rule');

            if ($example === null) {
                respondWithJson(['error' => '해당 예제를 찾을 수 없습니다.'], 404);
            }

            respondWithJson([
                'id' => (int) $example['id'],
                'title' => $example['title'],
                'category' => $example['category'],
                'section' => $example['section'],
                'kind' => $example['kind'],
                'key' => $example['guideKey'],
                'rule' => $example['ruleKey'],
                'variantIndex' => (int) $example['variantIndex'],
                'problem' => $example['problem'],
                'answer' => $example['answer'],
            ]);
        }

        $statement = $database->prepare(
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
             WHERE pe.id = :id'
        );
        $statement->execute([':id' => $requestedId]);
        $example = $statement->fetch();

        if ($example === false) {
            respondWithJson(['error' => '해당 예제를 찾을 수 없습니다.'], 404);
        }

        respondWithJson([
            'id' => (int) $example['id'],
            'title' => $example['title'],
            'category' => $example['category_title'],
            'section' => $example['section_title'],
            'kind' => $example['example_kind'],
            'key' => $example['guide_key'],
            'rule' => $example['rule_key'],
            'variantIndex' => (int) $example['variant_index'],
            'problem' => $example['problem_text'],
            'answer' => $example['answer_text'],
        ]);
    }

    if ($database instanceof LogicSeedStore) {
        $examples = $database->getRuleExamples();

        usort($examples, static fn (array $a, array $b): int => [$a['ruleKey'], $a['variantIndex']] <=> [$b['ruleKey'], $b['variantIndex']]);
        respondWithJson([
            'examples' => array_map(
                static fn (array $example): array => [
                    'id' => (int) $example['id'],
                    'title' => $example['title'],
                    'category' => $example['category'],
                    'section' => $example['section'],
                    'kind' => $example['kind'],
                    'key' => $example['guideKey'],
                    'rule' => $example['ruleKey'],
                    'variantIndex' => (int) $example['variantIndex'],
                ],
                $examples
            ),
        ]);
    }

    $examples = $database
        ->query(
            "SELECT
                id,
                title,
                category_title,
                section_title,
                example_kind,
                guide_key,
                rule_key,
                variant_index
             FROM problem_examples
             WHERE example_kind = 'rule'
             ORDER BY LOWER(rule_key) ASC, variant_index ASC"
        )
        ->fetchAll();

    respondWithJson([
        'examples' => array_map(
            static fn (array $example): array => [
                'id' => (int) $example['id'],
                'title' => $example['title'],
                'category' => $example['category_title'],
                'section' => $example['section_title'],
                'kind' => $example['example_kind'],
                'key' => $example['guide_key'],
                'rule' => $example['rule_key'],
                'variantIndex' => (int) $example['variant_index'],
            ],
            $examples
        ),
    ]);
} catch (Throwable $error) {
    error_log($error->__toString());
    respondWithJson(['error' => '예제 데이터를 불러오지 못했습니다.'], 500);
}

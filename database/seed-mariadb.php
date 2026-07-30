<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/database.php';

try {
    $database = getLogicDatabase();
    reseedLogicDatabase($database);

    $counts = [
        'problems' => (int) $database->query('SELECT COUNT(*) FROM problems')->fetchColumn(),
        'problem_examples' => (int) $database->query('SELECT COUNT(*) FROM problem_examples')->fetchColumn(),
        'exercise_categories' => (int) $database->query('SELECT COUNT(*) FROM exercise_categories')->fetchColumn(),
        'exercise_sections' => (int) $database->query('SELECT COUNT(*) FROM exercise_sections')->fetchColumn(),
        'exercise_entries' => (int) $database->query('SELECT COUNT(*) FROM exercise_entries')->fetchColumn(),
    ];

    echo json_encode($counts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}

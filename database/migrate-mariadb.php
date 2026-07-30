<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/database.php';

try {
    $database = getLogicDatabase();
    initializeLogicDatabase($database);

    echo json_encode([
        'ok' => true,
        'schema_version' => LOGIC_DATABASE_SCHEMA_VERSION,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}

<?php

try {
    require __DIR__ . '/../public/index.php';
} catch (\Throwable $e) {
    http_response_code(500);

    echo '<h1>Laravel Error</h1>';
    echo '<pre>';
    echo 'Message: ' . htmlspecialchars($e->getMessage()) . "\n\n";
    echo 'File: ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . "\n\n";
    echo htmlspecialchars($e->getTraceAsString());
    echo '</pre>';
}

<?php

declare(strict_types=1);

$candidates = [
    __DIR__ . DIRECTORY_SEPARATOR . 'Public' . DIRECTORY_SEPARATOR . 'index.php',
    __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php',
    __DIR__ . DIRECTORY_SEPARATOR . 'Vendor' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php',
];

foreach ($candidates as $entryPoint) {
    if (is_file($entryPoint)) {
        require $entryPoint;
        exit;
    }
}

http_response_code(500);
echo 'Unable to locate CodeIgniter public/index.php entry point.';

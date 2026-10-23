<?php
declare(strict_types=1);

$requestPath = rawurldecode((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
if (str_contains($requestPath, '\\') || str_contains($requestPath, "\0")) {
    http_response_code(400);
    exit('Permintaan tidak valid.');
}

if ($requestPath === '/') {
    require __DIR__ . '/index.php';
    exit;
}

if ($requestPath === '/admin.php' || $requestPath === '/admin.php') {
    require __DIR__ . '/admin.php';
    exit;
}

if (str_starts_with($requestPath, '/storage')
    || str_starts_with($requestPath, '/bootstrap.php')
    || str_starts_with($requestPath, '/router.php')) {
    http_response_code(404);
    exit('Tidak ditemukan.');
}

$allowedAssets = ['/style.css', '/script.js', '/admin.css', '/admin.js'];
if (in_array($requestPath, $allowedAssets, true)) {
    return false;
}

if (str_starts_with($requestPath, '/uploads/projects/')) {
    $filename = basename($requestPath);
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($filename !== '' && in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        $uploadPath = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $requestPath);
        $resolvedUpload = realpath($uploadPath);
        $resolvedDirectory = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'projects');
        if ($resolvedUpload !== false && $resolvedDirectory !== false
            && str_starts_with($resolvedUpload, $resolvedDirectory . DIRECTORY_SEPARATOR)
            && is_file($resolvedUpload)) {
            return false;
        }
    }
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo 'Tidak ditemukan.';

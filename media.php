<?php
require __DIR__ . '/bootstrap.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(404);
    exit;
}

$statement = $pdo->prepare('SELECT file_path, mime_type FROM media WHERE media_id = ?');
$statement->execute([$id]);
$media = $statement->fetch();
if (!$media || !in_array($media['mime_type'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)
    || !preg_match('#^/storage/media/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\.(?:jpg|png|gif|webp)$#iD', $media['file_path'])) {
    http_response_code(404);
    exit;
}

$file = __DIR__ . '/laravel-admin-api/storage/app/public/' . substr($media['file_path'], strlen('/storage/'));
if (!is_file($file) || !is_readable($file)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $media['mime_type']);
header('Content-Length: ' . (string) filesize($file));
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($file);

<?php

declare(strict_types=1);

function denyAdminPage(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, private');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Admin access</title><body><p>'
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        . '</p></body></html>';
    exit;
}

$relativePath = $_GET['file'] ?? null;
if (! is_string($relativePath)
    || ! preg_match('~\A[A-Za-z0-9][A-Za-z0-9._/-]*\.html\z~', $relativePath)
    || preg_match('~(?:\A|/)\.\.?(/|\z)|\\\\~', $relativePath)) {
    denyAdminPage(404, 'Page not found.');
}

$adminRoot = realpath(__DIR__);
$pagePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
if ($adminRoot === false
    || $pagePath === false
    || ! str_starts_with($pagePath, $adminRoot . DIRECTORY_SEPARATOR)
    || ! is_file($pagePath)) {
    denyAdminPage(404, 'Page not found.');
}

$headers = ['Accept: application/json'];
$cookie = $_SERVER['HTTP_COOKIE'] ?? '';
if (is_string($cookie) && $cookie !== '') {
    $headers[] = 'Cookie: ' . $cookie;
}

if (! function_exists('curl_init')) {
    denyAdminPage(503, 'Sign-in verification is unavailable. Please try again.');
}

$request = curl_init('http://127.0.0.1:8001/api/auth/me');
if ($request === false) {
    denyAdminPage(503, 'Sign-in verification is unavailable. Please try again.');
}
curl_setopt_array($request, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_TIMEOUT => 4,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
]);
$responseBody = curl_exec($request);
$responseStatus = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
curl_close($request);

if ($responseBody === false) {
    denyAdminPage(503, 'Sign-in verification is unavailable. Please try again.');
}

$loginPath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin-ui/access.php'), '/\\') . '/login/login-sc.html';
if ($responseStatus === 401) {
    header('Location: ' . $loginPath, true, 302);
    exit;
}
if ($responseStatus !== 200) {
    denyAdminPage(503, 'Sign-in verification is unavailable. Please try again.');
}

$payload = json_decode($responseBody, true);
if (! is_array($payload) || ! is_array($payload['user'] ?? null)) {
    header('Location: ' . $loginPath, true, 302);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
readfile($pagePath);

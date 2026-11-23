<?php

declare(strict_types=1);

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (! is_string($uriPath)) {
    http_response_code(404);
    exit;
}

$path = str_replace('\\', '/', rawurldecode($uriPath));
if (preg_match('~(?:\A|/)\.\.?(/|\z)|\0~', $path)
    || preg_match('~(?:\A|/)\.(?!well-known(?:/|\z))~i', $path)
    || preg_match('~(?:\A|/)(?:laravel-admin-api|tests|migrations)(?:/|\z)~i', $path)
    || preg_match('~\.(?:sql|log|lock|bak|old|orig|save|swp|tmp|ini|env|md)\z~i', $path)
    || preg_match('~/(?:config\.php|config\.sample\.php|schema\.sql|bootstrap\.php|composer\.(?:json|lock))\z~i', $path)) {
    http_response_code(404);
    exit;
}

if (strncasecmp($path, '/admin-ui/', 10) !== 0) {
    return false;
}

$relativePath = substr($path, 10);
if (str_ends_with($relativePath, '/')) {
    $relativePath .= 'index.html';
}
if (in_array(strtolower($relativePath), ['index.html', 'login/login-sc.html', 'setup/setup.html'], true)) {
    return false;
}
if (! preg_match('~\.html\z~i', $relativePath)) {
    return false;
}

$_GET['file'] = $relativePath;
$_SERVER['SCRIPT_NAME'] = substr($path, 0, 10) . 'access.php';
require __DIR__ . '/access.php';

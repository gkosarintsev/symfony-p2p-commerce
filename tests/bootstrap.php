<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';

// Resolve DATABASE_URL: prefer explicitly provided env var (from CI or CLI), fallback to auto-detection
$dbUrl = getenv('DATABASE_URL') ?: ($_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? null);

if (!$dbUrl || str_contains($dbUrl, '!ChangeMe!')) {
    $dbHost = ('postgres' !== gethostbyname('postgres')) ? 'postgres' : '127.0.0.1';
    $dbUrl = "postgresql://app_user:app_password@{$dbHost}:5432/p2p_commerce_test?serverVersion=16&charset=utf8";
} elseif (preg_match('#/p2p_commerce(\?|$)#', $dbUrl)) {
    // If it points to the main database (e.g. from Docker Compose default env), redirect to test database
    $dbUrl = preg_replace('#/p2p_commerce(\?|$)#', '/p2p_commerce_test$1', $dbUrl);
}

$_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $dbUrl;
putenv("DATABASE_URL={$dbUrl}");

if ($_SERVER['APP_DEBUG'] ?? false) {
    umask(0000);
}

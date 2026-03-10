<?php
/**
 * Bootstrap para PHPUnit
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Cargar variables de entorno de testing si existe
$dotenvPath = __DIR__ . '/../';
if (file_exists($dotenvPath . '.env.testing')) {
    $dotenv = Dotenv\Dotenv::createImmutable($dotenvPath, '.env.testing');
    $dotenv->load();
} elseif (file_exists($dotenvPath . '.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($dotenvPath);
    $dotenv->load();
}

<?php
/**
 * Entry Point de la API
 * 
 * Este es el archivo principal que recibe todas las peticiones HTTP
 * y las enruta a los controladores correspondientes.
 */

declare(strict_types=1);

use DI\Container;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;

// Cargar autoloader de Composer
require __DIR__ . '/../vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Crear container para Dependency Injection
$container = new Container();
AppFactory::setContainer($container);

// Crear aplicación Slim
$app = AppFactory::create();

// Cargar dependencias en el container
require __DIR__ . '/../config/container.php';

// Cargar rutas
require __DIR__ . '/../config/routes.php';

// Middleware de errores (mostrar errores en desarrollo)
$app->addErrorMiddleware(
    $_ENV['APP_DEBUG'] === 'true',
    true,
    true
);

// Middleware para parsear JSON body
$app->addBodyParsingMiddleware();

// Ejecutar aplicación
$app->run();

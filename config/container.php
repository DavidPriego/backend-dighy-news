<?php
/**
 * Configuración del Container (Dependency Injection)
 * 
 * Registra los servicios y dependencias de la aplicación.
 */

use Dighy\News\Database\Connection;
use Psr\Container\ContainerInterface;

// Registrar conexión a base de datos
$container->set('db', function (ContainerInterface $c) {
    return Connection::getInstance();
});

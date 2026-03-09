<?php
/**
 * Conexión a Base de Datos MySQL
 * 
 * Singleton para manejar la conexión PDO.
 * Lee la configuración desde variables de entorno.
 */

namespace Dighy\News\Database;

use PDO;
use PDOException;

class Connection
{
    private static ?PDO $instance = null;

    /**
     * Obtiene la instancia de conexión (Singleton)
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }
        return self::$instance;
    }

    /**
     * Crea la conexión PDO con MySQL
     */
    private static function createConnection(): PDO
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $database = $_ENV['DB_DATABASE'] ?? 'dighy_news';
        $username = $_ENV['DB_USERNAME'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            return $pdo;

        } catch (PDOException $e) {
            // En desarrollo, mostrar error
            if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                throw new PDOException("Error de conexión a BD: " . $e->getMessage());
            }
            throw new PDOException("Error de conexión a la base de datos");
        }
    }

    /**
     * Prevenir clonación
     */
    private function __clone() {}

    /**
     * Prevenir deserialización
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}

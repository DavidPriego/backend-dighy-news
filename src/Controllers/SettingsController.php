<?php
/**
 * SettingsController - Configuración Global de Noticias
 * 
 * Endpoints:
 *   GET /api/news/settings     - Configuración pública (section_enabled)
 *   GET /api/admin/settings    - Configuración completa (admin)
 *   PUT /api/admin/settings    - Actualizar configuración (admin)
 */

namespace Dighy\News\Controllers;

use Dighy\News\Database\Connection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class SettingsController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    /**
     * GET /api/news/settings
     * Configuración pública - solo indica si la sección está activa
     */
    public function getPublic(Request $request, Response $response): Response
    {
        $settings = $this->getSettings();
        
        return $this->json($response, [
            'success' => true,
            'data' => [
                'section_enabled' => $settings['section_enabled'],
                'max_items_home' => $settings['max_items_home']
            ]
        ]);
    }

    /**
     * GET /api/admin/settings
     * Configuración completa para admin
     */
    public function get(Request $request, Response $response): Response
    {
        $settings = $this->getSettings();
        
        return $this->json($response, [
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * PUT /api/admin/settings
     * Actualizar configuración
     */
    public function update(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        // Verificar que existe un registro de settings
        $settings = $this->getSettings();
        
        // Campos permitidos
        $allowedFields = [
            'section_enabled',
            'max_items_home',
            'allow_videos',
            'allow_images'
        ];
        
        $updates = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Validation Error',
                'message' => 'No se proporcionaron campos para actualizar'
            ], 422);
        }
        
        // Agregar updated_by si viene
        $updates[] = "updated_by = :updated_by";
        $params['updated_by'] = $data['updated_by'] ?? 1;
        
        try {
            if ($settings['id'] ?? null) {
                // Actualizar registro existente
                $params['id'] = $settings['id'];
                $sql = "UPDATE news_settings SET " . implode(', ', $updates) . " WHERE id = :id";
            } else {
                // Crear registro si no existe
                $sql = "INSERT INTO news_settings (section_enabled, max_items_home, allow_videos, allow_images, updated_by) 
                        VALUES (:section_enabled, :max_items_home, :allow_videos, :allow_images, :updated_by)";
                $params = [
                    'section_enabled' => $data['section_enabled'] ?? 1,
                    'max_items_home' => $data['max_items_home'] ?? 5,
                    'allow_videos' => $data['allow_videos'] ?? 1,
                    'allow_images' => $data['allow_images'] ?? 1,
                    'updated_by' => $data['updated_by'] ?? 1
                ];
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            // Obtener configuración actualizada
            $newSettings = $this->getSettings();
            
            return $this->json($response, [
                'success' => true,
                'message' => 'Configuración actualizada exitosamente',
                'data' => $newSettings
            ]);
            
        } catch (\Exception $e) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Database Error',
                'message' => $_ENV['APP_DEBUG'] === 'true' ? $e->getMessage() : 'Error al actualizar configuración'
            ], 500);
        }
    }

    // =========================================================================
    // MÉTODOS AUXILIARES
    // =========================================================================

    /**
     * Obtiene la configuración actual
     */
    private function getSettings(): array
    {
        $stmt = $this->db->query("SELECT * FROM news_settings LIMIT 1");
        $settings = $stmt->fetch();
        
        if (!$settings) {
            // Valores por defecto si no hay registro
            return [
                'id' => null,
                'section_enabled' => true,
                'max_items_home' => 5,
                'allow_videos' => true,
                'allow_images' => true,
                'updated_at' => null,
                'updated_by' => null
            ];
        }
        
        // Convertir a booleanos
        $settings['section_enabled'] = (bool) $settings['section_enabled'];
        $settings['allow_videos'] = (bool) $settings['allow_videos'];
        $settings['allow_images'] = (bool) $settings['allow_images'];
        
        return $settings;
    }

    /**
     * Helper para respuestas JSON
     */
    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}

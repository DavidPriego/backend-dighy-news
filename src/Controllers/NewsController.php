<?php
/**
 * NewsController - CRUD de Noticias y Actualizaciones
 * 
 * Endpoints:
 *   GET    /api/news           - Lista noticias activas (público)
 *   GET    /api/news/{id}      - Detalle de noticia (público)
 *   GET    /api/admin/news     - Lista TODAS las noticias (admin)
 *   POST   /api/admin/news     - Crear noticia (admin)
 *   PUT    /api/admin/news/{id} - Actualizar noticia (admin)
 *   DELETE /api/admin/news/{id} - Eliminar noticia (admin)
 *   PATCH  /api/admin/news/{id}/toggle - Activar/desactivar (admin)
 */

namespace Dighy\News\Controllers;

use Dighy\News\Database\Connection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class NewsController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    // =========================================================================
    // ENDPOINTS PÚBLICOS
    // =========================================================================

    /**
     * GET /api/news
     * Lista noticias activas para el dashboard
     */
    public function list(Request $request, Response $response): Response
    {
        // Obtener configuración
        $settings = $this->getSettings();
        
        // Si la sección está deshabilitada
        if (!$settings['section_enabled']) {
            return $this->json($response, [
                'success' => true,
                'data' => [
                    'section_enabled' => false,
                    'articles' => [],
                    'total' => 0
                ]
            ]);
        }

        $limit = $settings['max_items_home'];
        
        // Obtener noticias activas ordenadas por destacadas y fecha
        $stmt = $this->db->prepare("
            SELECT 
                id, type, title, slug, excerpt, layout, 
                featured_image, video_url, is_pinned, 
                published_at, created_at
            FROM news_articles
            WHERE is_active = 1 
              AND (published_at IS NULL OR published_at <= NOW())
            ORDER BY is_pinned DESC, published_at DESC, created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $articles = $stmt->fetchAll();

        // Cargar bloques de contenido para cada artículo
        foreach ($articles as &$article) {
            $article['is_pinned'] = (bool) $article['is_pinned'];
            $article['content_blocks'] = $this->getContentBlocks((int) $article['id']);
        }

        return $this->json($response, [
            'success' => true,
            'data' => [
                'section_enabled' => true,
                'articles' => $articles,
                'total' => count($articles)
            ]
        ]);
    }

    /**
     * GET /api/news/{id}
     * Obtiene el detalle de una noticia por ID o slug
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        $idOrSlug = $args['id'];
        
        // Buscar por ID numérico o slug
        if (is_numeric($idOrSlug)) {
            $stmt = $this->db->prepare("
                SELECT * FROM news_articles 
                WHERE id = :id AND is_active = 1
            ");
            $stmt->execute(['id' => (int) $idOrSlug]);
        } else {
            $stmt = $this->db->prepare("
                SELECT * FROM news_articles 
                WHERE slug = :slug AND is_active = 1
            ");
            $stmt->execute(['slug' => $idOrSlug]);
        }
        
        $article = $stmt->fetch();

        if (!$article) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Not Found',
                'message' => 'Artículo no encontrado'
            ], 404);
        }

        // Cargar bloques de contenido
        $article['is_active'] = (bool) $article['is_active'];
        $article['is_pinned'] = (bool) $article['is_pinned'];
        $article['content_blocks'] = $this->getContentBlocks((int) $article['id']);

        return $this->json($response, [
            'success' => true,
            'data' => $article
        ]);
    }

    // =========================================================================
    // ENDPOINTS ADMIN
    // =========================================================================

    /**
     * GET /api/admin/news
     * Lista TODAS las noticias (activas e inactivas)
     */
    public function listAll(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
        $type = $params['type'] ?? null;
        $offset = ($page - 1) * $limit;

        // Construir WHERE dinámico
        $whereClause = '';
        $queryParams = [];
        
        if ($type && in_array($type, ['news', 'update'])) {
            $whereClause = 'WHERE type = :type';
            $queryParams['type'] = $type;
        }

        // Total de registros
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM news_articles $whereClause");
        $countStmt->execute($queryParams);
        $total = (int) $countStmt->fetchColumn();

        // Obtener artículos
        $sql = "
            SELECT 
                id, type, title, slug, excerpt, layout, 
                featured_image, video_url, is_active, is_pinned, 
                published_at, created_by, created_at, updated_at
            FROM news_articles 
            $whereClause
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $this->db->prepare($sql);
        foreach ($queryParams as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $articles = $stmt->fetchAll();

        // Convertir booleanos
        foreach ($articles as &$article) {
            $article['is_active'] = (bool) $article['is_active'];
            $article['is_pinned'] = (bool) $article['is_pinned'];
        }

        return $this->json($response, [
            'success' => true,
            'data' => [
                'articles' => $articles,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int) ceil($total / $limit)
                ]
            ]
        ]);
    }

    /**
     * POST /api/admin/news
     * Crear nueva noticia
     */
    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        // Validación básica
        if (empty($data['title'])) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Validation Error',
                'message' => 'El título es requerido'
            ], 422);
        }

        // Generar slug único
        $slug = $this->generateSlug($data['title']);

        $this->db->beginTransaction();

        try {
            // Insertar artículo
            $stmt = $this->db->prepare("
                INSERT INTO news_articles 
                (type, title, slug, excerpt, layout, featured_image, video_url, is_active, is_pinned, published_at, created_by)
                VALUES 
                (:type, :title, :slug, :excerpt, :layout, :featured_image, :video_url, :is_active, :is_pinned, :published_at, :created_by)
            ");

            $stmt->execute([
                'type' => $data['type'] ?? 'news',
                'title' => $data['title'],
                'slug' => $slug,
                'excerpt' => $data['excerpt'] ?? null,
                'layout' => $data['layout'] ?? 'single',
                'featured_image' => $data['featured_image'] ?? null,
                'video_url' => $data['video_url'] ?? null,
                'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
                'is_pinned' => isset($data['is_pinned']) ? (int) $data['is_pinned'] : 0,
                'published_at' => $data['published_at'] ?? null,
                'created_by' => $data['created_by'] ?? 1 // Sin auth, usar 1 por defecto
            ]);

            $articleId = (int) $this->db->lastInsertId();

            // Insertar bloques de contenido si existen
            if (!empty($data['content_blocks']) && is_array($data['content_blocks'])) {
                $this->insertContentBlocks($articleId, $data['content_blocks']);
            }

            $this->db->commit();

            return $this->json($response, [
                'success' => true,
                'message' => 'Artículo creado exitosamente',
                'data' => [
                    'id' => $articleId,
                    'slug' => $slug
                ]
            ], 201);

        } catch (\Exception $e) {
            $this->db->rollBack();
            return $this->json($response, [
                'success' => false,
                'error' => 'Database Error',
                'message' => $_ENV['APP_DEBUG'] === 'true' ? $e->getMessage() : 'Error al crear el artículo'
            ], 500);
        }
    }

    /**
     * PUT /api/admin/news/{id}
     * Actualizar noticia existente
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();

        // Verificar que existe
        $stmt = $this->db->prepare("SELECT id FROM news_articles WHERE id = :id");
        $stmt->execute(['id' => $id]);
        if (!$stmt->fetch()) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Not Found',
                'message' => 'Artículo no encontrado'
            ], 404);
        }

        $this->db->beginTransaction();

        try {
            // Campos permitidos para actualizar
            $allowedFields = [
                'type', 'title', 'excerpt', 'layout', 
                'featured_image', 'video_url', 'is_active', 
                'is_pinned', 'published_at'
            ];
            $updates = [];
            $params = ['id' => $id];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "{$field} = :{$field}";
                    $params[$field] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $sql = "UPDATE news_articles SET " . implode(', ', $updates) . " WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            // Actualizar bloques de contenido si se proporcionan
            if (isset($data['content_blocks']) && is_array($data['content_blocks'])) {
                // Eliminar bloques existentes
                $stmt = $this->db->prepare("DELETE FROM news_content_blocks WHERE article_id = :article_id");
                $stmt->execute(['article_id' => $id]);
                
                // Insertar nuevos bloques
                $this->insertContentBlocks($id, $data['content_blocks']);
            }

            $this->db->commit();

            return $this->json($response, [
                'success' => true,
                'message' => 'Artículo actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            $this->db->rollBack();
            return $this->json($response, [
                'success' => false,
                'error' => 'Database Error',
                'message' => $_ENV['APP_DEBUG'] === 'true' ? $e->getMessage() : 'Error al actualizar'
            ], 500);
        }
    }

    /**
     * DELETE /api/admin/news/{id}
     * Eliminar noticia
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        $stmt = $this->db->prepare("DELETE FROM news_articles WHERE id = :id");
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() === 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Not Found',
                'message' => 'Artículo no encontrado'
            ], 404);
        }

        return $this->json($response, [
            'success' => true,
            'message' => 'Artículo eliminado exitosamente'
        ]);
    }

    /**
     * PATCH /api/admin/news/{id}/toggle
     * Activar/desactivar noticia
     */
    public function toggle(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        // Toggle is_active
        $stmt = $this->db->prepare("
            UPDATE news_articles 
            SET is_active = NOT is_active 
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() === 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Not Found',
                'message' => 'Artículo no encontrado'
            ], 404);
        }

        // Obtener nuevo estado
        $stmt = $this->db->prepare("SELECT is_active FROM news_articles WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $newState = (bool) $stmt->fetchColumn();

        return $this->json($response, [
            'success' => true,
            'message' => $newState ? 'Artículo activado' : 'Artículo desactivado',
            'data' => ['is_active' => $newState]
        ]);
    }

    // =========================================================================
    // MÉTODOS AUXILIARES PRIVADOS
    // =========================================================================

    /**
     * Obtiene la configuración de la sección de noticias
     */
    private function getSettings(): array
    {
        $stmt = $this->db->query("SELECT * FROM news_settings LIMIT 1");
        $settings = $stmt->fetch();
        
        if (!$settings) {
            return [
                'section_enabled' => true,
                'max_items_home' => 5
            ];
        }
        
        $settings['section_enabled'] = (bool) $settings['section_enabled'];
        return $settings;
    }

    /**
     * Obtiene los bloques de contenido de un artículo
     */
    private function getContentBlocks(int $articleId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, column_position, block_type, content, meta_data, sort_order
            FROM news_content_blocks 
            WHERE article_id = :article_id 
            ORDER BY sort_order ASC
        ");
        $stmt->execute(['article_id' => $articleId]);
        $blocks = $stmt->fetchAll();
        
        // Decodificar meta_data JSON
        foreach ($blocks as &$block) {
            if ($block['meta_data']) {
                $block['meta_data'] = json_decode($block['meta_data'], true);
            }
        }
        
        return $blocks;
    }

    /**
     * Inserta bloques de contenido para un artículo
     */
    private function insertContentBlocks(int $articleId, array $blocks): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO news_content_blocks 
            (article_id, column_position, block_type, content, meta_data, sort_order)
            VALUES 
            (:article_id, :column_position, :block_type, :content, :meta_data, :sort_order)
        ");

        foreach ($blocks as $index => $block) {
            $stmt->execute([
                'article_id' => $articleId,
                'column_position' => $block['column_position'] ?? 'main',
                'block_type' => $block['block_type'] ?? 'text',
                'content' => $block['content'] ?? '',
                'meta_data' => isset($block['meta_data']) ? json_encode($block['meta_data']) : null,
                'sort_order' => $block['sort_order'] ?? $index
            ]);
        }
    }

    /**
     * Genera un slug único a partir del título
     */
    private function generateSlug(string $title): string
    {
        // Convertir a minúsculas y limpiar
        $slug = mb_strtolower($title, 'UTF-8');
        
        // Reemplazar caracteres especiales españoles
        $slug = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $slug
        );
        
        // Eliminar caracteres no alfanuméricos
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        
        // Reemplazar espacios por guiones
        $slug = preg_replace('/[\s_]+/', '-', $slug);
        
        // Eliminar guiones duplicados
        $slug = preg_replace('/-+/', '-', $slug);
        
        // Trim guiones
        $slug = trim($slug, '-');
        
        // Verificar unicidad
        $baseSlug = $slug;
        $counter = 1;
        while ($this->slugExists($slug)) {
            $slug = $baseSlug . '-' . $counter++;
        }
        
        return $slug;
    }

    /**
     * Verifica si un slug ya existe
     */
    private function slugExists(string $slug): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM news_articles WHERE slug = :slug");
        $stmt->execute(['slug' => $slug]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Helper para devolver respuestas JSON
     */
    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}

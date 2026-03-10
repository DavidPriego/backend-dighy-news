<?php
/**
 * NewsController - CRUD de Noticias, Actualizaciones y Categorías
 * 
 * Endpoints Noticias:
 *   GET    /api/news           - Lista noticias activas (público)
 *   GET    /api/news/{id}      - Detalle de noticia (público)
 *   GET    /api/admin/news     - Lista TODAS las noticias (admin)
 *   GET    /api/admin/news/{id} - Detalle de noticia (admin)
 *   POST   /api/admin/news     - Crear noticia (admin)
 *   PUT    /api/admin/news/{id} - Actualizar noticia (admin)
 *   DELETE /api/admin/news/{id} - Eliminar noticia (admin)
 *   PATCH  /api/admin/news/{id}/toggle - Activar/desactivar (admin)
 * 
 * Endpoints Categorías:
 *   GET    /api/categories        - Lista categorías activas (público)
 *   GET    /api/admin/categories  - Lista TODAS las categorías (admin)
 *   POST   /api/admin/categories  - Crear categoría (admin)
 *   PUT    /api/admin/categories/{id} - Actualizar categoría (admin)
 *   DELETE /api/admin/categories/{id} - Eliminar categoría (admin)
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
                'data' => ['section_enabled' => false, 'articles' => [], 'total' => 0]
            ]);
        }

        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(50, max(1, (int) ($params['limit'] ?? $settings['max_items_home'])));
        $search = trim($params['search'] ?? '');
        $categoryId = (int) ($params['category'] ?? 0);
        $offset = ($page - 1) * $limit;

        // Construir WHERE dinámico
        $conditions = ['is_active = 1', '(published_at IS NULL OR published_at <= NOW())'];
        $queryParams = [];

        if ($search) {
            $conditions[] = '(title LIKE :search1 OR excerpt LIKE :search2 OR id IN (
                SELECT news_article_id FROM news_content_blocks WHERE content LIKE :search3
            ))';
            $searchPattern = "%{$search}%";
            $queryParams['search1'] = $searchPattern;
            $queryParams['search2'] = $searchPattern;
            $queryParams['search3'] = $searchPattern;
        }

        if ($categoryId > 0) {
            $conditions[] = 'id IN (SELECT news_article_id FROM news_category WHERE category_id = :category_id)';
            $queryParams['category_id'] = $categoryId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        // Total
        $countSql = "SELECT COUNT(*) FROM news_articles $whereClause";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($queryParams);
        $total = (int) $countStmt->fetchColumn();

        // Artículos
        $sql = "
            SELECT id, type, title, slug, excerpt, featured_image, video_url, is_pinned, published_at, created_at
            FROM news_articles 
            $whereClause
            ORDER BY is_pinned DESC, published_at DESC, created_at DESC
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

        foreach ($articles as &$article) {
            $article['is_pinned'] = (bool) $article['is_pinned'];
            $article['content_blocks'] = $this->getContentBlocks((int) $article['id']);
            $article['categories'] = $this->getArticleCategories((int) $article['id']);
        }

        return $this->json($response, [
            'success' => true,
            'data' => [
                'section_enabled' => true,
                'articles' => $articles,
                'total' => $total,
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
        $article['categories'] = $this->getArticleCategories((int) $article['id']);

        return $this->json($response, [
            'success' => true,
            'data' => $article
        ]);
    }

    // =========================================================================
    // ENDPOINTS ADMIN
    // =========================================================================

    /**
     * GET /api/admin/news/{id}
     * Obtiene el detalle de una noticia por ID (admin - sin filtro is_active)
     */
    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        $stmt = $this->db->prepare("SELECT * FROM news_articles WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $article = $stmt->fetch();

        if (!$article) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Not Found',
                'message' => 'Artículo no encontrado'
            ], 404);
        }

        // Cargar bloques de contenido y categorías
        $article['is_active'] = (bool) $article['is_active'];
        $article['is_pinned'] = (bool) $article['is_pinned'];
        $article['content_blocks'] = $this->getContentBlocks((int) $article['id']);
        $article['categories'] = $this->getArticleCategories((int) $article['id']);

        return $this->json($response, [
            'success' => true,
            'data' => $article
        ]);
    }

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
        $status = $params['status'] ?? null; // 'active', 'draft', o null para todos
        $search = trim($params['search'] ?? '');
        $offset = ($page - 1) * $limit;

        // Construir WHERE dinámico
        $conditions = [];
        $queryParams = [];
        
        if ($type && in_array($type, ['news', 'update'])) {
            $conditions[] = 'type = :type';
            $queryParams['type'] = $type;
        }

        if ($status === 'active') {
            $conditions[] = 'is_active = 1';
        } elseif ($status === 'draft') {
            $conditions[] = 'is_active = 0';
        }

        if ($search) {
            $conditions[] = '(title LIKE :search1 OR excerpt LIKE :search2 OR id IN (
                SELECT news_article_id FROM news_content_blocks WHERE content LIKE :search3
            ))';
            $searchPattern = "%{$search}%";
            $queryParams['search1'] = $searchPattern;
            $queryParams['search2'] = $searchPattern;
            $queryParams['search3'] = $searchPattern;
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Total de registros
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM news_articles $whereClause");
        $countStmt->execute($queryParams);
        $total = (int) $countStmt->fetchColumn();

        // Obtener artículos
        $sql = "
            SELECT 
                id, type, title, slug, excerpt, 
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
            $article['categories'] = $this->getArticleCategories((int) $article['id']);
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
                (type, title, slug, excerpt, featured_image, video_url, is_active, is_pinned, published_at, created_by)
                VALUES 
                (:type, :title, :slug, :excerpt, :featured_image, :video_url, :is_active, :is_pinned, :published_at, :created_by)
            ");

            $stmt->execute([
                'type' => $data['type'] ?? 'news',
                'title' => $data['title'],
                'slug' => $slug,
                'excerpt' => $data['excerpt'] ?? null,
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

            // Insertar categorías si existen
            if (!empty($data['categories']) && is_array($data['categories'])) {
                $this->syncArticleCategories($articleId, $data['categories']);
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
                'type', 'title', 'excerpt', 
                'featured_image', 'video_url', 'is_active', 
                'is_pinned', 'published_at'
            ];
            $updates = [];
            $params = ['id' => $id];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "{$field} = :{$field}";
                    // Convertir booleanos a entero para MySQL
                    if (in_array($field, ['is_active', 'is_pinned'])) {
                        $params[$field] = (int) $data[$field];
                    } else {
                        $params[$field] = $data[$field];
                    }
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
                $stmt = $this->db->prepare("DELETE FROM news_content_blocks WHERE news_article_id = :article_id");
                $stmt->execute(['article_id' => $id]);
                
                // Insertar nuevos bloques
                $this->insertContentBlocks($id, $data['content_blocks']);
            }

            // Actualizar categorías si se proporcionan
            if (isset($data['categories']) && is_array($data['categories'])) {
                $this->syncArticleCategories($id, $data['categories']);
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
            SELECT id, block_type, content, metadata, block_order
            FROM news_content_blocks 
            WHERE news_article_id = :article_id 
            ORDER BY block_order ASC
        ");
        $stmt->execute(['article_id' => $articleId]);
        $blocks = $stmt->fetchAll();
        
        // Transformar campos para compatibilidad con frontend
        foreach ($blocks as &$block) {
            // Renombrar metadata → meta_data para el frontend
            $block['meta_data'] = $block['metadata'] ? json_decode($block['metadata'], true) : null;
            unset($block['metadata']);
            // Renombrar block_order → sort_order para el frontend
            $block['sort_order'] = $block['block_order'];
            unset($block['block_order']);
        }
        
        return $blocks;
    }

    /**
     * Obtiene las categorías de un artículo
     */
    private function getArticleCategories(int $articleId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.name, c.slug, c.color
            FROM category c
            INNER JOIN news_category nc ON c.id = nc.category_id
            WHERE nc.news_article_id = :article_id
            ORDER BY c.name ASC
        ");
        $stmt->execute(['article_id' => $articleId]);
        return $stmt->fetchAll();
    }

    /**
     * Sincroniza las categorías de un artículo (elimina las existentes e inserta las nuevas)
     */
    private function syncArticleCategories(int $articleId, array $categoryIds): void
    {
        // Eliminar categorías existentes
        $stmt = $this->db->prepare("DELETE FROM news_category WHERE news_article_id = :article_id");
        $stmt->execute(['article_id' => $articleId]);

        // Insertar nuevas categorías
        if (!empty($categoryIds)) {
            $stmt = $this->db->prepare("
                INSERT INTO news_category (news_article_id, category_id)
                VALUES (:article_id, :category_id)
            ");

            foreach ($categoryIds as $categoryId) {
                // Aceptar tanto ID directo como objeto con id
                $catId = is_array($categoryId) ? ($categoryId['id'] ?? null) : $categoryId;
                if ($catId) {
                    $stmt->execute([
                        'article_id' => $articleId,
                        'category_id' => (int) $catId
                    ]);
                }
            }
        }
    }

    /**
     * Inserta bloques de contenido para un artículo
     */
    private function insertContentBlocks(int $articleId, array $blocks): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO news_content_blocks 
            (news_article_id, block_type, content, metadata, block_order)
            VALUES 
            (:news_article_id, :block_type, :content, :metadata, :block_order)
        ");

        foreach ($blocks as $index => $block) {
            // Aceptar tanto meta_data (frontend) como metadata
            $metadata = $block['meta_data'] ?? $block['metadata'] ?? null;
            // Aceptar tanto sort_order (frontend) como block_order
            $blockOrder = $block['sort_order'] ?? $block['block_order'] ?? $index;
            
            $stmt->execute([
                'news_article_id' => $articleId,
                'block_type' => $block['block_type'] ?? 'text',
                'content' => $block['content'] ?? '',
                'metadata' => $metadata ? json_encode($metadata) : null,
                'block_order' => $blockOrder
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

    // =========================================================================
    // ENDPOINTS CATEGORÍAS
    // =========================================================================

    /**
     * GET /api/categories
     * Lista categorías activas (público)
     */
    public function listCategories(Request $request, Response $response): Response
    {
        $stmt = $this->db->query("
            SELECT id, name, slug, description, color
            FROM category
            WHERE is_active = 1
            ORDER BY name ASC
        ");
        $categories = $stmt->fetchAll();

        return $this->json($response, [
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * GET /api/admin/categories
     * Lista TODAS las categorías (admin)
     */
    public function listAllCategories(Request $request, Response $response): Response
    {
        $stmt = $this->db->query("
            SELECT id, name, slug, description, color, is_active, created_at
            FROM category
            ORDER BY name ASC
        ");
        $categories = $stmt->fetchAll();

        // Convertir booleanos
        foreach ($categories as &$cat) {
            $cat['is_active'] = (bool) $cat['is_active'];
        }

        return $this->json($response, [
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * POST /api/admin/categories
     * Crear nueva categoría
     */
    public function createCategory(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Validation Error',
                'message' => 'El nombre es requerido'
            ], 422);
        }

        // Generar slug
        $slug = $this->generateCategorySlug($data['name']);

        try {
            $stmt = $this->db->prepare("
                INSERT INTO category (name, slug, description, color, is_active)
                VALUES (:name, :slug, :description, :color, :is_active)
            ");

            $stmt->execute([
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'color' => $data['color'] ?? '#3B82F6',
                'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1
            ]);

            $categoryId = (int) $this->db->lastInsertId();

            return $this->json($response, [
                'success' => true,
                'message' => 'Categoría creada exitosamente',
                'data' => [
                    'id' => $categoryId,
                    'slug' => $slug
                ]
            ], 201);

        } catch (\Exception $e) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Database Error',
                'message' => $_ENV['APP_DEBUG'] === 'true' ? $e->getMessage() : 'Error al crear la categoría'
            ], 500);
        }
    }

    /**
     * PUT /api/admin/categories/{id}
     * Actualizar categoría
     */
    public function updateCategory(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();

        // Verificar que existe
        $stmt = $this->db->prepare("SELECT id FROM category WHERE id = :id");
        $stmt->execute(['id' => $id]);
        if (!$stmt->fetch()) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Not Found',
                'message' => 'Categoría no encontrada'
            ], 404);
        }

        $allowedFields = ['name', 'description', 'color', 'is_active'];
        $updates = [];
        $params = ['id' => $id];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = :{$field}";
                if ($field === 'is_active') {
                    $params[$field] = (int) $data[$field];
                } else {
                    $params[$field] = $data[$field];
                }
            }
        }

        if (empty($updates)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Validation Error',
                'message' => 'No se proporcionaron campos para actualizar'
            ], 422);
        }

        try {
            $sql = "UPDATE category SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $this->json($response, [
                'success' => true,
                'message' => 'Categoría actualizada exitosamente'
            ]);

        } catch (\Exception $e) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Database Error',
                'message' => $_ENV['APP_DEBUG'] === 'true' ? $e->getMessage() : 'Error al actualizar'
            ], 500);
        }
    }

    /**
     * DELETE /api/admin/categories/{id}
     * Eliminar categoría
     */
    public function deleteCategory(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        $stmt = $this->db->prepare("DELETE FROM category WHERE id = :id");
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() === 0) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Not Found',
                'message' => 'Categoría no encontrada'
            ], 404);
        }

        return $this->json($response, [
            'success' => true,
            'message' => 'Categoría eliminada exitosamente'
        ]);
    }

    /**
     * Genera un slug único para categorías
     */
    private function generateCategorySlug(string $name): string
    {
        $slug = mb_strtolower($name, 'UTF-8');
        $slug = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $slug
        );
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s_]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        $baseSlug = $slug;
        $counter = 1;
        while ($this->categorySlugExists($slug)) {
            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }

    /**
     * Verifica si un slug de categoría ya existe
     */
    private function categorySlugExists(string $slug): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM category WHERE slug = :slug");
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

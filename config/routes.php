<?php
/**
 * Definición de Rutas de la API
 * 
 * Endpoints disponibles:
 * 
 * PÚBLICOS:
 *   GET  /api/news           - Lista noticias activas
 *   GET  /api/news/{id}      - Detalle de una noticia
 *   GET  /api/news/settings  - Configuración pública
 * 
 * ADMIN (sin auth por ahora):
 *   GET    /api/admin/news           - Lista TODAS las noticias
 *   POST   /api/admin/news           - Crear noticia
 *   PUT    /api/admin/news/{id}      - Actualizar noticia
 *   DELETE /api/admin/news/{id}      - Eliminar noticia
 *   PATCH  /api/admin/news/{id}/toggle - Activar/desactivar
 *   GET    /api/admin/settings       - Obtener configuración
 *   PUT    /api/admin/settings       - Actualizar configuración
 */

use Dighy\News\Controllers\NewsController;
use Dighy\News\Controllers\SettingsController;
use Dighy\News\Controllers\UploadController;
use Dighy\News\Middleware\CorsMiddleware;
use Slim\Routing\RouteCollectorProxy;

// Middleware CORS global
$app->add(CorsMiddleware::class);

// Preflight OPTIONS para CORS
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

// Health check
$app->get('/health', function ($request, $response) {
    $response->getBody()->write(json_encode([
        'status' => 'ok',
        'service' => 'dighy-news-api',
        'timestamp' => date('c')
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// =============================================================================
// RUTAS PÚBLICAS
// =============================================================================

$app->group('/api', function (RouteCollectorProxy $group) {
    
    // Obtener configuración pública (si la sección está activa)
    $group->get('/news/settings', [SettingsController::class, 'getPublic']);
    
    // Obtener noticias para dashboard (solo activas)
    $group->get('/news', [NewsController::class, 'list']);
    
    // Obtener detalle de noticia
    $group->get('/news/{id}', [NewsController::class, 'get']);
});

// =============================================================================
// RUTAS ADMIN (sin autenticación por ahora - blueprint)
// =============================================================================

$app->group('/api/admin', function (RouteCollectorProxy $group) {
    
    // CRUD Noticias
    $group->get('/news', [NewsController::class, 'listAll']);
    $group->get('/news/{id}', [NewsController::class, 'getById']);
    $group->post('/news', [NewsController::class, 'create']);
    $group->put('/news/{id}', [NewsController::class, 'update']);
    $group->delete('/news/{id}', [NewsController::class, 'delete']);
    $group->patch('/news/{id}/toggle', [NewsController::class, 'toggle']);
    
    // Settings
    $group->get('/settings', [SettingsController::class, 'get']);
    $group->put('/settings', [SettingsController::class, 'update']);
});

// =============================================================================
// SUBIDA DE ARCHIVOS
// =============================================================================

$app->post('/api/upload/image', [UploadController::class, 'upload']);

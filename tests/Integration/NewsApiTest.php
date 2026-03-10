<?php
/**
 * Tests de Integración HTTP para la API de Noticias
 * 
 * Estas pruebas ejecutan requests HTTP reales contra la aplicación Slim.
 * Requieren una base de datos de prueba configurada.
 */

namespace Dighy\News\Tests\Integration;

use Dighy\News\Tests\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use DI\Container;
use Psr\Http\Message\ResponseInterface;

class NewsApiTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Solo ejecutar si hay base de datos de prueba disponible
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped('Base de datos de prueba no disponible');
        }

        $this->app = $this->createApplication();
    }

    /**
     * Verificar si la base de datos de prueba está disponible
     */
    private function isDatabaseAvailable(): bool
    {
        try {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $name = $_ENV['DB_DATABASE'] ?? 'dighy_news_test';
            $user = $_ENV['DB_USERNAME'] ?? 'root';
            $pass = $_ENV['DB_PASSWORD'] ?? '';
            
            $pdo = new \PDO(
                "mysql:host={$host};dbname={$name};charset=utf8mb4",
                $user,
                $pass
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Crear instancia de la aplicación Slim
     */
    private function createApplication(): App
    {
        $container = new Container();
        AppFactory::setContainer($container);
        $app = AppFactory::create();

        // Cargar rutas
        $routes = require __DIR__ . '/../../config/routes.php';
        $routes($app);

        return $app;
    }

    /**
     * Ejecutar una request contra la aplicación
     */
    private function runApp(string $method, string $uri, array $data = []): ResponseInterface
    {
        $request = match($method) {
            'GET' => $this->createGetRequest($uri, $data),
            'POST' => $this->createPostRequest($uri, $data),
            'PUT' => $this->createPutRequest($uri, $data),
            'DELETE' => $this->createDeleteRequest($uri),
            'PATCH' => $this->createPatchRequest($uri, $data),
            default => $this->createGetRequest($uri)
        };

        return $this->app->handle($request);
    }

    // =========================================================================
    // TESTS DE ENDPOINTS PÚBLICOS
    // =========================================================================

    public function testGetNewsListReturns200(): void
    {
        $response = $this->runApp('GET', '/api/news');
        
        $this->assertStatusCode(200, $response);
        $body = $this->assertJsonResponse($response);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('meta', $body);
    }

    public function testGetNewsListWithPagination(): void
    {
        $response = $this->runApp('GET', '/api/news', ['page' => 1, 'limit' => 5]);
        
        $this->assertStatusCode(200, $response);
        $body = $this->assertJsonResponse($response);
        $this->assertEquals(1, $body['meta']['page']);
        $this->assertEquals(5, $body['meta']['limit']);
    }

    public function testGetNewsListWithSearch(): void
    {
        $response = $this->runApp('GET', '/api/news', ['search' => 'test']);
        
        $this->assertStatusCode(200, $response);
        $body = $this->assertJsonResponse($response);
        $this->assertArrayHasKey('data', $body);
    }

    public function testGetNewsListWithCategoryFilter(): void
    {
        $response = $this->runApp('GET', '/api/news', ['category' => 1]);
        
        $this->assertStatusCode(200, $response);
    }

    public function testGetCategoriesReturns200(): void
    {
        $response = $this->runApp('GET', '/api/categories');
        
        $this->assertStatusCode(200, $response);
        $body = $this->assertJsonResponse($response);
        $this->assertArrayHasKey('data', $body);
    }

    public function testGetSettingsReturns200(): void
    {
        $response = $this->runApp('GET', '/api/news/settings');
        
        $this->assertStatusCode(200, $response);
    }

    public function testGetInvalidSlugReturns404(): void
    {
        $response = $this->runApp('GET', '/api/news/slug-que-no-existe-xyz-123');
        
        $this->assertStatusCode(404, $response);
    }

    // =========================================================================
    // TESTS DE ENDPOINTS ADMIN
    // =========================================================================

    public function testAdminListAllNews(): void
    {
        $response = $this->runApp('GET', '/api/admin/news');
        
        $this->assertStatusCode(200, $response);
        $body = $this->assertJsonResponse($response);
        $this->assertPaginatedResponse($body);
    }

    public function testAdminListAllWithFilters(): void
    {
        $response = $this->runApp('GET', '/api/admin/news', [
            'status' => 'active',
            'type' => 'standard',
            'search' => 'test'
        ]);
        
        $this->assertStatusCode(200, $response);
    }

    public function testAdminCreateRequiresFields(): void
    {
        $response = $this->runApp('POST', '/api/admin/news', []);
        
        $this->assertStatusCode(400, $response);
    }

    public function testAdminCreateValidatesType(): void
    {
        $response = $this->runApp('POST', '/api/admin/news', [
            'title' => 'Test',
            'type' => 'invalid'
        ]);
        
        $this->assertStatusCode(400, $response);
    }

    // =========================================================================
    // TESTS DE FLUJO COMPLETO (CRUD)
    // =========================================================================

    public function testFullCrudWorkflow(): void
    {
        // 1. Crear
        $createResponse = $this->runApp('POST', '/api/admin/news', [
            'title' => 'Noticia de Prueba PHPUnit',
            'type' => 'standard',
            'excerpt' => 'Esta es una noticia creada por PHPUnit',
            'is_active' => true
        ]);
        
        $this->assertStatusCode(201, $createResponse);
        $createBody = $this->getJsonBody($createResponse);
        $articleId = $createBody['id'];
        
        // 2. Leer
        $getResponse = $this->runApp('GET', "/api/admin/news/{$articleId}");
        $this->assertStatusCode(200, $getResponse);
        $getBody = $this->getJsonBody($getResponse);
        $this->assertEquals('Noticia de Prueba PHPUnit', $getBody['data']['title']);
        
        // 3. Actualizar
        $updateResponse = $this->runApp('PUT', "/api/admin/news/{$articleId}", [
            'title' => 'Noticia de Prueba Actualizada',
            'type' => 'standard',
            'excerpt' => 'Excerpt actualizado'
        ]);
        $this->assertStatusCode(200, $updateResponse);
        
        // 4. Toggle
        $toggleResponse = $this->runApp('PATCH', "/api/admin/news/{$articleId}/toggle");
        $this->assertStatusCode(200, $toggleResponse);
        
        // 5. Eliminar
        $deleteResponse = $this->runApp('DELETE', "/api/admin/news/{$articleId}");
        $this->assertStatusCode(200, $deleteResponse);
        
        // 6. Verificar eliminación
        $verifyResponse = $this->runApp('GET', "/api/admin/news/{$articleId}");
        $this->assertStatusCode(404, $verifyResponse);
    }

    public function testCategoryCrudWorkflow(): void
    {
        // 1. Crear categoría
        $createResponse = $this->runApp('POST', '/api/admin/categories', [
            'name' => 'Categoría PHPUnit Test',
            'description' => 'Descripción de prueba'
        ]);
        
        $this->assertStatusCode(201, $createResponse);
        $createBody = $this->getJsonBody($createResponse);
        $categoryId = $createBody['id'];
        
        // 2. Listar (admin)
        $listResponse = $this->runApp('GET', '/api/admin/categories');
        $this->assertStatusCode(200, $listResponse);
        
        // 3. Actualizar
        $updateResponse = $this->runApp('PUT', "/api/admin/categories/{$categoryId}", [
            'name' => 'Categoría Actualizada',
            'is_active' => false
        ]);
        $this->assertStatusCode(200, $updateResponse);
        
        // 4. Eliminar
        $deleteResponse = $this->runApp('DELETE', "/api/admin/categories/{$categoryId}");
        $this->assertStatusCode(200, $deleteResponse);
    }
}

<?php
/**
 * Tests Unitarios para NewsController
 * 
 * Prueba la lógica del controlador usando mocks de PDO
 */

namespace Dighy\News\Tests\Unit;

use Dighy\News\Tests\TestCase;
use Dighy\News\Controllers\NewsController;
use PDO;
use PDOStatement;

class NewsControllerTest extends TestCase
{
    private NewsController $controller;
    private PDO $mockPdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockPdo = $this->createMock(PDO::class);
        $this->controller = new NewsController($this->mockPdo);
    }

    /**
     * Helper para crear mock de PDOStatement
     */
    private function createStmtMock(array $methods = []): PDOStatement
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        
        foreach ($methods as $method => $return) {
            $stmt->method($method)->willReturn($return);
        }
        
        return $stmt;
    }

    // =========================================================================
    // TESTS PARA list() - GET /api/news
    // =========================================================================

    public function testListReturnsSuccessStructure(): void
    {
        // Mock settings disabled
        $settingsStmt = $this->createStmtMock(['fetch' => ['section_enabled' => 0]]);
        $this->mockPdo->method('query')->willReturn($settingsStmt);

        $request = $this->createGetRequest('/api/news');
        $response = $this->createResponse();

        $result = $this->controller->list($request, $response);

        $this->assertStatusCode(200, $result);
        $body = $this->assertJsonResponse($result);
        $this->assertArrayHasKey('success', $body);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('data', $body);
    }

    public function testListWithSectionDisabled(): void
    {
        $settingsStmt = $this->createStmtMock(['fetch' => ['section_enabled' => 0]]);
        $this->mockPdo->method('query')->willReturn($settingsStmt);

        $request = $this->createGetRequest('/api/news');
        $response = $this->createResponse();

        $result = $this->controller->list($request, $response);

        $body = $this->getJsonBody($result);
        $this->assertFalse($body['data']['section_enabled']);
    }

    public function testListWithSectionEnabled(): void
    {
        // Settings habilitados
        $settingsStmt = $this->createStmtMock([
            'fetch' => [
                'section_enabled' => 1,
                'max_items_home' => 10,
                'max_featured' => 3
            ]
        ]);
        
        // Count
        $countStmt = $this->createStmtMock(['fetchColumn' => 0]);
        
        // Lista vacía
        $listStmt = $this->createStmtMock(['fetchAll' => []]);
        $listStmt->method('bindValue')->willReturn(true);

        $this->mockPdo->method('query')->willReturn($settingsStmt);
        $this->mockPdo->method('prepare')
            ->willReturnOnConsecutiveCalls($countStmt, $listStmt);

        $request = $this->createGetRequest('/api/news');
        $response = $this->createResponse();

        $result = $this->controller->list($request, $response);

        $body = $this->getJsonBody($result);
        $this->assertTrue($body['data']['section_enabled']);
        $this->assertArrayHasKey('articles', $body['data']);
        $this->assertArrayHasKey('pagination', $body['data']);
    }

    // =========================================================================
    // TESTS PARA get() - GET /api/news/{slug}
    // =========================================================================

    public function testGetReturnsNotFoundForInvalidSlug(): void
    {
        $stmt = $this->createStmtMock(['fetch' => false]);
        $this->mockPdo->method('prepare')->willReturn($stmt);

        $request = $this->createGetRequest('/api/news/slug-inexistente');
        $response = $this->createResponse();

        $result = $this->controller->get($request, $response, ['id' => 'slug-inexistente']);

        $this->assertStatusCode(404, $result);
        $body = $this->assertJsonResponse($result);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('error', $body);
    }

    public function testGetReturnsArticleByNumericId(): void
    {
        $article = [
            'id' => 1,
            'type' => 'news',
            'title' => 'Test Article',
            'slug' => 'test-article',
            'is_active' => 1,
            'is_pinned' => 0
        ];

        $articleStmt = $this->createStmtMock(['fetch' => $article]);
        $blocksStmt = $this->createStmtMock(['fetchAll' => []]);
        $categoriesStmt = $this->createStmtMock(['fetchAll' => []]);

        $this->mockPdo->method('prepare')
            ->willReturnOnConsecutiveCalls($articleStmt, $blocksStmt, $categoriesStmt);

        $request = $this->createGetRequest('/api/news/1');
        $response = $this->createResponse();

        $result = $this->controller->get($request, $response, ['id' => '1']);

        $this->assertStatusCode(200, $result);
        $body = $this->assertJsonResponse($result);
        $this->assertTrue($body['success']);
        $this->assertEquals('Test Article', $body['data']['title']);
    }

    public function testGetReturnsArticleBySlug(): void
    {
        $article = [
            'id' => 1,
            'type' => 'news',
            'title' => 'Test Article',
            'slug' => 'test-article',
            'is_active' => 1,
            'is_pinned' => 0
        ];

        $articleStmt = $this->createStmtMock(['fetch' => $article]);
        $blocksStmt = $this->createStmtMock(['fetchAll' => []]);
        $categoriesStmt = $this->createStmtMock(['fetchAll' => []]);

        $this->mockPdo->method('prepare')
            ->willReturnOnConsecutiveCalls($articleStmt, $blocksStmt, $categoriesStmt);

        $request = $this->createGetRequest('/api/news/test-article');
        $response = $this->createResponse();

        $result = $this->controller->get($request, $response, ['id' => 'test-article']);

        $this->assertStatusCode(200, $result);
        $body = $this->assertJsonResponse($result);
        $this->assertTrue($body['success']);
    }

    // =========================================================================
    // TESTS PARA getById() - GET /api/admin/news/{id}
    // =========================================================================

    public function testGetByIdReturnsNotFound(): void
    {
        $stmt = $this->createStmtMock(['fetch' => false]);
        $this->mockPdo->method('prepare')->willReturn($stmt);

        $request = $this->createGetRequest('/api/admin/news/999');
        $response = $this->createResponse();

        $result = $this->controller->getById($request, $response, ['id' => '999']);

        $this->assertStatusCode(404, $result);
    }

    public function testGetByIdReturnsArticle(): void
    {
        $article = [
            'id' => 1,
            'type' => 'news',
            'title' => 'Admin Article',
            'slug' => 'admin-article',
            'is_active' => 0,
            'is_pinned' => 1
        ];

        $articleStmt = $this->createStmtMock(['fetch' => $article]);
        $blocksStmt = $this->createStmtMock(['fetchAll' => []]);
        $categoriesStmt = $this->createStmtMock(['fetchAll' => []]);

        $this->mockPdo->method('prepare')
            ->willReturnOnConsecutiveCalls($articleStmt, $blocksStmt, $categoriesStmt);

        $request = $this->createGetRequest('/api/admin/news/1');
        $response = $this->createResponse();

        $result = $this->controller->getById($request, $response, ['id' => '1']);

        $this->assertStatusCode(200, $result);
        $body = $this->assertJsonResponse($result);
        $this->assertEquals('Admin Article', $body['data']['title']);
        $this->assertFalse($body['data']['is_active']);
        $this->assertTrue($body['data']['is_pinned']);
    }

    // =========================================================================
    // TESTS PARA listAll() - GET /api/admin/news
    // =========================================================================

    public function testListAllReturnsStructure(): void
    {
        $countStmt = $this->createStmtMock(['fetchColumn' => 0]);
        $listStmt = $this->createStmtMock(['fetchAll' => []]);
        $listStmt->method('bindValue')->willReturn(true);

        $this->mockPdo->method('prepare')
            ->willReturnOnConsecutiveCalls($countStmt, $listStmt);

        $request = $this->createGetRequest('/api/admin/news');
        $response = $this->createResponse();

        $result = $this->controller->listAll($request, $response);

        $this->assertStatusCode(200, $result);
        $body = $this->assertJsonResponse($result);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('articles', $body['data']);
        $this->assertArrayHasKey('pagination', $body['data']);
    }

    public function testListAllWithPagination(): void
    {
        $countStmt = $this->createStmtMock(['fetchColumn' => 25]);
        $listStmt = $this->createStmtMock(['fetchAll' => []]);
        $listStmt->method('bindValue')->willReturn(true);

        $this->mockPdo->method('prepare')
            ->willReturnOnConsecutiveCalls($countStmt, $listStmt);

        $request = $this->createGetRequest('/api/admin/news', ['page' => '2', 'limit' => '5']);
        $response = $this->createResponse();

        $result = $this->controller->listAll($request, $response);

        $body = $this->getJsonBody($result);
        $this->assertEquals(2, $body['data']['pagination']['page']);
        $this->assertEquals(5, $body['data']['pagination']['limit']);
        $this->assertEquals(25, $body['data']['pagination']['total']);
        $this->assertEquals(5, $body['data']['pagination']['pages']);
    }

    // =========================================================================
    // TESTS PARA toggle() - PATCH /api/admin/news/{id}/toggle
    // =========================================================================

    public function testToggleNotFound(): void
    {
        $updateStmt = $this->createMock(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);
        $updateStmt->method('rowCount')->willReturn(0);
        
        $this->mockPdo->method('prepare')->willReturn($updateStmt);

        $request = $this->createPatchRequest('/api/admin/news/999/toggle');
        $response = $this->createResponse();

        $result = $this->controller->toggle($request, $response, ['id' => '999']);

        $this->assertStatusCode(404, $result);
    }

    public function testToggleChangesActiveToInactive(): void
    {
        $updateStmt = $this->createMock(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);
        $updateStmt->method('rowCount')->willReturn(1);
        
        $fetchStmt = $this->createMock(PDOStatement::class);
        $fetchStmt->method('execute')->willReturn(true);
        $fetchStmt->method('fetchColumn')->willReturn(0); // is_active = false

        $this->mockPdo->method('prepare')
            ->willReturnOnConsecutiveCalls($updateStmt, $fetchStmt);

        $request = $this->createPatchRequest('/api/admin/news/1/toggle');
        $response = $this->createResponse();

        $result = $this->controller->toggle($request, $response, ['id' => '1']);

        $this->assertStatusCode(200, $result);
        $body = $this->assertJsonResponse($result);
        $this->assertFalse($body['data']['is_active']);
    }

    public function testToggleChangesInactiveToActive(): void
    {
        $updateStmt = $this->createMock(PDOStatement::class);
        $updateStmt->method('execute')->willReturn(true);
        $updateStmt->method('rowCount')->willReturn(1);
        
        $fetchStmt = $this->createMock(PDOStatement::class);
        $fetchStmt->method('execute')->willReturn(true);
        $fetchStmt->method('fetchColumn')->willReturn(1); // is_active = true

        $this->mockPdo->method('prepare')
            ->willReturnOnConsecutiveCalls($updateStmt, $fetchStmt);

        $request = $this->createPatchRequest('/api/admin/news/1/toggle');
        $response = $this->createResponse();

        $result = $this->controller->toggle($request, $response, ['id' => '1']);

        $body = $this->getJsonBody($result);
        $this->assertTrue($body['data']['is_active']);
    }

    // =========================================================================
    // TESTS PARA delete() - DELETE /api/admin/news/{id}
    // =========================================================================

    public function testDeleteNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);
        
        $this->mockPdo->method('prepare')->willReturn($stmt);

        $request = $this->createDeleteRequest('/api/admin/news/999');
        $response = $this->createResponse();

        $result = $this->controller->delete($request, $response, ['id' => '999']);

        $this->assertStatusCode(404, $result);
    }

    public function testDeleteSuccess(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(1);

        $this->mockPdo->method('prepare')->willReturn($stmt);

        $request = $this->createDeleteRequest('/api/admin/news/1');
        $response = $this->createResponse();

        $result = $this->controller->delete($request, $response, ['id' => '1']);

        $this->assertStatusCode(200, $result);
        $body = $this->assertJsonResponse($result);
        $this->assertTrue($body['success']);
    }

    // =========================================================================
    // TESTS PARA CATEGORÍAS
    // =========================================================================

    public function testListCategoriesReturnsArray(): void
    {
        $categories = [
            ['id' => 1, 'name' => 'General', 'slug' => 'general', 'is_active' => 1]
        ];
        
        $stmt = $this->createStmtMock(['fetchAll' => $categories]);
        $this->mockPdo->method('query')->willReturn($stmt);

        $request = $this->createGetRequest('/api/categories');
        $response = $this->createResponse();

        $result = $this->controller->listCategories($request, $response);

        $this->assertStatusCode(200, $result);
        $body = $this->assertJsonResponse($result);
        $this->assertTrue($body['success']);
        $this->assertCount(1, $body['data']);
    }

    public function testCreateCategoryRequiresName(): void
    {
        $request = $this->createPostRequest('/api/admin/categories', []);
        $response = $this->createResponse();

        $result = $this->controller->createCategory($request, $response);

        $this->assertStatusCode(422, $result); // Validation error
        $body = $this->assertJsonResponse($result);
        $this->assertFalse($body['success']);
    }

    public function testCreateCategorySuccess(): void
    {
        $insertStmt = $this->createStmtMock([]);
        
        $this->mockPdo->method('prepare')->willReturn($insertStmt);
        $this->mockPdo->method('lastInsertId')->willReturn('1');

        $request = $this->createPostRequest('/api/admin/categories', ['name' => 'Nueva Categoría']);
        $response = $this->createResponse();

        $result = $this->controller->createCategory($request, $response);

        $this->assertStatusCode(201, $result);
        $body = $this->assertJsonResponse($result);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('id', $body['data']);
    }

    public function testDeleteCategoryNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);
        
        $this->mockPdo->method('prepare')->willReturn($stmt);

        $request = $this->createDeleteRequest('/api/admin/categories/999');
        $response = $this->createResponse();

        $result = $this->controller->deleteCategory($request, $response, ['id' => '999']);

        $this->assertStatusCode(404, $result);
    }

    public function testDeleteCategorySuccess(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(1);

        $this->mockPdo->method('prepare')->willReturn($stmt);

        $request = $this->createDeleteRequest('/api/admin/categories/1');
        $response = $this->createResponse();

        $result = $this->controller->deleteCategory($request, $response, ['id' => '1']);

        $this->assertStatusCode(200, $result);
    }
}

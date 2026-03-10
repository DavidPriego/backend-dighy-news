<?php
/**
 * TestCase base para pruebas de la API
 */

namespace Dighy\News\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class TestCase extends PHPUnitTestCase
{
    protected ServerRequestFactory $requestFactory;
    protected StreamFactory $streamFactory;
    protected ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestFactory = new ServerRequestFactory();
        $this->streamFactory = new StreamFactory();
        $this->responseFactory = new ResponseFactory();
    }

    /**
     * Crear una request GET
     */
    protected function createGetRequest(string $uri, array $queryParams = []): ServerRequestInterface
    {
        $request = $this->requestFactory->createServerRequest('GET', $uri);
        
        if (!empty($queryParams)) {
            $request = $request->withQueryParams($queryParams);
        }
        
        return $request;
    }

    /**
     * Crear una request POST con JSON body
     */
    protected function createPostRequest(string $uri, array $data = []): ServerRequestInterface
    {
        $body = $this->streamFactory->createStream(json_encode($data));
        
        return $this->requestFactory->createServerRequest('POST', $uri)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body)
            ->withParsedBody($data);
    }

    /**
     * Crear una request PUT con JSON body
     */
    protected function createPutRequest(string $uri, array $data = []): ServerRequestInterface
    {
        $body = $this->streamFactory->createStream(json_encode($data));
        
        return $this->requestFactory->createServerRequest('PUT', $uri)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body)
            ->withParsedBody($data);
    }

    /**
     * Crear una request DELETE
     */
    protected function createDeleteRequest(string $uri): ServerRequestInterface
    {
        return $this->requestFactory->createServerRequest('DELETE', $uri);
    }

    /**
     * Crear una request PATCH con JSON body
     */
    protected function createPatchRequest(string $uri, array $data = []): ServerRequestInterface
    {
        $body = $this->streamFactory->createStream(json_encode($data));
        
        return $this->requestFactory->createServerRequest('PATCH', $uri)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body)
            ->withParsedBody($data);
    }

    /**
     * Crear una Response vacía
     */
    protected function createResponse(): ResponseInterface
    {
        return $this->responseFactory->createResponse();
    }

    /**
     * Obtener el body JSON de una response
     */
    protected function getJsonBody(ResponseInterface $response): array
    {
        $response->getBody()->rewind();
        return json_decode($response->getBody()->getContents(), true) ?? [];
    }

    /**
     * Assert que la response tiene status code esperado
     */
    protected function assertStatusCode(int $expected, ResponseInterface $response): void
    {
        $this->assertEquals(
            $expected,
            $response->getStatusCode(),
            "Expected status code {$expected}, got {$response->getStatusCode()}"
        );
    }

    /**
     * Assert que la response es JSON válido
     */
    protected function assertJsonResponse(ResponseInterface $response): array
    {
        $this->assertStringContainsString(
            'application/json',
            $response->getHeaderLine('Content-Type')
        );
        
        $body = $this->getJsonBody($response);
        $this->assertIsArray($body);
        
        return $body;
    }

    /**
     * Assert estructura de respuesta de lista paginada
     */
    protected function assertPaginatedResponse(array $body): void
    {
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('meta', $body);
        $this->assertArrayHasKey('total', $body['meta']);
        $this->assertArrayHasKey('page', $body['meta']);
        $this->assertArrayHasKey('limit', $body['meta']);
        $this->assertArrayHasKey('pages', $body['meta']);
    }

    /**
     * Assert estructura de artículo de noticia
     */
    protected function assertNewsArticleStructure(array $article): void
    {
        $requiredFields = ['id', 'type', 'title', 'slug'];
        
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $article, "Missing field: {$field}");
        }
    }

    /**
     * Assert estructura de categoría
     */
    protected function assertCategoryStructure(array $category): void
    {
        $requiredFields = ['id', 'name', 'slug'];
        
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $category, "Missing field: {$field}");
        }
    }
}

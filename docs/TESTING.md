# Pruebas Unitarias - Dighy News API

## Índice

1. [Introducción](#1-introducción)
2. [Configuración](#2-configuración)
3. [Estructura de Tests](#3-estructura-de-tests)
4. [Tests Implementados](#4-tests-implementados)
5. [Ejecución de Tests](#5-ejecución-de-tests)
6. [Resultados](#6-resultados)
7. [Cobertura de Código](#7-cobertura-de-código)
8. [Guía para Nuevos Tests](#8-guía-para-nuevos-tests)

---

## 1. Introducción

### 1.1 Framework de Testing

El proyecto utiliza **PHPUnit 10.5** como framework de testing, el estándar de facto para pruebas en PHP.

### 1.2 Estrategia de Testing

Se implementan dos tipos de pruebas:

| Tipo | Ubicación | Propósito |
|------|-----------|-----------|
| **Unitarios** | `tests/Unit/` | Prueban componentes aislados con mocks |
| **Integración** | `tests/Integration/` | Prueban flujos completos con BD real |

### 1.3 Principios Seguidos

- **Aislamiento**: Cada test es independiente
- **Mocking**: Se simulan dependencias externas (PDO)
- **Claridad**: Nombres descriptivos siguiendo `test[Método][Comportamiento]`
- **Cobertura**: Se cubren casos de éxito y error

---

## 2. Configuración

### 2.1 Archivos de Configuración

**phpunit.xml**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         bootstrap="tests/bootstrap.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         executionOrder="depends,defects"
         beStrictAboutOutputDuringTests="true"
         failOnRisky="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_DATABASE" value="dighy_news_test"/>
    </php>
</phpunit>
```

**tests/bootstrap.php**
```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Cargar .env.testing si existe
$dotenvPath = __DIR__ . '/../';
if (file_exists($dotenvPath . '.env.testing')) {
    $dotenv = Dotenv\Dotenv::createImmutable($dotenvPath, '.env.testing');
    $dotenv->load();
}
```

### 2.2 Dependencias

```json
{
  "require-dev": {
    "phpunit/phpunit": "^10.5"
  }
}
```

### 2.3 Base de Datos de Test

Para tests de integración, crear una BD separada:

```bash
# Copiar configuración
cp .env.testing.example .env.testing

# Crear BD de test
mysql -u root -p -e "CREATE DATABASE dighy_news_test"
mysql -u root -p dighy_news_test < database/create_database.sql
```

---

## 3. Estructura de Tests

### 3.1 Clase Base: TestCase

```php
namespace Dighy\News\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\ResponseFactory;

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
}
```

### 3.2 Métodos Helper Disponibles

| Método | Descripción |
|--------|-------------|
| `createGetRequest($uri, $params)` | Crea request GET |
| `createPostRequest($uri, $data)` | Crea request POST con JSON body |
| `createPutRequest($uri, $data)` | Crea request PUT con JSON body |
| `createDeleteRequest($uri)` | Crea request DELETE |
| `createPatchRequest($uri, $data)` | Crea request PATCH |
| `createResponse()` | Crea response vacía |
| `getJsonBody($response)` | Extrae JSON de response |
| `assertStatusCode($code, $response)` | Verifica status code |
| `assertJsonResponse($response)` | Verifica que es JSON válido |

---

## 4. Tests Implementados

### 4.1 NewsControllerTest (20 tests)

Ubicación: `tests/Unit/NewsControllerTest.php`

#### 4.1.1 Tests de list() - GET /api/news

| Test | Descripción | Verifica |
|------|-------------|----------|
| `testListReturnsSuccessStructure` | Estructura de respuesta | `success`, `data` presentes |
| `testListWithSectionDisabled` | Sección deshabilitada | `section_enabled: false` |
| `testListWithSectionEnabled` | Sección habilitada | `articles`, `pagination` presentes |

**Código ejemplo:**
```php
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
}
```

#### 4.1.2 Tests de get() - GET /api/news/{id}

| Test | Descripción | Verifica |
|------|-------------|----------|
| `testGetReturnsNotFoundForInvalidSlug` | Slug inválido | Status 404, `error` presente |
| `testGetReturnsArticleByNumericId` | Buscar por ID | Artículo retornado |
| `testGetReturnsArticleBySlug` | Buscar por slug | Artículo retornado |

**Código ejemplo:**
```php
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
```

#### 4.1.3 Tests de getById() - GET /api/admin/news/{id}

| Test | Descripción | Verifica |
|------|-------------|----------|
| `testGetByIdReturnsNotFound` | ID no existe | Status 404 |
| `testGetByIdReturnsArticle` | ID válido | Artículo con campos booleanos |

#### 4.1.4 Tests de listAll() - GET /api/admin/news

| Test | Descripción | Verifica |
|------|-------------|----------|
| `testListAllReturnsStructure` | Estructura completa | `articles`, `pagination` |
| `testListAllWithPagination` | Paginación correcta | `page`, `limit`, `total`, `pages` |

**Código ejemplo:**
```php
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
```

#### 4.1.5 Tests de toggle() - PATCH /api/admin/news/{id}/toggle

| Test | Descripción | Verifica |
|------|-------------|----------|
| `testToggleNotFound` | ID no existe | Status 404 |
| `testToggleChangesActiveToInactive` | Desactivar | `is_active: false` |
| `testToggleChangesInactiveToActive` | Activar | `is_active: true` |

**Código ejemplo:**
```php
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
```

#### 4.1.6 Tests de delete() - DELETE /api/admin/news/{id}

| Test | Descripción | Verifica |
|------|-------------|----------|
| `testDeleteNotFound` | ID no existe | Status 404 |
| `testDeleteSuccess` | Eliminación exitosa | Status 200, `success: true` |

#### 4.1.7 Tests de Categorías

| Test | Descripción | Verifica |
|------|-------------|----------|
| `testListCategoriesReturnsArray` | Lista categorías | Array de categorías |
| `testCreateCategoryRequiresName` | Validación nombre | Status 422 |
| `testCreateCategorySuccess` | Creación exitosa | Status 201, `id` presente |
| `testDeleteCategoryNotFound` | ID no existe | Status 404 |
| `testDeleteCategorySuccess` | Eliminación exitosa | Status 200 |

---

## 5. Ejecución de Tests

### 5.1 Comandos Disponibles

```bash
# Ejecutar todos los tests
composer test

# Solo tests unitarios
./vendor/bin/phpunit --testsuite Unit

# Solo tests de integración
./vendor/bin/phpunit --testsuite Integration

# Test específico
./vendor/bin/phpunit --filter testListReturnsSuccessStructure

# Con salida verbose
./vendor/bin/phpunit --testsuite Unit -v

# Con cobertura HTML
composer test:coverage
```

### 5.2 Scripts Composer

```json
{
  "scripts": {
    "test": "phpunit",
    "test:coverage": "phpunit --coverage-html coverage"
  }
}
```

---

## 6. Resultados

### 6.1 Última Ejecución

```
PHPUnit 10.5.63 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.6
Configuration: /home/kelga/projects/backend-dighy-news/phpunit.xml

....................                                              20 / 20 (100%)

Time: 00:00.042, Memory: 10.00 MB

OK (20 tests, 70 assertions)
```

### 6.2 Resumen de Resultados

| Métrica | Valor |
|---------|-------|
| Tests ejecutados | 20 |
| Assertions | 70 |
| Tiempo | 0.042s |
| Memoria | 10.00 MB |
| Estado | ✅ PASS |

### 6.3 Distribución por Funcionalidad

| Área | Tests | Estado |
|------|-------|--------|
| list() público | 3 | ✅ |
| get() público | 3 | ✅ |
| getById() admin | 2 | ✅ |
| listAll() admin | 2 | ✅ |
| toggle() | 3 | ✅ |
| delete() | 2 | ✅ |
| Categorías | 5 | ✅ |
| **TOTAL** | **20** | ✅ |

---

## 7. Cobertura de Código

### 7.1 Generar Reporte

```bash
# Requiere Xdebug o PCOV
composer test:coverage

# El reporte se genera en coverage/index.html
```

### 7.2 Cobertura por Componente

| Componente | Estado | Notas |
|------------|--------|-------|
| `NewsController` | ✅ Cubierto | Tests unitarios completos |
| `SettingsController` | ⚠️ Parcial | Pendiente tests específicos |
| `UploadController` | ⚠️ Parcial | Pendiente tests de upload |
| `Connection` | ❌ No cubierto | Singleton, difícil de testear |

### 7.3 Métodos Testeados de NewsController

| Método | Testeado | Tests |
|--------|----------|-------|
| `list()` | ✅ | 3 |
| `get()` | ✅ | 3 |
| `getById()` | ✅ | 2 |
| `listAll()` | ✅ | 2 |
| `create()` | ⚠️ Indirecto | - |
| `update()` | ⚠️ Indirecto | - |
| `delete()` | ✅ | 2 |
| `toggle()` | ✅ | 3 |
| `listCategories()` | ✅ | 1 |
| `createCategory()` | ✅ | 2 |
| `deleteCategory()` | ✅ | 2 |

---

## 8. Guía para Nuevos Tests

### 8.1 Crear un Test Unitario

```php
<?php

namespace Dighy\News\Tests\Unit;

use Dighy\News\Tests\TestCase;
use Dighy\News\Controllers\MiController;
use PDO;
use PDOStatement;

class MiControllerTest extends TestCase
{
    private MiController $controller;
    private PDO $mockPdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockPdo = $this->createMock(PDO::class);
        $this->controller = new MiController($this->mockPdo);
    }

    /**
     * @test
     */
    public function testMiMetodoHaceAlgo(): void
    {
        // Arrange - Preparar mocks
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(['id' => 1, 'name' => 'Test']);
        $this->mockPdo->method('prepare')->willReturn($stmt);

        // Act - Ejecutar acción
        $request = $this->createGetRequest('/api/mi-endpoint');
        $response = $this->createResponse();
        $result = $this->controller->miMetodo($request, $response);

        // Assert - Verificar resultado
        $this->assertStatusCode(200, $result);
        $body = $this->assertJsonResponse($result);
        $this->assertTrue($body['success']);
    }
}
```

### 8.2 Crear un Mock de PDOStatement

```php
private function createStmtMock(array $methods = []): PDOStatement
{
    $stmt = $this->createMock(PDOStatement::class);
    $stmt->method('execute')->willReturn(true);
    
    foreach ($methods as $method => $return) {
        $stmt->method($method)->willReturn($return);
    }
    
    return $stmt;
}
```

### 8.3 Convenciones de Nomenclatura

```
test[Método][ComportamientoEsperado]

Ejemplos:
- testListReturnsEmptyArrayWhenNoData
- testGetReturnsNotFoundForInvalidId
- testCreateRequiresTitle
- testDeleteSuccessfullyRemovesRecord
```

### 8.4 Checklist para Nuevos Tests

- [ ] Nombre descriptivo siguiendo convención
- [ ] Sección Arrange/Act/Assert clara
- [ ] Mock de dependencias externas
- [ ] Casos de éxito y error cubiertos
- [ ] Assertions específicos y relevantes
- [ ] Test aislado (no depende de otros)

---

## Apéndice: Errores Comunes

### A.1 PDO Connection en Constructor

**Problema:** El controlador conecta a BD real en tests.

**Solución:** Inyección de dependencias opcional.

```php
// Antes
public function __construct()
{
    $this->db = Connection::getInstance();
}

// Después
public function __construct(?PDO $pdo = null)
{
    $this->db = $pdo ?? Connection::getInstance();
}
```

### A.2 rowCount() no mockeado

**Problema:** Tests fallan en toggle/delete.

**Solución:** Mockear rowCount() explícitamente.

```php
$stmt = $this->createMock(PDOStatement::class);
$stmt->method('execute')->willReturn(true);
$stmt->method('rowCount')->willReturn(1); // ← Importante
```

### A.3 willReturnOnConsecutiveCalls Order

**Problema:** Mocks retornan en orden incorrecto.

**Solución:** Verificar orden de llamadas a prepare().

```php
// El orden DEBE coincidir con las llamadas en el código
$this->mockPdo->method('prepare')
    ->willReturnOnConsecutiveCalls(
        $countStmt,   // Primera llamada: COUNT
        $listStmt,    // Segunda llamada: SELECT
        $catStmt      // Tercera llamada: categorías
    );
```

# Dighy News API - Documentación Técnica

## Índice

1. [Introducción](#1-introducción)
2. [Arquitectura del Sistema](#2-arquitectura-del-sistema)
3. [Modelo de Datos](#3-modelo-de-datos)
4. [API Reference](#4-api-reference)
5. [Pruebas Unitarias](#5-pruebas-unitarias)
6. [Guía de Desarrollo](#6-guía-de-desarrollo)

---

## 1. Introducción

### 1.1 Descripción General

**Dighy News API** es un microservicio RESTful desarrollado en PHP que gestiona el módulo de noticias y actualizaciones para el Dashboard de Dighy. Proporciona una arquitectura flexible basada en bloques de contenido que permite crear artículos con múltiples tipos de elementos (texto, imágenes, videos, citas, listas, etc.).

### 1.2 Características Principales

- **Sistema de noticias flexible**: Soporte para artículos tipo "news" y "update"
- **Bloques de contenido**: Arquitectura modular para componer artículos
- **Categorización**: Sistema de categorías N:M para organizar contenido
- **Búsqueda avanzada**: Búsqueda en título, excerpt y contenido de bloques
- **Paginación**: Soporte completo de paginación en listados
- **Configuración dinámica**: Settings globales para controlar el módulo

### 1.3 Stack Tecnológico

| Componente | Tecnología | Versión |
|------------|------------|---------|
| Framework | Slim PHP | 4.12+ |
| Lenguaje | PHP | 8.1+ |
| Base de Datos | MySQL | 8.0+ |
| Gestor Dependencias | Composer | 2.x |
| Testing | PHPUnit | 10.5+ |
| HTTP | PSR-7 / PSR-15 | - |

### 1.4 Requisitos del Sistema

```
PHP >= 8.1
  - ext-pdo
  - ext-pdo_mysql
  - ext-mbstring
  - ext-json
  - ext-dom (para tests)

MySQL >= 8.0
Composer >= 2.0
```

---

## 2. Arquitectura del Sistema

### 2.1 Estructura de Directorios

```
backend-dighy-news/
├── config/
│   ├── container.php      # Configuración DI Container
│   └── routes.php         # Definición de rutas API
├── database/
│   └── create_database.sql # Schema completo v3.0
├── docs/                   # Documentación técnica
│   ├── README.md          # Este documento
│   ├── DATABASE.md        # Modelo E-R y diseño BD
│   ├── API.md             # Referencia completa de endpoints
│   └── TESTING.md         # Documentación de pruebas
├── logs/                   # Archivos de log
├── public/
│   ├── index.php          # Entry point
│   └── uploads/           # Archivos subidos
├── src/
│   ├── Controllers/
│   │   ├── NewsController.php     # CRUD noticias y categorías
│   │   ├── SettingsController.php # Configuración
│   │   └── UploadController.php   # Subida de archivos
│   ├── Database/
│   │   └── Connection.php         # Singleton PDO
│   └── Middleware/
│       └── CorsMiddleware.php     # Manejo de CORS
├── tests/
│   ├── bootstrap.php      # Bootstrap PHPUnit
│   ├── TestCase.php       # Clase base de tests
│   ├── Unit/              # Tests unitarios
│   └── Integration/       # Tests de integración
├── vendor/                 # Dependencias (composer)
├── .env                    # Variables de entorno
├── .env.example           # Ejemplo de configuración
├── composer.json          # Dependencias del proyecto
└── phpunit.xml            # Configuración PHPUnit
```

### 2.2 Patrón de Diseño

El proyecto sigue una arquitectura **MVC simplificada** adaptada para APIs REST:

```
┌─────────────────────────────────────────────────────────────────┐
│                         CLIENT REQUEST                           │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                      public/index.php                            │
│                      (Entry Point)                               │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                        Slim Framework                            │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐         │
│  │    CORS     │───▶│   Router    │───▶│ Controller  │         │
│  │ Middleware  │    │config/routes│    │    Layer    │         │
│  └─────────────┘    └─────────────┘    └─────────────┘         │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Database Layer                              │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │              Connection.php (PDO Singleton)              │   │
│  └─────────────────────────────────────────────────────────┘   │
│                              │                                   │
│                              ▼                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    MySQL Database                        │   │
│  │   news_articles │ news_content_blocks │ category │ ...   │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

### 2.3 Flujo de Request/Response

1. **Request entrante** → `public/index.php`
2. **Bootstrap** → Carga autoloader, .env, container
3. **Middleware CORS** → Añade headers de CORS
4. **Router** → Identifica ruta y controlador
5. **Controller** → Ejecuta lógica de negocio
6. **Database** → Consultas PDO
7. **Response JSON** → Formato estandarizado

### 2.4 Formato de Respuesta Estándar

Todas las respuestas siguen un formato consistente:

**Éxito:**
```json
{
  "success": true,
  "data": { ... },
  "message": "Operación exitosa"  // opcional
}
```

**Error:**
```json
{
  "success": false,
  "error": "Error Type",
  "message": "Descripción del error"
}
```

**Listados paginados:**
```json
{
  "success": true,
  "data": {
    "articles": [...],
    "pagination": {
      "page": 1,
      "limit": 10,
      "total": 50,
      "pages": 5
    }
  }
}
```

---

## 3. Modelo de Datos

Ver documentación detallada en [DATABASE.md](DATABASE.md)

### 3.1 Resumen de Tablas

| Tabla | Descripción |
|-------|-------------|
| `news_settings` | Configuración global del módulo |
| `category` | Categorías de noticias |
| `news_articles` | Artículos de noticias/actualizaciones |
| `news_content_blocks` | Bloques de contenido de artículos |
| `news_category` | Relación N:M artículos-categorías |

---

## 4. API Reference

Ver documentación detallada en [API.md](API.md)

### 4.1 Resumen de Endpoints

| Grupo | Endpoints | Descripción |
|-------|-----------|-------------|
| Público | 4 | Lectura de noticias y categorías |
| Admin News | 6 | CRUD completo de noticias |
| Admin Categories | 4 | CRUD de categorías |
| Admin Settings | 2 | Configuración |
| Upload | 1 | Subida de imágenes |
| Health | 1 | Estado del servicio |

---

## 5. Pruebas Unitarias

Ver documentación detallada en [TESTING.md](TESTING.md)

### 5.1 Resumen de Tests

```
Tests: 20
Assertions: 70
Coverage: Controllers (NewsController)
```

---

## 6. Guía de Desarrollo

### 6.1 Instalación Local

```bash
# Clonar repositorio
git clone <repo-url>
cd backend-dighy-news

# Instalar dependencias
composer install

# Configurar entorno
cp .env.example .env
# Editar .env con credenciales de BD

# Crear base de datos
mysql -u root -p < database/create_database.sql

# Iniciar servidor
php -S localhost:8080 -t public
```

### 6.2 Ejecutar Tests

```bash
# Todos los tests
composer test

# Solo unitarios
./vendor/bin/phpunit --testsuite Unit

# Solo integración
./vendor/bin/phpunit --testsuite Integration

# Con coverage
composer test:coverage
```

### 6.3 Integración con Frontend

El backend está diseñado para trabajar con un frontend Vue.js/Nuxt. Configurar CORS en `.env`:

```env
CORS_ALLOWED_ORIGINS=http://localhost:3000
```

---

## Changelog

### v3.0 (Actual)
- Esquema de BD simplificado
- Integración de categorías N:M
- Búsqueda en contenido de bloques
- Paginación completa
- Tests unitarios PHPUnit

### v2.0
- Sistema de bloques de contenido
- Layouts multi-columna

### v1.0
- CRUD básico de noticias
- Sistema de settings

---

## Contacto

**Equipo de Desarrollo Dighy**  
Para soporte técnico, crear un issue en el repositorio.

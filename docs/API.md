# API Reference - Dighy News API

## Índice

1. [Información General](#1-información-general)
2. [Autenticación](#2-autenticación)
3. [Endpoints Públicos](#3-endpoints-públicos)
4. [Endpoints Admin - Noticias](#4-endpoints-admin---noticias)
5. [Endpoints Admin - Categorías](#5-endpoints-admin---categorías)
6. [Endpoints Admin - Settings](#6-endpoints-admin---settings)
7. [Upload de Archivos](#7-upload-de-archivos)
8. [Códigos de Error](#8-códigos-de-error)

---

## 1. Información General

### 1.1 Base URL

```
Desarrollo: http://localhost:8080
Producción: https://api.dighy.com/news
```

### 1.2 Headers Requeridos

```http
Content-Type: application/json
Accept: application/json
```

### 1.3 Formato de Respuesta

Todas las respuestas son JSON con la siguiente estructura:

**Éxito:**
```json
{
  "success": true,
  "data": { ... },
  "message": "Mensaje opcional"
}
```

**Error:**
```json
{
  "success": false,
  "error": "Tipo de Error",
  "message": "Descripción detallada"
}
```

### 1.4 Paginación

Los endpoints de listado soportan paginación:

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `page` | int | 1 | Número de página |
| `limit` | int | 10-20 | Elementos por página |

**Respuesta paginada:**
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

## 2. Autenticación

> ⚠️ **Nota:** Actualmente la API no implementa autenticación. Los endpoints admin están abiertos para desarrollo. En producción, se debe implementar autenticación JWT o similar.

---

## 3. Endpoints Públicos

### 3.1 Health Check

Verifica el estado del servicio.

```http
GET /health
```

**Response 200:**
```json
{
  "status": "ok",
  "service": "dighy-news-api",
  "timestamp": "2026-03-10T15:30:00+01:00"
}
```

---

### 3.2 Listar Noticias (Público)

Obtiene las noticias activas y publicadas para el dashboard.

```http
GET /api/news
```

**Query Parameters:**

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `page` | int | 1 | Página actual |
| `limit` | int | max_items_home | Elementos por página (máx 50) |
| `search` | string | - | Buscar en título, excerpt y contenido |
| `category` | int | - | Filtrar por ID de categoría |

**Ejemplos:**
```bash
# Básico
GET /api/news

# Con paginación
GET /api/news?page=2&limit=5

# Con búsqueda
GET /api/news?search=hidrógeno

# Filtrar por categoría
GET /api/news?category=2

# Combinado
GET /api/news?search=proyecto&category=4&page=1&limit=10
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "section_enabled": true,
    "articles": [
      {
        "id": 1,
        "type": "news",
        "title": "Dighy lanza nuevo proyecto",
        "slug": "dighy-lanza-nuevo-proyecto",
        "excerpt": "Resumen de la noticia...",
        "featured_image": "https://...",
        "video_url": null,
        "is_pinned": true,
        "published_at": "2026-03-10T10:00:00",
        "created_at": "2026-03-10T09:00:00",
        "content_blocks": [
          {
            "id": 1,
            "block_order": 1,
            "block_type": "text",
            "content": "Contenido...",
            "metadata": {"classes": "text-lg"}
          }
        ],
        "categories": [
          {"id": 2, "name": "Hidrógeno Verde", "slug": "hidrogeno-verde"}
        ]
      }
    ],
    "total": 15,
    "pagination": {
      "page": 1,
      "limit": 10,
      "total": 15,
      "pages": 2
    }
  }
}
```

**Response 200 (sección deshabilitada):**
```json
{
  "success": true,
  "data": {
    "section_enabled": false,
    "articles": [],
    "total": 0
  }
}
```

---

### 3.3 Obtener Noticia (Público)

Obtiene el detalle de una noticia activa por ID o slug.

```http
GET /api/news/{id}
```

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `id` | int/string | ID numérico o slug de la noticia |

**Ejemplos:**
```bash
GET /api/news/1
GET /api/news/dighy-lanza-nuevo-proyecto
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "type": "news",
    "title": "Dighy lanza nuevo proyecto",
    "slug": "dighy-lanza-nuevo-proyecto",
    "excerpt": "Resumen...",
    "featured_image": "https://...",
    "video_url": null,
    "is_pinned": true,
    "is_active": true,
    "published_at": "2026-03-10T10:00:00",
    "created_at": "2026-03-10T09:00:00",
    "updated_at": null,
    "content_blocks": [
      {
        "id": 1,
        "block_order": 1,
        "block_type": "text",
        "content": "Párrafo de texto...",
        "metadata": null
      },
      {
        "id": 2,
        "block_order": 2,
        "block_type": "image",
        "content": "https://...",
        "metadata": {"alt": "Descripción", "caption": "Pie de foto"}
      }
    ],
    "categories": [
      {"id": 2, "name": "Hidrógeno Verde", "slug": "hidrogeno-verde"}
    ]
  }
}
```

**Response 404:**
```json
{
  "success": false,
  "error": "Not Found",
  "message": "Artículo no encontrado"
}
```

---

### 3.4 Obtener Settings Públicos

Obtiene la configuración pública de la sección de noticias.

```http
GET /api/news/settings
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "section_enabled": true,
    "max_items_home": 5,
    "allow_videos": true,
    "allow_images": true
  }
}
```

---

### 3.5 Listar Categorías (Público)

Obtiene las categorías activas.

```http
GET /api/categories
```

**Response 200:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Tecnología",
      "slug": "tecnologia",
      "description": "Noticias sobre tecnología",
      "color": "#3B82F6"
    },
    {
      "id": 2,
      "name": "Hidrógeno Verde",
      "slug": "hidrogeno-verde",
      "description": "Avances en hidrógeno verde",
      "color": "#3B82F6"
    }
  ]
}
```

---

## 4. Endpoints Admin - Noticias

### 4.1 Listar Todas las Noticias (Admin)

Lista todas las noticias incluyendo borradores e inactivas.

```http
GET /api/admin/news
```

**Query Parameters:**

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `page` | int | 1 | Página actual |
| `limit` | int | 20 | Elementos por página (máx 100) |
| `status` | string | - | Filtrar: 'active', 'draft' |
| `type` | string | - | Filtrar: 'news', 'update' |
| `search` | string | - | Buscar en título, excerpt y contenido |

**Ejemplos:**
```bash
# Todos
GET /api/admin/news

# Solo borradores
GET /api/admin/news?status=draft

# Solo actualizaciones
GET /api/admin/news?type=update

# Búsqueda
GET /api/admin/news?search=hidrógeno&status=active
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "articles": [
      {
        "id": 1,
        "type": "news",
        "title": "Mi Noticia",
        "slug": "mi-noticia",
        "excerpt": "...",
        "featured_image": null,
        "video_url": null,
        "is_active": true,
        "is_pinned": false,
        "published_at": "2026-03-10T10:00:00",
        "created_by": 1,
        "created_at": "2026-03-10T09:00:00",
        "updated_at": null,
        "categories": []
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 50,
      "pages": 3
    }
  }
}
```

---

### 4.2 Obtener Noticia por ID (Admin)

Obtiene cualquier noticia por ID, incluyendo borradores.

```http
GET /api/admin/news/{id}
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "type": "news",
    "title": "Mi Noticia",
    "slug": "mi-noticia",
    "excerpt": "...",
    "featured_image": null,
    "video_url": null,
    "is_active": false,
    "is_pinned": false,
    "published_at": null,
    "created_by": 1,
    "created_at": "2026-03-10T09:00:00",
    "updated_at": null,
    "content_blocks": [...],
    "categories": [...]
  }
}
```

---

### 4.3 Crear Noticia

Crea una nueva noticia.

```http
POST /api/admin/news
```

**Body:**
```json
{
  "type": "news",
  "title": "Título de la noticia",
  "excerpt": "Resumen opcional",
  "featured_image": "https://...",
  "video_url": "https://youtube.com/embed/...",
  "is_pinned": false,
  "is_active": true,
  "published_at": "2026-03-10T10:00:00",
  "content_blocks": [
    {
      "block_type": "text",
      "content": "Primer párrafo...",
      "metadata": {"classes": "text-lg"}
    },
    {
      "block_type": "image",
      "content": "https://...",
      "metadata": {"alt": "Descripción", "caption": "Pie de foto"}
    }
  ],
  "categories": [1, 2, 4]
}
```

**Campos requeridos:**
- `type` - "news" o "update"
- `title` - Título del artículo

**Campos opcionales:**
- `excerpt` - Resumen
- `featured_image` - URL imagen principal
- `video_url` - URL video embed
- `is_pinned` - boolean (default: false)
- `is_active` - boolean (default: false)
- `published_at` - datetime (auto si is_active)
- `content_blocks` - array de bloques
- `categories` - array de IDs de categorías

**Response 201:**
```json
{
  "success": true,
  "message": "Artículo creado exitosamente",
  "data": {
    "id": 10,
    "slug": "titulo-de-la-noticia"
  }
}
```

**Response 422 (Validación):**
```json
{
  "success": false,
  "error": "Validation Error",
  "message": "El título es requerido"
}
```

**Response 422 (Tipo inválido):**
```json
{
  "success": false,
  "error": "Validation Error",
  "message": "El tipo debe ser 'news' o 'update'"
}
```

---

### 4.4 Actualizar Noticia

Actualiza una noticia existente.

```http
PUT /api/admin/news/{id}
```

**Body:**
```json
{
  "title": "Título actualizado",
  "excerpt": "Nuevo resumen",
  "is_active": true,
  "content_blocks": [...],
  "categories": [1, 3]
}
```

> ⚠️ Si se envía `content_blocks`, se reemplazan TODOS los bloques existentes.

> ⚠️ Si se envía `categories`, se resincronizan TODAS las categorías.

**Response 200:**
```json
{
  "success": true,
  "message": "Artículo actualizado exitosamente"
}
```

**Response 404:**
```json
{
  "success": false,
  "error": "Not Found",
  "message": "Artículo no encontrado"
}
```

---

### 4.5 Eliminar Noticia

Elimina una noticia y sus bloques de contenido asociados.

```http
DELETE /api/admin/news/{id}
```

**Response 200:**
```json
{
  "success": true,
  "message": "Artículo eliminado exitosamente"
}
```

**Response 404:**
```json
{
  "success": false,
  "error": "Not Found",
  "message": "Artículo no encontrado"
}
```

---

### 4.6 Toggle Estado (Activar/Desactivar)

Cambia el estado is_active de una noticia.

```http
PATCH /api/admin/news/{id}/toggle
```

**Response 200:**
```json
{
  "success": true,
  "message": "Artículo activado",
  "data": {
    "is_active": true
  }
}
```

```json
{
  "success": true,
  "message": "Artículo desactivado",
  "data": {
    "is_active": false
  }
}
```

---

## 5. Endpoints Admin - Categorías

### 5.1 Listar Todas las Categorías (Admin)

```http
GET /api/admin/categories
```

**Response 200:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Tecnología",
      "slug": "tecnologia",
      "description": "...",
      "color": "#3B82F6",
      "is_active": true,
      "created_at": "2026-03-10T09:00:00"
    }
  ]
}
```

---

### 5.2 Crear Categoría

```http
POST /api/admin/categories
```

**Body:**
```json
{
  "name": "Nueva Categoría",
  "description": "Descripción opcional",
  "color": "#FF5733",
  "is_active": true
}
```

**Campos requeridos:**
- `name` - Nombre de la categoría

**Response 201:**
```json
{
  "success": true,
  "message": "Categoría creada exitosamente",
  "data": {
    "id": 8,
    "slug": "nueva-categoria"
  }
}
```

---

### 5.3 Actualizar Categoría

```http
PUT /api/admin/categories/{id}
```

**Body:**
```json
{
  "name": "Nombre actualizado",
  "description": "Nueva descripción",
  "color": "#00FF00",
  "is_active": false
}
```

**Response 200:**
```json
{
  "success": true,
  "message": "Categoría actualizada exitosamente"
}
```

---

### 5.4 Eliminar Categoría

```http
DELETE /api/admin/categories/{id}
```

> ⚠️ Se elimina la relación con artículos pero NO los artículos.

**Response 200:**
```json
{
  "success": true,
  "message": "Categoría eliminada exitosamente"
}
```

---

## 6. Endpoints Admin - Settings

### 6.1 Obtener Configuración

```http
GET /api/admin/settings
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "section_enabled": true,
    "max_items_home": 5,
    "allow_videos": true,
    "allow_images": true,
    "updated_by": null,
    "updated_at": "2026-03-10T15:00:00"
  }
}
```

---

### 6.2 Actualizar Configuración

```http
PUT /api/admin/settings
```

**Body:**
```json
{
  "section_enabled": true,
  "max_items_home": 10,
  "allow_videos": false,
  "allow_images": true
}
```

**Response 200:**
```json
{
  "success": true,
  "message": "Configuración actualizada exitosamente"
}
```

---

## 7. Upload de Archivos

### 7.1 Subir Imagen

```http
POST /api/upload/image
```

**Content-Type:** `multipart/form-data`

**Form Fields:**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `image` | file | Sí | Archivo de imagen |

**Tipos permitidos:** JPEG, PNG, GIF, WebP  
**Tamaño máximo:** 5 MB

**Ejemplo con cURL:**
```bash
curl -X POST http://localhost:8080/api/upload/image \
  -F "image=@/path/to/image.jpg"
```

**Response 200:**
```json
{
  "success": true,
  "message": "Imagen subida exitosamente",
  "data": {
    "url": "/uploads/image_1710000000_abc123.jpg",
    "filename": "image_1710000000_abc123.jpg"
  }
}
```

**Response 400:**
```json
{
  "success": false,
  "error": "Upload Error",
  "message": "No se recibió ninguna imagen"
}
```

**Response 422:**
```json
{
  "success": false,
  "error": "Validation Error",
  "message": "Tipo de archivo no permitido"
}
```

---

## 8. Códigos de Error

### 8.1 Códigos HTTP

| Código | Significado | Uso |
|--------|-------------|-----|
| 200 | OK | Operación exitosa |
| 201 | Created | Recurso creado |
| 400 | Bad Request | Request malformada |
| 404 | Not Found | Recurso no encontrado |
| 422 | Unprocessable Entity | Error de validación |
| 500 | Internal Server Error | Error del servidor |

### 8.2 Tipos de Error

| Error | Descripción |
|-------|-------------|
| `Not Found` | El recurso solicitado no existe |
| `Validation Error` | Los datos enviados no son válidos |
| `Upload Error` | Error en la subida de archivos |
| `Database Error` | Error de base de datos |

---

## Ejemplos con cURL

### Crear noticia completa

```bash
curl -X POST http://localhost:8080/api/admin/news \
  -H "Content-Type: application/json" \
  -d '{
    "type": "news",
    "title": "Nueva Noticia de Prueba",
    "excerpt": "Este es el resumen de la noticia",
    "is_active": true,
    "content_blocks": [
      {
        "block_type": "text",
        "content": "Este es el primer párrafo de la noticia.",
        "metadata": {"classes": "text-lg font-medium"}
      },
      {
        "block_type": "image",
        "content": "https://example.com/image.jpg",
        "metadata": {"alt": "Imagen descriptiva", "caption": "Pie de foto"}
      },
      {
        "block_type": "quote",
        "content": "Esta es una cita importante",
        "metadata": {"author": "Juan Pérez"}
      }
    ],
    "categories": [1, 2]
  }'
```

### Buscar noticias

```bash
curl "http://localhost:8080/api/news?search=hidrógeno&category=2&page=1&limit=5"
```

### Actualizar configuración

```bash
curl -X PUT http://localhost:8080/api/admin/settings \
  -H "Content-Type: application/json" \
  -d '{
    "section_enabled": true,
    "max_items_home": 10,
    "allow_videos": true,
    "allow_images": true
  }'
```

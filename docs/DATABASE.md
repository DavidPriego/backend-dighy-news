# Modelo de Base de Datos - Dighy News API

## Índice

1. [Diagrama Entidad-Relación](#1-diagrama-entidad-relación)
2. [Descripción de Tablas](#2-descripción-de-tablas)
3. [Relaciones](#3-relaciones)
4. [Índices y Optimización](#4-índices-y-optimización)
5. [Datos de Ejemplo](#5-datos-de-ejemplo)

---

## 1. Diagrama Entidad-Relación

### 1.1 Diagrama Completo

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          DIGHY NEWS - MODELO E-R                             │
└─────────────────────────────────────────────────────────────────────────────┘

┌───────────────────┐
│   news_settings   │
├───────────────────┤
│ PK id             │
│    section_enabled│
│    max_items_home │
│    allow_videos   │
│    allow_images   │
│    updated_by     │
│    created_at     │
│    updated_at     │
└───────────────────┘


┌───────────────────┐       ┌───────────────────┐       ┌───────────────────┐
│     category      │       │   news_category   │       │   news_articles   │
├───────────────────┤       ├───────────────────┤       ├───────────────────┤
│ PK id             │◄──────│ FK category_id    │───────│ PK id             │
│    name           │       │ FK news_article_id│──────►│    type           │
│    slug           │       │    created_at     │       │    title          │
│    description    │       └───────────────────┘       │    slug           │
│    color          │              N : M                │    excerpt        │
│    is_active      │                                   │    featured_image │
│    created_at     │                                   │    video_url      │
└───────────────────┘                                   │    is_pinned      │
                                                        │    is_active      │
                                                        │    published_at   │
                                                        │    created_by     │
                                                        │    created_at     │
                                                        │    updated_at     │
                                                        └─────────┬─────────┘
                                                                  │
                                                                  │ 1 : N
                                                                  │
                                                        ┌─────────▼─────────┐
                                                        │news_content_blocks│
                                                        ├───────────────────┤
                                                        │ PK id             │
                                                        │ FK news_article_id│
                                                        │    block_order    │
                                                        │    block_type     │
                                                        │    content        │
                                                        │    metadata (JSON)│
                                                        │    created_at     │
                                                        │    updated_at     │
                                                        └───────────────────┘
```

### 1.2 Relaciones Resumidas

| Relación | Tipo | Descripción |
|----------|------|-------------|
| `news_articles` ↔ `category` | N:M | Un artículo puede tener múltiples categorías |
| `news_articles` → `news_content_blocks` | 1:N | Un artículo tiene múltiples bloques |
| `news_settings` | Singleton | Una única fila de configuración |

---

## 2. Descripción de Tablas

### 2.1 news_settings

**Propósito:** Almacena la configuración global del módulo de noticias. Es una tabla singleton (una única fila).

| Campo | Tipo | Null | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | INT | NO | AUTO | Primary Key |
| `section_enabled` | BOOLEAN | NO | TRUE | Si la sección está activa |
| `max_items_home` | INT | NO | 5 | Máximo de noticias en home |
| `allow_videos` | BOOLEAN | NO | TRUE | Permitir videos en artículos |
| `allow_images` | BOOLEAN | NO | TRUE | Permitir imágenes en artículos |
| `updated_by` | INT | SÍ | NULL | ID del usuario que actualizó |
| `created_at` | TIMESTAMP | NO | CURRENT | Fecha de creación |
| `updated_at` | TIMESTAMP | NO | ON UPDATE | Fecha de última modificación |

**SQL:**
```sql
CREATE TABLE news_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    max_items_home INT NOT NULL DEFAULT 5,
    allow_videos BOOLEAN NOT NULL DEFAULT TRUE,
    allow_images BOOLEAN NOT NULL DEFAULT TRUE,
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

---

### 2.2 category

**Propósito:** Define las categorías disponibles para clasificar noticias.

| Campo | Tipo | Null | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | INT | NO | AUTO | Primary Key |
| `name` | VARCHAR(100) | NO | - | Nombre de la categoría (único) |
| `slug` | VARCHAR(100) | NO | - | URL amigable (único) |
| `description` | VARCHAR(255) | SÍ | NULL | Descripción opcional |
| `color` | VARCHAR(7) | SÍ | #3B82F6 | Color hexadecimal para UI |
| `is_active` | BOOLEAN | NO | TRUE | Si está activa |
| `created_at` | TIMESTAMP | NO | CURRENT | Fecha de creación |

**SQL:**
```sql
CREATE TABLE category (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    color VARCHAR(7) DEFAULT '#3B82F6',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

**Categorías predefinidas:**

| ID | Nombre | Slug | Color |
|----|--------|------|-------|
| 1 | Tecnología | tecnologia | #3B82F6 |
| 2 | Hidrógeno Verde | hidrogeno-verde | #3B82F6 |
| 3 | Energías Renovables | energias-renovables | #EAB308 |
| 4 | Proyectos | proyectos | #8B5CF6 |
| 5 | Normativa | normativa | #EF4444 |
| 6 | Eventos | eventos | #EC4899 |
| 7 | Empresa | empresa | #06B6D4 |

---

### 2.3 news_articles

**Propósito:** Almacena los artículos de noticias y actualizaciones.

| Campo | Tipo | Null | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | INT | NO | AUTO | Primary Key |
| `type` | ENUM | NO | 'news' | Tipo: 'news' o 'update' |
| `title` | VARCHAR(255) | NO | - | Título del artículo |
| `slug` | VARCHAR(255) | NO | - | URL amigable (único) |
| `excerpt` | TEXT | SÍ | NULL | Resumen/descripción corta |
| `featured_image` | VARCHAR(500) | SÍ | NULL | URL imagen principal |
| `video_url` | VARCHAR(500) | SÍ | NULL | URL de video embed |
| `is_pinned` | BOOLEAN | NO | FALSE | Noticia destacada/fijada |
| `is_active` | BOOLEAN | NO | FALSE | Publicada/visible |
| `published_at` | DATETIME | SÍ | NULL | Fecha de publicación |
| `created_by` | INT | SÍ | NULL | ID usuario creador |
| `created_at` | TIMESTAMP | NO | CURRENT | Fecha de creación |
| `updated_at` | TIMESTAMP | NO | ON UPDATE | Última modificación |

**Índices:**
- `idx_type` - Búsqueda por tipo
- `idx_active` - Filtro por estado y fecha
- `idx_pinned` - Noticias destacadas
- `idx_slug` - Búsqueda por slug

**SQL:**
```sql
CREATE TABLE news_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('news', 'update') NOT NULL DEFAULT 'news',
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    excerpt TEXT DEFAULT NULL,
    featured_image VARCHAR(500) DEFAULT NULL,
    video_url VARCHAR(500) DEFAULT NULL,
    is_pinned BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT FALSE,
    published_at DATETIME DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_type (type),
    INDEX idx_active (is_active, published_at),
    INDEX idx_pinned (is_pinned),
    INDEX idx_slug (slug)
) ENGINE=InnoDB;
```

---

### 2.4 news_content_blocks

**Propósito:** Almacena los bloques de contenido que componen cada artículo.

| Campo | Tipo | Null | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | INT | NO | AUTO | Primary Key |
| `news_article_id` | INT | NO | - | FK a news_articles |
| `block_order` | INT | NO | 0 | Orden de visualización |
| `block_type` | ENUM | NO | 'text' | Tipo de bloque |
| `content` | TEXT | SÍ | NULL | Contenido del bloque |
| `metadata` | JSON | SÍ | NULL | Datos adicionales |
| `created_at` | TIMESTAMP | NO | CURRENT | Fecha de creación |
| `updated_at` | TIMESTAMP | NO | ON UPDATE | Última modificación |

**Tipos de bloque disponibles:**

| Tipo | Descripción | Ejemplo de metadata |
|------|-------------|---------------------|
| `text` | Párrafo de texto | `{"classes": "text-lg"}` |
| `image` | Imagen | `{"alt": "...", "caption": "..."}` |
| `video` | Video embebido | `{"provider": "youtube", "autoplay": false}` |
| `heading` | Título/subtítulo | `{"level": 2}` |
| `quote` | Cita | `{"author": "Nombre"}` |
| `list` | Lista | `{"style": "bullet"}` |
| `divider` | Separador | `{}` |
| `embed` | Contenido embebido | `{"type": "twitter"}` |
| `file` | Archivo descargable | `{"filename": "...", "size": "..."}` |
| `link` | Enlace | `{"url": "...", "target": "_blank"}` |

**SQL:**
```sql
CREATE TABLE news_content_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    news_article_id INT NOT NULL,
    block_order INT NOT NULL DEFAULT 0,
    block_type ENUM('text', 'image', 'video', 'heading', 'quote', 
                    'list', 'divider', 'embed', 'file', 'link') NOT NULL DEFAULT 'text',
    content TEXT DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_article_order (news_article_id, block_order),
    FOREIGN KEY (news_article_id) REFERENCES news_articles(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

---

### 2.5 news_category

**Propósito:** Tabla pivote para la relación muchos a muchos entre artículos y categorías.

| Campo | Tipo | Null | Default | Descripción |
|-------|------|------|---------|-------------|
| `news_article_id` | INT | NO | - | FK a news_articles |
| `category_id` | INT | NO | - | FK a category |
| `created_at` | TIMESTAMP | NO | CURRENT | Fecha de asociación |

**Clave primaria compuesta:** (`news_article_id`, `category_id`)

**SQL:**
```sql
CREATE TABLE news_category (
    news_article_id INT NOT NULL,
    category_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (news_article_id, category_id),
    FOREIGN KEY (news_article_id) REFERENCES news_articles(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES category(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

---

## 3. Relaciones

### 3.1 Artículos ↔ Categorías (N:M)

Un artículo puede tener múltiples categorías y una categoría puede estar asignada a múltiples artículos.

```sql
-- Asignar categorías a un artículo
INSERT INTO news_category (news_article_id, category_id) VALUES
    (1, 2),  -- Artículo 1 en categoría "Hidrógeno Verde"
    (1, 4);  -- Artículo 1 en categoría "Proyectos"

-- Obtener categorías de un artículo
SELECT c.* FROM category c
JOIN news_category nc ON c.id = nc.category_id
WHERE nc.news_article_id = 1 AND c.is_active = 1;
```

### 3.2 Artículos → Bloques (1:N)

Un artículo tiene múltiples bloques de contenido ordenados.

```sql
-- Obtener bloques de un artículo
SELECT * FROM news_content_blocks 
WHERE news_article_id = 1 
ORDER BY block_order ASC;

-- Al eliminar un artículo, sus bloques se eliminan automáticamente (CASCADE)
DELETE FROM news_articles WHERE id = 1;
```

---

## 4. Índices y Optimización

### 4.1 Índices Definidos

| Tabla | Índice | Columnas | Propósito |
|-------|--------|----------|-----------|
| `news_articles` | `idx_type` | `type` | Filtrar por tipo |
| `news_articles` | `idx_active` | `is_active, published_at` | Listado público |
| `news_articles` | `idx_pinned` | `is_pinned` | Noticias destacadas |
| `news_articles` | `idx_slug` | `slug` | Búsqueda por URL |
| `news_content_blocks` | `idx_article_order` | `news_article_id, block_order` | Orden de bloques |

### 4.2 Consultas Optimizadas

**Listado público eficiente:**
```sql
SELECT id, type, title, slug, excerpt, featured_image, is_pinned, published_at
FROM news_articles 
WHERE is_active = 1 
  AND (published_at IS NULL OR published_at <= NOW())
ORDER BY is_pinned DESC, published_at DESC
LIMIT 10 OFFSET 0;
```

**Búsqueda en contenido:**
```sql
SELECT * FROM news_articles 
WHERE is_active = 1
  AND (
    title LIKE '%término%' 
    OR excerpt LIKE '%término%' 
    OR id IN (
      SELECT news_article_id FROM news_content_blocks 
      WHERE content LIKE '%término%'
    )
  );
```

---

## 5. Datos de Ejemplo

### 5.1 Artículo Completo

```sql
-- Crear artículo
INSERT INTO news_articles (type, title, slug, excerpt, featured_image, is_active, published_at)
VALUES ('news', 'Mi Noticia', 'mi-noticia', 'Resumen...', 'https://...', TRUE, NOW());

SET @article_id = LAST_INSERT_ID();

-- Asignar categorías
INSERT INTO news_category (news_article_id, category_id) VALUES
    (@article_id, 1),
    (@article_id, 2);

-- Agregar bloques de contenido
INSERT INTO news_content_blocks (news_article_id, block_order, block_type, content, metadata) VALUES
    (@article_id, 1, 'text', 'Primer párrafo...', '{"classes": "text-lg"}'),
    (@article_id, 2, 'image', 'https://...', '{"alt": "Imagen", "caption": "Pie de foto"}'),
    (@article_id, 3, 'quote', 'Cita importante', '{"author": "Juan Pérez"}'),
    (@article_id, 4, 'text', 'Conclusión...', NULL);
```

### 5.2 Vista news_articles_full

El schema incluye una vista que agrega información:

```sql
CREATE VIEW news_articles_full AS
SELECT 
    a.*,
    (SELECT JSON_ARRAYAGG(JSON_OBJECT('id', cat.id, 'name', cat.name, 'slug', cat.slug, 'color', cat.color))
     FROM news_category nc
     JOIN category cat ON nc.category_id = cat.id
     WHERE nc.news_article_id = a.id) AS categories,
    (SELECT COUNT(*) FROM news_content_blocks cb WHERE cb.news_article_id = a.id) AS content_blocks_count
FROM news_articles a;
```

---

## Historial de Versiones

| Versión | Fecha | Cambios |
|---------|-------|---------|
| 3.0 | 2026-03 | Eliminado layout/column_position, añadida tabla news_category |
| 2.0 | 2026-02 | Sistema de bloques de contenido |
| 1.0 | 2026-01 | Schema inicial |

-- ============================================
-- DIGHY NEWS SYSTEM - Database Schema v3.0
-- Adaptado para integración con Frontend Vue/Nuxt
-- ============================================

DROP DATABASE IF EXISTS dighy_news;
CREATE DATABASE dighy_news CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dighy_news;

-- ============================================
-- TABLA: news_settings
-- Configuración global del módulo de noticias
-- ============================================
CREATE TABLE news_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_enabled BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Sección de noticias activa',
    max_items_home INT NOT NULL DEFAULT 5 COMMENT 'Máximo de noticias en home',
    allow_videos BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Permitir videos',
    allow_images BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Permitir imágenes',
    updated_by INT DEFAULT NULL COMMENT 'Usuario que actualizó',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Configuración por defecto
INSERT INTO news_settings (section_enabled, max_items_home, allow_videos, allow_images) VALUES
    (TRUE, 5, TRUE, TRUE);

-- ============================================
-- TABLA: category (OPCIONAL - para uso futuro)
-- Categorías predefinidas
-- ============================================
CREATE TABLE category (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    color VARCHAR(7) DEFAULT '#3B82F6' COMMENT 'Color hex para UI',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insertar categorías base
INSERT INTO category (name, slug, description, color) VALUES
    ('Tecnología', 'tecnologia', 'Noticias sobre tecnología e innovación', '#3B82F6'),
    ('Hidrógeno Verde', 'hidrogeno-verde', 'Avances en hidrógeno verde', '#22C55E'),
    ('Energías Renovables', 'energias-renovables', 'Noticias sobre energías limpias', '#EAB308'),
    ('Proyectos', 'proyectos', 'Actualizaciones de proyectos Dighy', '#8B5CF6'),
    ('Normativa', 'normativa', 'Cambios en legislación y normativas', '#EF4444'),
    ('Eventos', 'eventos', 'Conferencias, ferias y eventos', '#EC4899'),
    ('Empresa', 'empresa', 'Noticias corporativas', '#06B6D4');

-- ============================================
-- TABLA: news_articles
-- Artículos de noticias y actualizaciones
-- ============================================
CREATE TABLE news_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('news', 'update') NOT NULL DEFAULT 'news',
    title VARCHAR(255) NOT NULL COMMENT 'Título del artículo',
    slug VARCHAR(255) NOT NULL UNIQUE COMMENT 'URL amigable',
    excerpt TEXT DEFAULT NULL COMMENT 'Resumen/descripción corta',
    featured_image VARCHAR(500) DEFAULT NULL COMMENT 'Imagen principal',
    video_url VARCHAR(500) DEFAULT NULL COMMENT 'URL de video',
    is_pinned BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Noticia destacada/fijada',
    is_active BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Publicada/visible',
    published_at DATETIME DEFAULT NULL,
    created_by INT DEFAULT NULL COMMENT 'ID del usuario creador',
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_type (type),
    INDEX idx_active (is_active, published_at),
    INDEX idx_pinned (is_pinned),
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

-- ============================================
-- TABLA: news_category (N:M) - OPCIONAL
-- Relación muchos a muchos entre news_articles y category
-- ============================================
CREATE TABLE news_category (
    news_article_id INT NOT NULL,
    category_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (news_article_id, category_id),
    FOREIGN KEY (news_article_id) REFERENCES news_articles(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES category(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- TABLA: news_content_blocks
-- Bloques de contenido de cada artículo
-- ============================================
CREATE TABLE news_content_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    news_article_id INT NOT NULL COMMENT 'FK a news_articles',
    block_order INT NOT NULL DEFAULT 0 COMMENT 'Orden de visualización',
    block_type ENUM('text', 'image', 'video', 'heading', 'quote', 'list', 'divider', 'embed', 'file','link') NOT NULL DEFAULT 'text',
    content TEXT DEFAULT NULL COMMENT 'Contenido del bloque',
    metadata JSON DEFAULT NULL COMMENT 'Datos adicionales (alt, caption, styles, etc.)',
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_article_order (news_article_id, block_order),
    FOREIGN KEY (news_article_id) REFERENCES news_articles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- DATOS DE EJEMPLO
-- ============================================

-- Artículo 1: Tipo news
INSERT INTO news_articles (type, title, slug, excerpt, featured_image, is_active, is_pinned, published_at, created_by) VALUES
    ('news', 
     'Dighy lanza nuevo proyecto de hidrógeno verde en Almería',
     'dighy-lanza-nuevo-proyecto-hidrogeno', 
     'Dighy anuncia su nuevo proyecto de producción de hidrógeno verde en la provincia de Almería con una capacidad inicial de 50 MW.',
     'https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?w=800',
     TRUE, FALSE, NOW(), 1);

SET @article_1_id = LAST_INSERT_ID();

-- Asociar categorías al artículo 1
INSERT INTO news_category (news_article_id, category_id) VALUES
    (@article_1_id, 2), -- Hidrógeno Verde
    (@article_1_id, 4); -- Proyectos

-- Contenido del artículo 1
INSERT INTO news_content_blocks (news_article_id, block_order, block_type, content, metadata) VALUES
    (@article_1_id, 1, 'text', 
     'La empresa Dighy ha anunciado hoy el lanzamiento de su nuevo proyecto de producción de hidrógeno verde, que se ubicará en la provincia de Almería y tendrá una capacidad inicial de 50 MW.', 
     '{"classes": "text-lg text-gray-700 leading-relaxed"}'),
    (@article_1_id, 2, 'image', 
     'https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?w=800', 
     '{"alt": "Planta de hidrógeno verde", "caption": "Vista aérea del futuro emplazamiento"}'),
    (@article_1_id, 3, 'quote', 
     'Este proyecto representa un paso importante hacia la transición energética y la descarbonización de la industria española.',
     '{"author": "Director de Operaciones"}'),
    (@article_1_id, 4, 'text', 
     'El proyecto contará con una inversión inicial de 200 millones de euros y se espera que genere más de 500 empleos directos e indirectos en la región.',
     NULL);

-- Artículo 2: Tipo update
INSERT INTO news_articles (type, title, slug, excerpt, is_active, published_at, created_by) VALUES
    ('update', 
     'Mantenimiento programado del portal',
     'mantenimiento-programado-portal', 
     'El próximo sábado el portal estará en mantenimiento de 02:00 a 06:00 horas.',
     TRUE, NOW(), 1);

SET @article_2_id = LAST_INSERT_ID();

-- Asociar categorías al artículo 2
INSERT INTO news_category (news_article_id, category_id) VALUES
    (@article_2_id, 7); -- Empresa

-- Contenido del artículo 2 (update simple)
INSERT INTO news_content_blocks (news_article_id, block_order, block_type, content, metadata) VALUES
    (@article_2_id, 1, 'text', 
     'El próximo sábado 8 de marzo de 2026, el portal estará en mantenimiento de 02:00 a 06:00 horas. Disculpen las molestias.', 
     '{"classes": "p-4 bg-orange-50 rounded-lg text-orange-800"}');

-- Artículo 3: News con video
INSERT INTO news_articles (type, title, slug, excerpt, featured_image, video_url, is_active, is_pinned, published_at, created_by) VALUES
    ('news', 
     'Webinar: El futuro del hidrógeno en España',
     'webinar-futuro-hidrogeno-espana', 
     'Revive nuestro webinar donde expertos del sector analizan las perspectivas del hidrógeno verde en España.',
     'https://images.unsplash.com/photo-1560472354-b33ff0c44a43?w=800',
     'https://www.youtube.com/embed/dQw4w9WgXcQ',
     TRUE, TRUE, DATE_SUB(NOW(), INTERVAL 2 DAY), 1);

SET @article_3_id = LAST_INSERT_ID();

-- Asociar categorías al artículo 3
INSERT INTO news_category (news_article_id, category_id) VALUES
    (@article_3_id, 1), -- Tecnología
    (@article_3_id, 2), -- Hidrógeno Verde
    (@article_3_id, 6); -- Eventos

-- Contenido del artículo 3 con video
INSERT INTO news_content_blocks (news_article_id, block_order, block_type, content, metadata) VALUES
    (@article_3_id, 1, 'text', 
     'Revive nuestro webinar donde expertos del sector analizan las perspectivas del hidrógeno verde en España para los próximos 10 años.',
     '{"classes": "text-lg text-gray-600"}'),
    (@article_3_id, 2, 'video', 
     'https://www.youtube.com/embed/dQw4w9WgXcQ',
     '{"provider": "youtube", "autoplay": false}'),
    (@article_3_id, 3, 'heading', 
     'Temas tratados',
     '{"level": 3}'),
    (@article_3_id, 4, 'list', 
     '["Inversiones previstas hasta 2030", "Marco regulatorio actual", "Casos de éxito internacionales", "Oportunidades para pymes"]',
     '{"style": "bullet"}');

-- ============================================
-- VISTA: news_articles_full
-- Vista con información completa de artículos
-- ============================================
CREATE VIEW news_articles_full AS
SELECT 
    a.id,
    a.type,
    a.title,
    a.slug,
    a.excerpt,
    a.featured_image,
    a.video_url,
    a.is_pinned,
    a.is_active,
    a.published_at,
    a.created_by,
    a.created_at,
    a.updated_at,
    -- Categorías como JSON array
    (SELECT JSON_ARRAYAGG(JSON_OBJECT('id', cat.id, 'name', cat.name, 'slug', cat.slug, 'color', cat.color))
     FROM news_category nc
     JOIN category cat ON nc.category_id = cat.id
     WHERE nc.news_article_id = a.id) AS categories,
    -- Número de bloques de contenido
    (SELECT COUNT(*) FROM news_content_blocks cb WHERE cb.news_article_id = a.id) AS content_blocks_count
FROM news_articles a;

-- ============================================
-- INFORMACIÓN DEL ESQUEMA
-- ============================================
SELECT '✅ Base de datos dighy_news v3.0 creada correctamente' AS status;
SELECT 'Tablas creadas:' AS info;
SHOW TABLES;
SELECT '' AS '';
SELECT 'Configuración actual:' AS info;
SELECT id, section_enabled, max_items_home, allow_videos, allow_images FROM news_settings;
SELECT '' AS '';
SELECT 'Artículos de ejemplo:' AS info;
SELECT id, type, title, is_active, is_pinned FROM news_articles;

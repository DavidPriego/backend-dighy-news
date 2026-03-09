-- ============================================
-- DIGHY NEWS SYSTEM - Database Schema v2
-- Reestructurado según especificaciones
-- ============================================

DROP DATABASE IF EXISTS dighy_news;
CREATE DATABASE dighy_news CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dighy_news;

-- ============================================
-- TABLA: content_type
-- Tipos de contenido predefinidos
-- ============================================
CREATE TABLE content_type (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insertar tipos de contenido base
INSERT INTO content_type (name, description) VALUES
    ('text', 'Bloque de texto plano o HTML'),
    ('image', 'URL de imagen'),
    ('video', 'URL de video (YouTube, Vimeo, etc.)'),
    ('heading', 'Título o encabezado'),
    ('quote', 'Cita o blockquote'),
    ('list', 'Lista de elementos'),
    ('code', 'Bloque de código'),
    ('divider', 'Separador visual'),
    ('embed', 'Contenido embebido genérico'),
    ('file', 'Enlace a archivo descargable');

-- ============================================
-- TABLA: categories
-- Categorías predefinidas para evitar errores
-- ============================================
CREATE TABLE categories (
    id_category INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    color VARCHAR(7) DEFAULT '#3B82F6' COMMENT 'Color hex para UI',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insertar categorías base
INSERT INTO categories (name, slug, description, color) VALUES
    ('Tecnología', 'tecnologia', 'Noticias sobre tecnología e innovación', '#3B82F6'),
    ('Hidrógeno Verde', 'hidrogeno-verde', 'Avances en hidrógeno verde', '#22C55E'),
    ('Energías Renovables', 'energias-renovables', 'Noticias sobre energías limpias', '#EAB308'),
    ('Proyectos', 'proyectos', 'Actualizaciones de proyectos Dighy', '#8B5CF6'),
    ('Normativa', 'normativa', 'Cambios en legislación y normativas', '#EF4444'),
    ('Eventos', 'eventos', 'Conferencias, ferias y eventos', '#EC4899'),
    ('Empresa', 'empresa', 'Noticias corporativas', '#06B6D4');

-- ============================================
-- TABLA: news
-- Noticias y notificaciones
-- ============================================
CREATE TABLE news (
    id_news INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    type ENUM('news', 'notification') NOT NULL DEFAULT 'news',
    slug VARCHAR(255) NOT NULL UNIQUE COMMENT 'URL amigable',
    author_name VARCHAR(100) DEFAULT NULL,
    author_avatar VARCHAR(500) DEFAULT NULL,
    thumbnail_url VARCHAR(500) DEFAULT NULL COMMENT 'Imagen principal',
    thumbnail_alt VARCHAR(255) DEFAULT NULL,
    is_featured BOOLEAN NOT NULL DEFAULT FALSE,
    is_published BOOLEAN NOT NULL DEFAULT FALSE,
    published_at DATETIME DEFAULT NULL,
    -- SEO
    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description TEXT DEFAULT NULL,
    meta_keywords VARCHAR(500) DEFAULT NULL,
    og_image VARCHAR(500) DEFAULT NULL,
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_type (type),
    INDEX idx_published (is_published, published_at),
    INDEX idx_featured (is_featured),
    INDEX idx_uuid (uuid)
) ENGINE=InnoDB;

-- ============================================
-- TABLA: news_categories (N:M)
-- Relación muchos a muchos entre news y categories
-- ============================================
CREATE TABLE news_categories (
    id_news INT NOT NULL,
    id_category INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id_news, id_category),
    FOREIGN KEY (id_news) REFERENCES news(id_news) ON DELETE CASCADE,
    FOREIGN KEY (id_category) REFERENCES categories(id_category) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- TABLA: content
-- Bloques de contenido de cada noticia
-- ============================================
CREATE TABLE content (
    id_content INT AUTO_INCREMENT PRIMARY KEY,
    parent_news INT NOT NULL,
    type INT NOT NULL COMMENT 'FK a content_type',
    `order` INT NOT NULL DEFAULT 0 COMMENT 'Orden de visualización',
    title VARCHAR(120) DEFAULT NULL COMMENT 'Auto: primeros 120 chars del content',
    content TEXT DEFAULT NULL COMMENT 'Texto, URL de imagen, URL de video, etc.',
    style JSON DEFAULT NULL COMMENT 'Clases Tailwind y estilos',
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_parent_order (parent_news, `order`),
    FOREIGN KEY (parent_news) REFERENCES news(id_news) ON DELETE CASCADE,
    FOREIGN KEY (type) REFERENCES content_type(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================
-- TRIGGER: Auto-generar título desde content
-- ============================================
DELIMITER //
CREATE TRIGGER content_auto_title_insert
BEFORE INSERT ON content
FOR EACH ROW
BEGIN
    IF NEW.title IS NULL AND NEW.content IS NOT NULL THEN
        SET NEW.title = LEFT(REGEXP_REPLACE(NEW.content, '<[^>]*>', ''), 120);
    END IF;
END//

CREATE TRIGGER content_auto_title_update
BEFORE UPDATE ON content
FOR EACH ROW
BEGIN
    IF NEW.title IS NULL AND NEW.content IS NOT NULL THEN
        SET NEW.title = LEFT(REGEXP_REPLACE(NEW.content, '<[^>]*>', ''), 120);
    END IF;
END//
DELIMITER ;

-- ============================================
-- DATOS DE EJEMPLO
-- ============================================

-- Noticia 1: Tipo news
INSERT INTO news (uuid, type, slug, author_name, is_published, published_at, thumbnail_url, meta_title, meta_description) VALUES
    (UUID(), 'news', 'dighy-lanza-nuevo-proyecto-hidrogeno', 'Alfredo González', TRUE, NOW(), 
     'https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?w=800',
     'Dighy lanza nuevo proyecto de hidrógeno verde',
     'Dighy anuncia su nuevo proyecto de producción de hidrógeno verde en la provincia de Almería.');

SET @news1_id = LAST_INSERT_ID();

-- Asociar categorías a noticia 1
INSERT INTO news_categories (id_news, id_category) VALUES
    (@news1_id, 2), -- Hidrógeno Verde
    (@news1_id, 4); -- Proyectos

-- Contenido de noticia 1
INSERT INTO content (parent_news, type, `order`, content, style) VALUES
    (@news1_id, 4, 1, 'Dighy lanza nuevo proyecto de hidrógeno verde en Almería', 
     '{"container": "mb-6", "text": "text-3xl font-bold text-gray-900"}'),
    (@news1_id, 1, 2, 'La empresa Dighy ha anunciado hoy el lanzamiento de su nuevo proyecto de producción de hidrógeno verde, que se ubicará en la provincia de Almería y tendrá una capacidad inicial de 50 MW.', 
     '{"container": "mb-4", "text": "text-lg text-gray-700 leading-relaxed"}'),
    (@news1_id, 2, 3, 'https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?w=800', 
     '{"container": "my-6 rounded-lg overflow-hidden", "image": "w-full h-auto"}'),
    (@news1_id, 5, 4, 'Este proyecto representa un paso importante hacia la transición energética y la descarbonización de la industria española.',
     '{"container": "border-l-4 border-green-500 pl-4 my-6", "text": "text-xl italic text-gray-600"}'),
    (@news1_id, 1, 5, 'El proyecto contará con una inversión inicial de 200 millones de euros y se espera que genere más de 500 empleos directos e indirectos en la región.',
     '{"container": "mb-4", "text": "text-base text-gray-700"}');

-- Noticia 2: Tipo notification
INSERT INTO news (uuid, type, slug, author_name, is_published, published_at) VALUES
    (UUID(), 'notification', 'mantenimiento-programado-portal', 'Sistema', TRUE, NOW());

SET @news2_id = LAST_INSERT_ID();

-- Asociar categorías a noticia 2
INSERT INTO news_categories (id_news, id_category) VALUES
    (@news2_id, 7); -- Empresa

-- Contenido de noticia 2 (notificación simple)
INSERT INTO content (parent_news, type, `order`, content, style) VALUES
    (@news2_id, 4, 1, 'Mantenimiento programado del portal', 
     '{"container": "mb-4", "text": "text-xl font-semibold text-orange-600"}'),
    (@news2_id, 1, 2, 'El próximo sábado 8 de marzo de 2026, el portal estará en mantenimiento de 02:00 a 06:00 horas. Disculpen las molestias.', 
     '{"container": "p-4 bg-orange-50 rounded-lg", "text": "text-base text-orange-800"}');

-- Noticia 3: News con video
INSERT INTO news (uuid, type, slug, author_name, is_featured, is_published, published_at, thumbnail_url) VALUES
    (UUID(), 'news', 'webinar-futuro-hidrogeno-espana', 'María López', TRUE, TRUE, DATE_SUB(NOW(), INTERVAL 2 DAY),
     'https://images.unsplash.com/photo-1560472354-b33ff0c44a43?w=800');

SET @news3_id = LAST_INSERT_ID();

-- Asociar categorías a noticia 3
INSERT INTO news_categories (id_news, id_category) VALUES
    (@news3_id, 1), -- Tecnología
    (@news3_id, 2), -- Hidrógeno Verde
    (@news3_id, 6); -- Eventos

-- Contenido de noticia 3 con video
INSERT INTO content (parent_news, type, `order`, content, style) VALUES
    (@news3_id, 4, 1, 'Webinar: El futuro del hidrógeno en España', 
     '{"container": "mb-6", "text": "text-4xl font-extrabold text-gray-900"}'),
    (@news3_id, 1, 2, 'Revive nuestro webinar donde expertos del sector analizan las perspectivas del hidrógeno verde en España para los próximos 10 años.',
     '{"container": "mb-6", "text": "text-lg text-gray-600"}'),
    (@news3_id, 3, 3, 'https://www.youtube.com/embed/dQw4w9WgXcQ',
     '{"container": "my-8 aspect-video", "iframe": "w-full h-full rounded-xl shadow-lg"}'),
    (@news3_id, 6, 4, '<ul><li>Inversiones previstas hasta 2030</li><li>Marco regulatorio actual</li><li>Casos de éxito internacionales</li><li>Oportunidades para pymes</li></ul>',
     '{"container": "my-6", "list": "list-disc list-inside space-y-2 text-gray-700"}');

-- ============================================
-- VISTA: news_full
-- Vista con toda la información de una noticia
-- ============================================
CREATE VIEW news_full AS
SELECT 
    n.id_news,
    n.uuid,
    n.type,
    n.slug,
    n.author_name,
    n.author_avatar,
    n.thumbnail_url,
    n.thumbnail_alt,
    n.is_featured,
    n.is_published,
    n.published_at,
    n.meta_title,
    n.meta_description,
    n.created_at,
    n.updated_at,
    -- Primer título del contenido
    (SELECT c.title FROM content c WHERE c.parent_news = n.id_news ORDER BY c.`order` ASC LIMIT 1) AS title,
    -- Categorías como JSON array
    (SELECT JSON_ARRAYAGG(JSON_OBJECT('id', cat.id_category, 'name', cat.name, 'slug', cat.slug, 'color', cat.color))
     FROM news_categories nc
     JOIN categories cat ON nc.id_category = cat.id_category
     WHERE nc.id_news = n.id_news) AS categories,
    -- Calcular tiempo de lectura (palabras / 200 wpm)
    (SELECT CEIL(SUM(LENGTH(c.content) - LENGTH(REPLACE(c.content, ' ', '')) + 1) / 200)
     FROM content c WHERE c.parent_news = n.id_news AND c.type = 1) AS read_time_minutes
FROM news n;

-- ============================================
-- INFORMACIÓN DEL ESQUEMA
-- ============================================
SELECT '✅ Base de datos dighy_news v2 creada correctamente' AS status;
SELECT 'Tablas creadas:' AS info;
SHOW TABLES;
SELECT '' AS '';
SELECT 'Tipos de contenido disponibles:' AS info;
SELECT id, name, description FROM content_type;
SELECT '' AS '';
SELECT 'Categorías disponibles:' AS info;
SELECT id_category, name, slug, color FROM categories;

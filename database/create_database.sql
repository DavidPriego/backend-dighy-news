-- ============================================
-- DIGHY NEWS SYSTEM - Database Schema v2.1
-- Convención: underscore_number_aware
-- FK pattern: {referenced_table}_id
-- ============================================

DROP DATABASE IF EXISTS dighy_news;
CREATE DATABASE dighy_news CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dighy_news;

-- ============================================
-- TABLA: content_type
-- Tipos de contenido predefinidos
-- ============================================
CREATE TABLE content_type (
    content_type_id INT AUTO_INCREMENT PRIMARY KEY,
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
    ('divider', 'Separador visual'),
    ('embed', 'Contenido embebido genérico'),
    ('file', 'Enlace a archivo descargable');

-- ============================================
-- TABLA: category
-- Categorías predefinidas para evitar errores
-- ============================================
CREATE TABLE category (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
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
-- TABLA: news
-- Noticias y notificaciones
-- ============================================
CREATE TABLE news (
    news_id INT AUTO_INCREMENT PRIMARY KEY,
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
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_type (type),
    INDEX idx_published (is_published, published_at),
    INDEX idx_featured (is_featured),
    INDEX idx_uuid (uuid)
) ENGINE=InnoDB;

-- ============================================
-- TABLA: news_category (N:M)
-- Relación muchos a muchos entre news y category
-- ============================================
CREATE TABLE news_category (
    news_id INT NOT NULL,
    category_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (news_id, category_id),
    FOREIGN KEY (news_id) REFERENCES news(news_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES category(category_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- TABLA: content
-- Bloques de contenido de cada noticia
-- ============================================
CREATE TABLE content (
    content_id INT AUTO_INCREMENT PRIMARY KEY,
    news_id INT NOT NULL COMMENT 'FK a news (parent)',
    content_type_id INT NOT NULL COMMENT 'FK a content_type',
    sort_order INT NOT NULL DEFAULT 0 COMMENT 'Orden de visualización',
    title VARCHAR(120) DEFAULT NULL COMMENT 'Auto: primeros 120 chars del content',
    content TEXT DEFAULT NULL COMMENT 'Texto, URL de imagen, URL de video, etc.',
    style JSON DEFAULT NULL COMMENT 'Clases Tailwind y estilos',
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_news_order (news_id, sort_order),
    FOREIGN KEY (news_id) REFERENCES news(news_id) ON DELETE CASCADE,
    FOREIGN KEY (content_type_id) REFERENCES content_type(content_type_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================
-- DATOS DE EJEMPLO
-- ============================================

-- Noticia 1: Tipo news
INSERT INTO news (uuid, type, slug, author_name, is_published, published_at, thumbnail_url) VALUES
    (UUID(), 'news', 'dighy-lanza-nuevo-proyecto-hidrogeno', 'Alfredo González', TRUE, NOW(), 
     'https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?w=800',
     'Dighy lanza nuevo proyecto de hidrógeno verde',
     'Dighy anuncia su nuevo proyecto de producción de hidrógeno verde en la provincia de Almería.');

SET @news_1_id = LAST_INSERT_ID();

-- Asociar categorías a noticia 1
INSERT INTO news_category (news_id, category_id) VALUES
    (@news_1_id, 2), -- Hidrógeno Verde
    (@news_1_id, 4); -- Proyectos

-- Contenido de noticia 1
INSERT INTO content (news_id, content_type_id, sort_order, content, style) VALUES
    (@news_1_id, 4, 1, 'Dighy lanza nuevo proyecto de hidrógeno verde en Almería', 
     '{"container": "mb-6", "text": "text-3xl font-bold text-gray-900"}'),
    (@news_1_id, 1, 2, 'La empresa Dighy ha anunciado hoy el lanzamiento de su nuevo proyecto de producción de hidrógeno verde, que se ubicará en la provincia de Almería y tendrá una capacidad inicial de 50 MW.', 
     '{"container": "mb-4", "text": "text-lg text-gray-700 leading-relaxed"}'),
    (@news_1_id, 2, 3, 'https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?w=800', 
     '{"container": "my-6 rounded-lg overflow-hidden", "image": "w-full h-auto"}'),
    (@news_1_id, 5, 4, 'Este proyecto representa un paso importante hacia la transición energética y la descarbonización de la industria española.',
     '{"container": "border-l-4 border-green-500 pl-4 my-6", "text": "text-xl italic text-gray-600"}'),
    (@news_1_id, 1, 5, 'El proyecto contará con una inversión inicial de 200 millones de euros y se espera que genere más de 500 empleos directos e indirectos en la región.',
     '{"container": "mb-4", "text": "text-base text-gray-700"}');

-- Noticia 2: Tipo notification
INSERT INTO news (uuid, type, slug, author_name, is_published, published_at) VALUES
    (UUID(), 'notification', 'mantenimiento-programado-portal', 'Sistema', TRUE, NOW());

SET @news_2_id = LAST_INSERT_ID();

-- Asociar categorías a noticia 2
INSERT INTO news_category (news_id, category_id) VALUES
    (@news_2_id, 7); -- Empresa

-- Contenido de noticia 2 (notificación simple)
INSERT INTO content (news_id, content_type_id, sort_order, content, style) VALUES
    (@news_2_id, 4, 1, 'Mantenimiento programado del portal', 
     '{"container": "mb-4", "text": "text-xl font-semibold text-orange-600"}'),
    (@news_2_id, 1, 2, 'El próximo sábado 8 de marzo de 2026, el portal estará en mantenimiento de 02:00 a 06:00 horas. Disculpen las molestias.', 
     '{"container": "p-4 bg-orange-50 rounded-lg", "text": "text-base text-orange-800"}');

-- Noticia 3: News con video
INSERT INTO news (uuid, type, slug, author_name, is_featured, is_published, published_at, thumbnail_url) VALUES
    (UUID(), 'news', 'webinar-futuro-hidrogeno-espana', 'María López', TRUE, TRUE, DATE_SUB(NOW(), INTERVAL 2 DAY),
     'https://images.unsplash.com/photo-1560472354-b33ff0c44a43?w=800');

SET @news_3_id = LAST_INSERT_ID();

-- Asociar categorías a noticia 3
INSERT INTO news_category (news_id, category_id) VALUES
    (@news_3_id, 1), -- Tecnología
    (@news_3_id, 2), -- Hidrógeno Verde
    (@news_3_id, 6); -- Eventos

-- Contenido de noticia 3 con video
INSERT INTO content (news_id, content_type_id, sort_order, content, style) VALUES
    (@news_3_id, 4, 1, 'Webinar: El futuro del hidrógeno en España', 
     '{"container": "mb-6", "text": "text-4xl font-extrabold text-gray-900"}'),
    (@news_3_id, 1, 2, 'Revive nuestro webinar donde expertos del sector analizan las perspectivas del hidrógeno verde en España para los próximos 10 años.',
     '{"container": "mb-6", "text": "text-lg text-gray-600"}'),
    (@news_3_id, 3, 3, 'https://www.youtube.com/embed/dQw4w9WgXcQ',
     '{"container": "my-8 aspect-video", "iframe": "w-full h-full rounded-xl shadow-lg"}'),
    (@news_3_id, 6, 4, '<ul><li>Inversiones previstas hasta 2030</li><li>Marco regulatorio actual</li><li>Casos de éxito internacionales</li><li>Oportunidades para pymes</li></ul>',
     '{"container": "my-6", "list": "list-disc list-inside space-y-2 text-gray-700"}');

-- ============================================
-- VISTA: news_full
-- Vista con toda la información de una noticia
-- ============================================
CREATE VIEW news_full AS
SELECT 
    n.news_id,
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
    (SELECT c.title FROM content c WHERE c.news_id = n.news_id ORDER BY c.sort_order ASC LIMIT 1) AS title,
    -- Categorías como JSON array
    (SELECT JSON_ARRAYAGG(JSON_OBJECT('id', cat.category_id, 'name', cat.name, 'slug', cat.slug, 'color', cat.color))
     FROM news_category nc
     JOIN category cat ON nc.category_id = cat.category_id
     WHERE nc.news_id = n.news_id) AS categories,
    -- Calcular tiempo de lectura (palabras / 200 wpm)
    (SELECT CEIL(SUM(LENGTH(c.content) - LENGTH(REPLACE(c.content, ' ', '')) + 1) / 200)
     FROM content c WHERE c.news_id = n.news_id AND c.content_type_id = 1) AS read_time_minutes
FROM news n;

-- ============================================
-- INFORMACIÓN DEL ESQUEMA
-- ============================================
SELECT '✅ Base de datos dighy_news v2.1 (underscore_number_aware) creada correctamente' AS status;
SELECT 'Tablas creadas:' AS info;
SHOW TABLES;
SELECT '' AS '';
SELECT 'Tipos de contenido disponibles:' AS info;
SELECT content_type_id, name, description FROM content_type;
SELECT '' AS '';
SELECT 'Categorías disponibles:' AS info;
SELECT category_id, name, slug, color FROM category;

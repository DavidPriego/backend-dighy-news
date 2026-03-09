-- ============================================================================
-- BASE DE DATOS: dighy_news
-- Sistema de Noticias y Actualizaciones para Dighy Dashboard
-- 
-- Framework: Slim PHP 4
-- Fecha: Marzo 2026
-- ============================================================================

-- Crear base de datos si no existe
CREATE DATABASE IF NOT EXISTS dighy_news 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE dighy_news;

-- ============================================================================
-- TABLA 1: news_settings
-- Configuración global de la sección de noticias (1 registro)
-- ============================================================================

DROP TABLE IF EXISTS news_content_blocks;
DROP TABLE IF EXISTS news_articles;
DROP TABLE IF EXISTS news_settings;

CREATE TABLE news_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Configuración de visibilidad
    section_enabled TINYINT(1) NOT NULL DEFAULT 1 
        COMMENT 'Activa/desactiva toda la sección de noticias en el dashboard',
    
    -- Configuración de display
    max_items_home INT NOT NULL DEFAULT 5 
        COMMENT 'Número máximo de noticias a mostrar en el dashboard',
    
    -- Configuración de contenido
    allow_videos TINYINT(1) NOT NULL DEFAULT 1 
        COMMENT 'Permite incluir videos de YouTube en las noticias',
    allow_images TINYINT(1) NOT NULL DEFAULT 1 
        COMMENT 'Permite incluir imágenes en las noticias',
    default_layout ENUM('single', 'two_column') NOT NULL DEFAULT 'single'
        COMMENT 'Layout por defecto para nuevos artículos',
    
    -- Auditoría
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL COMMENT 'ID del usuario que actualizó'
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuración inicial
INSERT INTO news_settings (section_enabled, max_items_home, allow_videos, allow_images, default_layout) 
VALUES (1, 5, 1, 1, 'single');


-- ============================================================================
-- TABLA 2: news_articles
-- Artículos de noticias y actualizaciones
-- ============================================================================

CREATE TABLE news_articles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Tipo de contenido
    type ENUM('news', 'update') NOT NULL DEFAULT 'news'
        COMMENT 'Tipo: news=Noticia, update=Actualización del sistema',
    
    -- Contenido principal
    title VARCHAR(255) NOT NULL 
        COMMENT 'Título del artículo',
    slug VARCHAR(255) NOT NULL UNIQUE 
        COMMENT 'URL amigable generada desde el título',
    excerpt TEXT NULL 
        COMMENT 'Resumen corto para listados (máx 300 caracteres recomendado)',
    
    -- Layout y multimedia
    layout ENUM('single', 'two_column') NOT NULL DEFAULT 'single'
        COMMENT 'Layout: single=1 columna, two_column=2 columnas (main + sidebar)',
    featured_image VARCHAR(512) NULL 
        COMMENT 'URL de la imagen principal/destacada',
    video_url VARCHAR(512) NULL 
        COMMENT 'URL de video embebido (YouTube/Vimeo embed URL)',
    
    -- Estado y visibilidad
    is_active TINYINT(1) NOT NULL DEFAULT 1 
        COMMENT 'Artículo activo (visible) / inactivo (oculto)',
    is_pinned TINYINT(1) NOT NULL DEFAULT 0 
        COMMENT 'Artículo destacado (aparece primero en el listado)',
    
    -- Programación
    published_at DATETIME NULL 
        COMMENT 'Fecha de publicación programada (NULL = publicar inmediatamente)',
    
    -- Auditoría
    created_by INT NOT NULL DEFAULT 1
        COMMENT 'ID del usuario creador',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices para búsqueda eficiente
    INDEX idx_type (type),
    INDEX idx_is_active (is_active),
    INDEX idx_published_at (published_at),
    INDEX idx_is_pinned (is_pinned),
    INDEX idx_created_at (created_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- TABLA 3: news_content_blocks
-- Bloques de contenido dinámico para layouts flexibles
-- ============================================================================

CREATE TABLE news_content_blocks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Relación con artículo
    article_id INT NOT NULL 
        COMMENT 'FK al artículo padre',
    
    -- Posición en layout
    column_position ENUM('main', 'sidebar') NOT NULL DEFAULT 'main'
        COMMENT 'Columna: main=columna principal, sidebar=columna lateral (solo para two_column)',
    
    -- Tipo de contenido
    block_type ENUM('text', 'image', 'video', 'link', 'divider') NOT NULL DEFAULT 'text'
        COMMENT 'Tipo de bloque: text=HTML, image=URL imagen, video=URL embed, link=URL enlace, divider=separador',
    
    -- Contenido
    content TEXT NULL 
        COMMENT 'Contenido: HTML para text, URL para image/video/link, NULL para divider',
    meta_data JSON NULL 
        COMMENT 'Datos adicionales JSON: {alt, caption, target, label, etc}',
    
    -- Orden
    sort_order INT NOT NULL DEFAULT 0 
        COMMENT 'Orden de aparición dentro de la columna',
    
    -- Auditoría
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_article_id (article_id),
    INDEX idx_sort_order (sort_order),
    
    -- Foreign Key con eliminación en cascada
    CONSTRAINT fk_content_blocks_article 
        FOREIGN KEY (article_id) 
        REFERENCES news_articles(id) 
        ON DELETE CASCADE
        ON UPDATE CASCADE
        
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- DATOS DE EJEMPLO
-- ============================================================================

-- Ejemplo 1: Noticia con layout de 2 columnas
INSERT INTO news_articles (
    type, title, slug, excerpt, layout, 
    featured_image, is_active, is_pinned, created_by
) VALUES (
    'news',
    'Nueva funcionalidad de simulación disponible',
    'nueva-funcionalidad-simulacion',
    'Hemos lanzado una nueva herramienta de simulación avanzada que permite realizar proyecciones más precisas con datos en tiempo real.',
    'two_column',
    'https://via.placeholder.com/800x400/00e676/ffffff?text=Simulacion',
    1,
    1,
    1
);

-- Bloques de contenido para la noticia 1
INSERT INTO news_content_blocks (article_id, column_position, block_type, content, sort_order) VALUES
(1, 'main', 'text', '<p>Estamos emocionados de anunciar el lanzamiento de nuestra nueva herramienta de <strong>simulación avanzada</strong>.</p><p>Esta funcionalidad permite a los usuarios realizar proyecciones más precisas con datos en tiempo real, mejorando significativamente la toma de decisiones.</p>', 1),
(1, 'main', 'text', '<h3>Características principales:</h3><ul><li>Simulaciones en tiempo real</li><li>Integración con datos externos</li><li>Exportación de resultados</li><li>Comparación de escenarios</li></ul>', 2),
(1, 'sidebar', 'image', 'https://via.placeholder.com/300x200/1a1a1a/00e676?text=Feature+1', 1),
(1, 'sidebar', 'link', 'https://docs.dighy.com/simulacion', 2);

-- Ejemplo 2: Actualización del sistema
INSERT INTO news_articles (
    type, title, slug, excerpt, layout, 
    is_active, created_by
) VALUES (
    'update',
    'Actualización v2.5.0 - Mejoras de rendimiento',
    'actualizacion-v250',
    'Nueva versión con mejoras significativas en el rendimiento del sistema y corrección de bugs reportados.',
    'single',
    1,
    1
);

-- Bloques de contenido para la actualización
INSERT INTO news_content_blocks (article_id, column_position, block_type, content, sort_order) VALUES
(2, 'main', 'text', '<h3>Cambios en esta versión:</h3><ul><li>✅ Mejora del 40% en tiempos de carga</li><li>✅ Nueva interfaz de usuario optimizada</li><li>✅ Corrección de bugs reportados por usuarios</li><li>✅ Mejoras de seguridad</li></ul>', 1),
(2, 'main', 'divider', NULL, 2),
(2, 'main', 'text', '<p><em>Para más información sobre esta actualización, consulta nuestra documentación.</em></p>', 3);

-- Ejemplo 3: Evento (noticia inactiva)
INSERT INTO news_articles (
    type, title, slug, excerpt, layout, 
    featured_image, is_active, is_pinned, published_at, created_by
) VALUES (
    'news',
    'Webinar: Introducción al análisis de hidrógeno verde',
    'webinar-hidrogeno-verde',
    'Únete a nuestro webinar gratuito donde exploraremos las oportunidades del hidrógeno verde en la transición energética.',
    'single',
    'https://via.placeholder.com/800x400/00e676/000000?text=Webinar',
    0,
    0,
    '2026-04-15 10:00:00',
    1
);

INSERT INTO news_content_blocks (article_id, column_position, block_type, content, sort_order) VALUES
(3, 'main', 'text', '<p>Este webinar está diseñado para profesionales interesados en el sector del hidrógeno verde.</p>', 1),
(3, 'main', 'video', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 2);


-- ============================================================================
-- VERIFICACIÓN
-- ============================================================================

-- Mostrar estructura de tablas creadas
SELECT 'Tablas creadas exitosamente:' AS mensaje;
SHOW TABLES;

-- Mostrar datos insertados
SELECT 'Configuración inicial:' AS mensaje;
SELECT * FROM news_settings;

SELECT 'Artículos de ejemplo:' AS mensaje;
SELECT id, type, title, is_active, is_pinned FROM news_articles;

SELECT 'Bloques de contenido:' AS mensaje;
SELECT article_id, column_position, block_type, sort_order FROM news_content_blocks ORDER BY article_id, sort_order;

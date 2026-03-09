# 📰 Dighy News API

Microservicio de Noticias y Actualizaciones para el Dashboard de Dighy.

## 🚀 Stack Tecnológico

- **Framework**: Slim PHP 4.15
- **Base de Datos**: MySQL 8.0
- **PHP**: 8.1+

## 📦 Instalación

### 1. Instalar dependencias

```bash
composer install
```

### 2. Configurar entorno

```bash
cp .env.example .env
# Editar .env con tus credenciales de BD
```

### 3. Crear base de datos

```bash
mysql -u root -p < database/create_database.sql
```

### 4. Iniciar servidor de desarrollo

```bash
php -S localhost:8080 -t public
```

## 🔌 API Endpoints

### Públicos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/health` | Health check |
| `GET` | `/api/news` | Lista noticias activas |
| `GET` | `/api/news/{id}` | Detalle de noticia (por ID o slug) |
| `GET` | `/api/news/settings` | Configuración pública |

### Admin

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/admin/news` | Lista TODAS las noticias |
| `POST` | `/api/admin/news` | Crear noticia |
| `PUT` | `/api/admin/news/{id}` | Actualizar noticia |
| `DELETE` | `/api/admin/news/{id}` | Eliminar noticia |
| `PATCH` | `/api/admin/news/{id}/toggle` | Activar/desactivar |
| `GET` | `/api/admin/settings` | Obtener configuración |
| `PUT` | `/api/admin/settings` | Actualizar configuración |

## 📝 Ejemplos de Uso

### Listar noticias

```bash
curl http://localhost:8080/api/news
```

### Crear noticia

```bash
curl -X POST http://localhost:8080/api/admin/news \
  -H "Content-Type: application/json" \
  -d '{
    "type": "news",
    "title": "Mi primera noticia",
    "excerpt": "Resumen de la noticia",
    "layout": "single",
    "content_blocks": [
      {
        "column_position": "main",
        "block_type": "text",
        "content": "<p>Contenido de la noticia</p>"
      }
    ]
  }'
```

### Activar/Desactivar noticia

```bash
curl -X PATCH http://localhost:8080/api/admin/news/1/toggle
```

## 🗄️ Estructura de Base de Datos

### Diagrama ER

```
news_settings (1)
    │
    │ (configuración global)
    │
news_articles (N)
    │
    │ 1:N
    │
news_content_blocks (N)
```

### Tablas

- **news_settings**: Configuración global (1 registro)
- **news_articles**: Artículos de noticias/actualizaciones
- **news_content_blocks**: Bloques de contenido dinámico

## 📁 Estructura del Proyecto

```
backend-dighy-news/
├── composer.json         # Dependencias PHP
├── .env                  # Variables de entorno
├── public/
│   └── index.php         # Entry point
├── config/
│   ├── routes.php        # Definición de rutas
│   └── container.php     # Dependency Injection
├── src/
│   ├── Controllers/
│   │   ├── NewsController.php
│   │   └── SettingsController.php
│   ├── Database/
│   │   └── Connection.php
│   └── Middleware/
│       └── CorsMiddleware.php
├── database/
│   └── create_database.sql
└── logs/
    └── .gitkeep
```

## 🔧 Configuración

### Variables de Entorno (.env)

```env
APP_NAME=dighy-news-api
APP_ENV=development
APP_DEBUG=true

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=dighy_news
DB_USERNAME=root
DB_PASSWORD=

CORS_ORIGINS=http://localhost:3000,http://localhost:3001
```

## 🧪 Testing

```bash
# Health check
curl http://localhost:8080/health

# Listar noticias
curl http://localhost:8080/api/news

# Ver configuración
curl http://localhost:8080/api/news/settings
```

## 📋 TODO

- [ ] Agregar autenticación JWT
- [ ] Validación de datos más robusta
- [ ] Logging con Monolog
- [ ] Tests unitarios
- [ ] Documentación OpenAPI/Swagger

---

**Versión**: 1.0.0  
**Fecha**: Marzo 2026

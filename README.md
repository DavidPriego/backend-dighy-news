# 📰 Dighy News API

Microservicio RESTful de Noticias y Actualizaciones para el Dashboard de Dighy.

## Stack

| Tecnología | Versión |
|------------|---------|
| PHP | 8.1+ |
| Slim Framework | 4.12 |
| MySQL | 8.0+ |
| PHPUnit | 10.5 |

## Instalación Rápida

```bash
# Clonar e instalar
git clone <repo-url>
cd backend-dighy-news
composer install

# Configurar
cp .env.example .env
# Editar .env con credenciales de BD

# Crear base de datos
mysql -u root -p < database/create_database.sql

# Iniciar servidor
php -S localhost:8080 -t public
```

## Endpoints Principales

### Públicos
```
GET  /api/news              # Lista noticias activas
GET  /api/news/{id}         # Detalle de noticia
GET  /api/news/settings     # Configuración
GET  /api/categories        # Categorías
```

### Admin
```
GET    /api/admin/news              # Lista todas las noticias
POST   /api/admin/news              # Crear noticia
PUT    /api/admin/news/{id}         # Actualizar
DELETE /api/admin/news/{id}         # Eliminar
PATCH  /api/admin/news/{id}/toggle  # Activar/desactivar
```

## Tests

```bash
# Ejecutar todos los tests
composer test

# Solo unitarios
./vendor/bin/phpunit --testsuite Unit
```

**Resultado:** 20 tests, 70 assertions ✅

## Documentación Técnica

| Documento | Descripción |
|-----------|-------------|
| [📖 Documentación Principal](docs/README.md) | Arquitectura y guía completa |
| [🗃️ Modelo de Datos](docs/DATABASE.md) | Diagrama E-R y esquema BD |
| [🔌 API Reference](docs/API.md) | Endpoints detallados con ejemplos |
| [🧪 Pruebas Unitarias](docs/TESTING.md) | Documentación de tests |

## Estructura del Proyecto

```
backend-dighy-news/
├── config/           # Rutas y contenedor DI
├── database/         # Schema SQL v3.0
├── docs/             # Documentación técnica
├── public/           # Entry point + uploads
├── src/
│   ├── Controllers/  # NewsController, SettingsController, UploadController
│   ├── Database/     # Connection PDO
│   └── Middleware/   # CORS
├── tests/            # PHPUnit tests
├── .env.example      # Variables de entorno
└── composer.json
```

## Configuración Frontend

Para integrar con Vue.js/Nuxt en `localhost:3000`:

```env
# .env
CORS_ORIGINS=http://localhost:3000
```

## Licencia

Propietario - Dighy © 2026

<?php
/**
 * Middleware CORS
 * 
 * Gestiona los headers CORS para permitir peticiones cross-origin.
 * Lee los orígenes permitidos desde variables de entorno.
 */

namespace Dighy\News\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        
        // Si es preflight (OPTIONS), devolver respuesta vacía con headers
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response();
            return $this->addCorsHeaders($response, $request);
        }

        // Procesar la petición normal
        $response = $handler->handle($request);
        
        // Agregar headers CORS a la respuesta
        return $this->addCorsHeaders($response, $request);
    }

    /**
     * Agrega los headers CORS a la respuesta
     */
    private function addCorsHeaders(
        ResponseInterface $response,
        ServerRequestInterface $request
    ): ResponseInterface {
        
        // Obtener origen de la petición
        $origin = $request->getHeaderLine('Origin');
        
        // Orígenes permitidos desde env
        $allowedOrigins = explode(',', $_ENV['CORS_ORIGINS'] ?? '*');
        $allowedOrigins = array_map('trim', $allowedOrigins);
        
        // Si el origen está permitido o permitimos todos
        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            $allowOrigin = $origin ?: '*';
        } else {
            $allowOrigin = $allowedOrigins[0] ?? '*';
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}

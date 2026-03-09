<?php
/**
 * UploadController - Gestión de subida de archivos
 * 
 * Endpoints:
 *   POST /api/upload/image - Subir imagen
 */

namespace Dighy\News\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UploadController
{
    private string $uploadDir;
    private array $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private int $maxSize = 5 * 1024 * 1024; // 5MB

    public function __construct()
    {
        $this->uploadDir = __DIR__ . '/../../public/uploads/';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * POST /api/upload/image
     * Subir una imagen
     */
    public function upload(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        
        if (empty($uploadedFiles['image'])) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Validation Error',
                'message' => 'No se proporcionó ninguna imagen'
            ], 400);
        }

        $file = $uploadedFiles['image'];
        
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Upload Error',
                'message' => 'Error al subir el archivo: ' . $this->getUploadErrorMessage($file->getError())
            ], 400);
        }

        // Validar tipo MIME
        $mimeType = $file->getClientMediaType();
        if (!in_array($mimeType, $this->allowedTypes)) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Validation Error',
                'message' => 'Tipo de archivo no permitido. Use JPG, PNG, GIF o WebP'
            ], 400);
        }

        // Validar tamaño
        if ($file->getSize() > $this->maxSize) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Validation Error',
                'message' => 'El archivo excede el tamaño máximo de 5MB'
            ], 400);
        }

        // Generar nombre único
        $extension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        $filename = 'img_' . uniqid() . '_' . time() . '.' . $extension;
        $filepath = $this->uploadDir . $filename;

        try {
            // Mover archivo
            $file->moveTo($filepath);

            // URL pública
            $baseUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');
            $publicUrl = $baseUrl . '/uploads/' . $filename;

            return $this->json($response, [
                'success' => true,
                'message' => 'Imagen subida exitosamente',
                'data' => [
                    'url' => $publicUrl,
                    'filename' => $filename,
                    'size' => $file->getSize(),
                    'mime_type' => $mimeType
                ]
            ], 201);

        } catch (\Exception $e) {
            return $this->json($response, [
                'success' => false,
                'error' => 'Server Error',
                'message' => $_ENV['APP_DEBUG'] === 'true' ? $e->getMessage() : 'Error al guardar el archivo'
            ], 500);
        }
    }

    /**
     * Obtiene el mensaje de error de subida
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal en el servidor',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida',
            default => 'Error desconocido'
        };
    }

    /**
     * Helper para respuestas JSON
     */
    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}

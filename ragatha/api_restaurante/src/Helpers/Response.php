<?php

declare(strict_types=1);

namespace Amare\Api\Helpers;

class Response
{
    public static function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'Operación exitosa', int $statusCode = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    public static function error(string $message = 'Error en la operación', int $statusCode = 400, ?string $code = null): void
    {
        self::json([
            'success' => false,
            'message' => $message,
            'code' => $code
        ], $statusCode);
    }

    public static function validationError(array $errors, int $statusCode = 422): void
    {
        self::json([
            'success' => false,
            'message' => 'Error de validación',
            'errors' => $errors
        ], $statusCode);
    }

    public static function unauthorized(string $message = 'No autorizado'): void
    {
        self::json([
            'success' => false,
            'message' => $message
        ], 401);
    }

    public static function forbidden(string $message = 'Acceso denegado'): void
    {
        self::json([
            'success' => false,
            'message' => $message
        ], 403);
    }

    public static function notFound(string $message = 'Recurso no encontrado'): void
    {
        self::json([
            'success' => false,
            'message' => $message
        ], 404);
    }

    public static function serverError(string $message = 'Error interno del servidor'): void
    {
        self::json([
            'success' => false,
            'message' => $message
        ], 500);
    }
}
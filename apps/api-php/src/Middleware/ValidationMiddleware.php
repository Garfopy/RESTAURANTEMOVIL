<?php

declare(strict_types=1);

namespace Amare\Api\Middleware;

use Amare\Api\Helpers\Response;

class ValidationMiddleware
{
    private static $cachedInput = null;
    private static $inputDebug = [];

    public static function validate(array $rules, array $data): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleSet) {
            $ruleArray = explode('|', $ruleSet);
            
            foreach ($ruleArray as $rule) {
                if ($rule === 'required') {

                    if (!isset($data[$field])) {
                        $errors[$field][] = "El campo {$field} es requerido";
                        continue;
                    }

                    $value = $data[$field];

                    if (is_string($value) && trim($value) === '') {
                        $errors[$field][] = "El campo {$field} es requerido";
                        continue;
                    }
                }
                if (!isset($data[$field])) {
                    continue;
                }

                $value = $data[$field];

                if (str_starts_with($rule, 'email') && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "El campo {$field} debe ser un email válido";
                }

                if (str_starts_with($rule, 'min:')) {
                    $min = (int)substr($rule, 4);
                    if (strlen($value) < $min) {
                        $errors[$field][] = "El campo {$field} debe tener al menos {$min} caracteres";
                    }
                }

                if (str_starts_with($rule, 'max:')) {
                    $max = (int)substr($rule, 4);
                    if (strlen($value) > $max) {
                        $errors[$field][] = "El campo {$field} no debe exceder {$max} caracteres";
                    }
                }

                if (str_starts_with($rule, 'numeric') && !is_numeric($value)) {
                    $errors[$field][] = "El campo {$field} debe ser numérico";
                }

                if (str_starts_with($rule, 'integer') && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $errors[$field][] = "El campo {$field} debe ser un entero";
                }

                if ($rule === 'url' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[$field][] = "El campo {$field} debe ser una URL válida";
                }
            }
        }

        return $errors;
    }

    public static function validateRequest(array $rules): void
    {
        $data = self::getAllInput();

        $errors = self::validate($rules, $data);

        if (!empty($errors)) {
            Response::validationError($errors);
        }
    }

    public static function getInput(string $key, mixed $default = null): mixed
    {
        $input = self::getAllInput();
        return $input[$key] ?? $default;
    }

    public static function getAllInput(): array
    {
        if (self::$cachedInput !== null) {
            return self::$cachedInput;
        }

        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET') {
            self::$cachedInput = $_GET;
            self::$inputDebug = [
                'method' => $method,
                'source' => 'query',
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '',
                'query_keys' => array_keys($_GET),
                'parsed_keys' => array_keys(self::$cachedInput),
            ];
            return self::$cachedInput;
        }

        $input = $_POST;

        // Algunos servidores/proxies no conservan CONTENT_TYPE en PUT.
        // Si el body parece JSON, se intenta decodificar siempre que no sea multipart.
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        $isMultipart = stripos($contentType, 'multipart/form-data') !== false;
        $rawBody = file_get_contents('php://input');
        $trimmedBody = trim((string)$rawBody);
        $looksLikeJson = $trimmedBody !== '' && in_array($trimmedBody[0], ['{', '['], true);
        $jsonError = null;

        if (!$isMultipart && ($looksLikeJson || stripos($contentType, 'application/json') !== false)) {
            $json = json_decode($trimmedBody, true);
            $jsonError = json_last_error_msg();

            if (is_array($json)) {
                $input = array_merge($input, $json);
            }
        }

        // Adjuntar archivos
        if (!empty($_FILES)) {
            $input['_files'] = $_FILES;
        }

        self::$cachedInput = $input;
        self::$inputDebug = [
            'method' => $method,
            'source' => $looksLikeJson ? 'json_body' : 'post_or_empty',
            'content_type' => $contentType,
            'is_multipart' => $isMultipart,
            'raw_body_length' => strlen((string)$rawBody),
            'raw_body_preview' => substr($trimmedBody, 0, 500),
            'looks_like_json' => $looksLikeJson,
            'json_error' => $jsonError,
            'post_keys' => array_keys($_POST),
            'file_keys' => array_keys($_FILES),
            'parsed_keys' => array_keys(self::$cachedInput),
        ];
        return self::$cachedInput;
    }

    public static function getInputDebugInfo(): array
    {
        if (self::$cachedInput === null) {
            self::getAllInput();
        }

        return self::$inputDebug;
    }
}

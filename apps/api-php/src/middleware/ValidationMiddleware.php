<?php

declare(strict_types=1);

namespace Amare\Api\Middleware;

use Amare\Api\Helpers\Response;

class ValidationMiddleware
{
    public static function validate(array $rules, array $data): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleSet) {
            $ruleArray = explode('|', $ruleSet);
            
            foreach ($ruleArray as $rule) {
                if ($rule === 'required' && !isset($data[$field]) || (isset($data[$field]) && trim($data[$field]) === '')) {
                    $errors[$field][] = "El campo {$field} es requerido";
                    continue;
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
        $method = $_SERVER['REQUEST_METHOD'];
        $data = [];

        if ($method === 'GET') {
            $data = $_GET;
        } elseif ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
            $data = array_merge($_POST, (array)json_decode(file_get_contents('php://input'), true));
        }

        $errors = self::validate($rules, $data);

        if (!empty($errors)) {
            Response::validationError($errors);
        }
    }

    public static function getInput(string $key, mixed $default = null): mixed
    {
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method === 'GET') {
            return $_GET[$key] ?? $default;
        }
        
        if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
            $input = json_decode(file_get_contents('php://input'), true);
            return $input[$key] ?? $_POST[$key] ?? $default;
        }

        return $default;
    }

    public static function getAllInput(): array
    {
        $method = $_SERVER['REQUEST_METHOD'];
        
        if ($method === 'GET') {
            return $_GET;
        }
        
        if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
            return array_merge($_POST, (array)json_decode(file_get_contents('php://input'), true));
        }

        return [];
    }
}
<?php
/**
 * PasswordHelper
 * Generación de contraseñas seguras crypto-random
 */
class PasswordHelper
{
    /**
     * Genera una contraseña aleatoria segura
     *
     * @param int $length Longitud de la contraseña (mínimo 12, default 14)
     * @return string Contraseña generada
     */
    public static function generar(int $length = 14): string
    {
        if ($length < 12) {
            $length = 12;
        }

        // Conjuntos de caracteres
        $mayusculas = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $minusculas = 'abcdefghijklmnopqrstuvwxyz';
        $numeros    = '0123456789';
        $simbolos   = '!@#$%^&*-_+=';

        $todos = $mayusculas . $minusculas . $numeros . $simbolos;

        // Garantizar al menos 1 de cada tipo (obligatorio)
        $password = '';
        $password .= $mayusculas[random_int(0, strlen($mayusculas) - 1)];
        $password .= $minusculas[random_int(0, strlen($minusculas) - 1)];
        $password .= $numeros[random_int(0, strlen($numeros) - 1)];
        $password .= $simbolos[random_int(0, strlen($simbolos) - 1)];

        // Rellenar el resto con caracteres aleatorios del conjunto completo
        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $todos[random_int(0, strlen($todos) - 1)];
        }

        // Mezclar para que los obligatorios no estén siempre al inicio
        return str_shuffle($password);
    }

    /**
     * Valida que una contraseña cumpla con requisitos mínimos
     *
     * @param string $password Contraseña a validar
     * @return array ['valida' => bool, 'errores' => string[]]
     */
    public static function validar(string $password): array
    {
        $errores = [];

        if (strlen($password) < 8) {
            $errores[] = 'Debe tener al menos 8 caracteres';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errores[] = 'Debe incluir al menos una mayúscula';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errores[] = 'Debe incluir al menos una minúscula';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errores[] = 'Debe incluir al menos un número';
        }

        return [
            'valida'  => empty($errores),
            'errores' => $errores
        ];
    }
}

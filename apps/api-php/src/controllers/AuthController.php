<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\User;

class AuthController
{
    public function register(): void
    {
        $rules = [
            'name' => 'required|min:3|max:100',
            'password' => 'required|min:6|max:100'
        ];

        $errors = ValidationMiddleware::validate($rules, ValidationMiddleware::getAllInput());

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $input = ValidationMiddleware::getAllInput();
        $email = isset($input['email']) ? strtolower(trim((string)$input['email'])) : '';
        $phone = preg_replace('/\D+/', '', (string)($input['phone'] ?? $input['telefono'] ?? ''));

        if ($phone === '') {
            Response::validationError(['phone' => ['El telefono es requerido']]);
        }

        if (strlen($phone) < 10 || strlen($phone) > 15) {
            Response::validationError(['phone' => ['El telefono debe tener entre 10 y 15 digitos']]);
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::validationError(['email' => ['El email no es valido']]);
        }

        if ($email !== '' && User::existsByEmail($email)) {
            Response::error('El email ya está registrado', 409);
        }

        if (User::existsByPhone($phone)) {
            Response::error('El telefono ya esta registrado', 409);
        }

        $userId = User::create([
            'nombre' => $input['name'],
            'email' => $email !== '' ? $email : null,
            'password_hash' => password_hash($input['password'], PASSWORD_DEFAULT),
            'telefono' => $phone
        ]);

        if (!$userId) {
            Response::serverError('No se pudo crear el usuario');
        }

        $user = User::findById($userId);
        $token = AuthMiddleware::generateToken([
            'id' => $user['id'],
            'email' => $user['email'],
            'nombre' => $user['nombre'],
            'rol' => $user['rol'] ?? 'user'
        ]);

        unset($user['password_hash']);

        Response::success([
            'user' => $user,
            'token' => $token
        ], 'Usuario registrado exitosamente', 201);
    }

    public function login(): void
    {
        $rules = [
            'password' => 'required'
        ];

        $errors = ValidationMiddleware::validate($rules, ValidationMiddleware::getAllInput());

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $input = ValidationMiddleware::getAllInput();
        $identifier = trim((string)($input['identifier'] ?? $input['email'] ?? ''));

        if ($identifier === '') {
            Response::validationError(['identifier' => ['Correo o telefono es requerido']]);
        }

        $user = str_contains($identifier, '@')
            ? User::findByEmail(strtolower($identifier))
            : User::findByPhone(preg_replace('/\D+/', '', $identifier));

        if (!$user || !isset($user['password_hash']) || !User::verifyPassword($input['password'], $user['password_hash'])) {
            Response::unauthorized('Credenciales inválidas');
        }

        $token = AuthMiddleware::generateToken([
            'id' => $user['id'],
            'email' => $user['email'],
            'nombre' => $user['nombre'],
            'rol' => $user['rol'] ?? 'user'
        ]);

        unset($user['password_hash']);

        Response::success([
            'user' => $user,
            'token' => $token
        ], 'Login exitoso');
    }

    public function google(): void
    {
        $input = ValidationMiddleware::getAllInput();
        
        if (empty($input['token'])) {
            Response::validationError(['token' => ['El token de Google es requerido']]);
        }

        $googleClient = new \Google_Client(['client_id' => $_ENV['GOOGLE_CLIENT_ID']]);
        
        try {
            $payload = $googleClient->verifyIdToken($input['token']);
            
            if (!$payload) {
                Response::unauthorized('Token de Google inválido');
            }

            $googleId = $payload['sub'];
            $email = $payload['email'];
            $nombre = $payload['name'];

            $user = User::findByGoogleId($googleId);

            if (!$user) {
                $user = User::findByEmail($email);

                if ($user) {
                    User::updateGoogleId($user['id'], $googleId);
                } else {
                    $userId = User::create([
                        'nombre' => $nombre,
                        'email' => $email,
                        'google_id' => $googleId
                    ]);
                    $user = User::findById($userId);
                }
            }

            $token = AuthMiddleware::generateToken([
                'id' => $user['id'],
                'email' => $user['email'],
                'nombre' => $user['nombre'],
                'rol' => $user['rol'] ?? 'user'
            ]);

            unset($user['password_hash']);

            Response::success([
                'user' => $user,
                'token' => $token
            ], 'Login con Google exitoso');
        } catch (\Exception $e) {
            Response::serverError('Error al verificar token de Google');
        }
    }

    public function me(): void
    {
        $user = AuthMiddleware::authenticate();
        $userData = User::findById($user->id);

        if (!$userData) {
            Response::notFound('Usuario no encontrado');
        }

        Response::success(['user' => $userData]);
    }

    public function updatePassword(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $rules = [
            'current_password' => 'required',
            'new_password' => 'required|min:6'
        ];

        $errors = ValidationMiddleware::validate($rules, $input);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $userData = User::findById($user->id);

        if (!$userData || !isset($userData['password_hash']) || !User::verifyPassword($input['current_password'], $userData['password_hash'])) {
            Response::error('Contraseña actual inválida', 400);
        }

        if (!User::updatePassword($user->id, $input['new_password'])) {
            Response::serverError('No se pudo actualizar la contraseña');
        }

        Response::success(null, 'Contraseña actualizada exitosamente');
    }
}

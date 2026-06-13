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
            'email' => 'required|email|max:100',
            'password' => 'required|min:6|max:100'
        ];

        $errors = ValidationMiddleware::validate($rules, ValidationMiddleware::getAllInput());

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $input = ValidationMiddleware::getAllInput();

        if (User::existsByEmail($input['email'])) {
            Response::error('El email ya está registrado', 409);
        }

        $userId = User::create([
            'nombre' => $input['name'],
            'email' => $input['email'],
            'password_hash' => password_hash($input['password'], PASSWORD_DEFAULT),
            'telefono' => $input['phone'] ?? null
        ]);

        if (!$userId) {
            Response::serverError('No se pudo crear el usuario');
        }

        $user = User::findById($userId);
        $token = AuthMiddleware::generateToken([
            'id' => $user['id'],
            'email' => $user['email'],
            'nombre' => $user['nombre']
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
            'email' => 'required|email',
            'password' => 'required'
        ];

        $errors = ValidationMiddleware::validate($rules, ValidationMiddleware::getAllInput());

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $input = ValidationMiddleware::getAllInput();
        $user = User::findByEmail($input['email']);

        if (!$user || !isset($user['password_hash']) || !User::verifyPassword($input['password'], $user['password_hash'])) {
            Response::unauthorized('Credenciales inválidas');
        }

        $token = AuthMiddleware::generateToken([
            'id' => $user['id'],
            'email' => $user['email'],
            'nombre' => $user['nombre']
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
                'nombre' => $user['nombre']
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
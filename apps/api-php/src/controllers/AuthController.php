<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\User;

class AuthController
{
    private static function normalizePhone(?string $value): string
    {
        return preg_replace('/\D+/', '', (string)($value ?? ''));
    }

    /**
     * @return array<int, string>
     */
    private static function phoneLookupCandidates(string $phone): array
    {
        $candidates = [$phone];

        if (strlen($phone) === 10) {
            $candidates[] = '52' . $phone;
        }

        if (substr($phone, 0, 2) === '52' && strlen($phone) === 12) {
            $candidates[] = substr($phone, 2);
        }

        if (substr($phone, 0, 1) === '1' && strlen($phone) === 11) {
            $candidates[] = substr($phone, 1);
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private static function findUserByPhoneCandidates(string $phone): ?array
    {
        foreach (self::phoneLookupCandidates($phone) as $candidate) {
            $user = User::findByPhone($candidate);
            if ($user) {
                return $user;
            }
        }

        return null;
    }

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
        $phone = self::normalizePhone($input['phone'] ?? $input['telefono'] ?? '');

        if (strlen($phone) === 10) {
            $phone = '52' . $phone;
        }

        if ($phone === '') {
            Response::validationError(['phone' => ['El teléfono es requerido']]);
        }

        if (strlen($phone) < 10 || strlen($phone) > 15) {
            Response::validationError(['phone' => ['El teléfono debe tener entre 10 y 15 dígitos']]);
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::validationError(['email' => ['El correo electrónico no es válido']]);
        }

        if ($email !== '' && User::existsByEmail($email)) {
            Response::error('El correo electrónico ya está registrado', 409);
        }

        if (self::findUserByPhoneCandidates($phone)) {
            Response::error('El teléfono ya está registrado', 409);
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
            'rol' => $user['rol'] ?? 'user',
            'auth_source' => 'mobile'
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
            Response::validationError(['identifier' => ['Correo o teléfono es requerido']]);
        }

        $isEmail = str_contains($identifier, '@');
        $normalizedEmail = $isEmail ? strtolower($identifier) : '';

        if ($isEmail) {
            $staff = User::findStaffByEmail($normalizedEmail);
            if ($staff && isset($staff['password_hash']) && User::verifyPassword($input['password'], $staff['password_hash'])) {
                $token = AuthMiddleware::generateToken([
                    'id' => $staff['id'],
                    'email' => $staff['email'],
                    'nombre' => $staff['nombre'],
                    'rol' => $staff['rol'] ?? 'staff',
                    'auth_source' => 'staff',
                    'staff_role_slug' => $staff['staff_role_slug'] ?? null,
                    'current_restaurante_id' => $staff['current_restaurante_id'] ?? null,
                ]);

                unset($staff['password_hash']);

                Response::success([
                    'user' => $staff,
                    'token' => $token
                ], 'Inicio de sesion exitoso');
            }
        }

        $user = $isEmail
            ? User::findByEmail($normalizedEmail)
            : self::findUserByPhoneCandidates(self::normalizePhone($identifier));

        if (!$user || !isset($user['password_hash']) || !User::verifyPassword($input['password'], $user['password_hash'])) {
            Response::unauthorized('Credenciales inválidas');
        }

        $token = AuthMiddleware::generateToken([
            'id' => $user['id'],
            'email' => $user['email'],
            'nombre' => $user['nombre'],
            'rol' => $user['rol'] ?? 'user',
            'auth_source' => 'mobile'
        ]);

        unset($user['password_hash']);

        Response::success([
            'user' => $user,
            'token' => $token
        ], 'Inicio de sesión exitoso');
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
                'rol' => $user['rol'] ?? 'user',
                'auth_source' => 'mobile'
            ]);

            unset($user['password_hash']);

            Response::success([
                'user' => $user,
                'token' => $token
            ], 'Inicio de sesión con Google exitoso');
        } catch (\Exception $e) {
            Response::serverError('Error al verificar token de Google');
        }
    }

    public function me(): void
    {
        $user = AuthMiddleware::authenticate();
        $userData = User::findAuthenticated(
            (int)$user->id,
            isset($user->auth_source) ? (string)$user->auth_source : null
        );

        if (!$userData) {
            Response::notFound('Usuario no encontrado');
        }

        Response::success(['user' => $userData]);
    }

    public function updatePassword(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        if (($user->auth_source ?? 'mobile') === 'staff') {
            Response::error('Actualiza tu contrasena desde el panel web.', 400);
        }

        $rules = [
            'current_password' => 'required',
            'new_password' => 'required|min:6'
        ];

        $errors = ValidationMiddleware::validate($rules, $input);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $userData = User::findByIdWithPassword((int)$user->id);

        if (!$userData || !isset($userData['password_hash']) || !User::verifyPassword($input['current_password'], $userData['password_hash'])) {
            Response::error('Contraseña actual inválida', 400);
        }

        if (!User::updatePassword($user->id, $input['new_password'])) {
            Response::serverError('No se pudo actualizar la contraseña');
        }

        Response::success(null, 'Contraseña actualizada exitosamente');
    }

    public function requestPasswordReset(): void
    {
        $input = ValidationMiddleware::getAllInput();
        $identifier = trim((string)($input['identifier'] ?? $input['email'] ?? $input['phone'] ?? ''));

        if ($identifier === '') {
            Response::validationError(['identifier' => ['Correo o telefono es requerido']]);
        }

        $user = $this->findMobileUserByIdentifier($identifier);
        $debugPayload = [];

        if ($user) {
            $code = (string)random_int(100000, 999999);
            if (!User::storePasswordResetCode((int)$user['id'], $code, 15)) {
                Response::serverError('No se pudo preparar la recuperacion. Ejecuta la migracion 047.');
            }

            $email = strtolower(trim((string)($user['email'] ?? '')));
            if ($email !== '') {
                $this->sendPasswordResetEmail($email, (string)($user['nombre'] ?? 'Amare'), $code);
            }

            if ($this->isDebugMode()) {
                $debugPayload['reset_code'] = $code;
            }
        }

        Response::success(array_merge([
            'expires_in_minutes' => 15,
        ], $debugPayload), 'Si encontramos una cuenta, te enviaremos un codigo para restablecer tu contrasena.');
    }

    public function confirmPasswordReset(): void
    {
        $input = ValidationMiddleware::getAllInput();
        $identifier = trim((string)($input['identifier'] ?? $input['email'] ?? $input['phone'] ?? ''));
        $code = preg_replace('/\D+/', '', (string)($input['code'] ?? $input['reset_code'] ?? ''));
        $newPassword = (string)($input['new_password'] ?? $input['password'] ?? '');

        $errors = [];
        if ($identifier === '') {
            $errors['identifier'] = ['Correo o telefono es requerido'];
        }
        if (strlen($code) !== 6) {
            $errors['code'] = ['Ingresa el codigo de 6 digitos'];
        }
        if (strlen($newPassword) < 6) {
            $errors['new_password'] = ['La nueva contrasena debe tener al menos 6 caracteres'];
        }

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $user = $this->findMobileUserByIdentifier($identifier);
        if (!$user || !User::hasValidPasswordResetCode($user, $code)) {
            Response::error('Codigo invalido o expirado', 400, 'PASSWORD_RESET_INVALID');
        }

        if (!User::updatePassword((int)$user['id'], $newPassword)) {
            Response::serverError('No se pudo actualizar la contrasena');
        }

        User::clearPasswordResetCode((int)$user['id']);

        Response::success(null, 'Contrasena actualizada exitosamente');
    }

    private function findMobileUserByIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        if (str_contains($identifier, '@')) {
            return User::findByEmail(strtolower($identifier));
        }

        return self::findUserByPhoneCandidates(self::normalizePhone($identifier));
    }

    private function sendPasswordResetEmail(string $email, string $name, string $code): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $from = trim((string)($_ENV['MAIL_FROM'] ?? $_ENV['APP_MAIL_FROM'] ?? 'no-reply@amarerestaurant.club'));
        $safeName = trim($name) !== '' ? trim($name) : 'Amare';
        $subject = 'Codigo para restablecer tu contrasena';
        $body = "Hola {$safeName},\n\n"
            . "Tu codigo para restablecer la contrasena de Amare es: {$code}\n\n"
            . "Este codigo expira en 15 minutos. Si no lo solicitaste, puedes ignorar este mensaje.\n";
        $headers = [
            'From: Amare <' . $from . '>',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        try {
            @mail($email, $subject, $body, implode("\r\n", $headers));
        } catch (\Throwable $exception) {
            error_log('AuthController::sendPasswordResetEmail ERROR: ' . $exception->getMessage());
        }
    }

    private function isDebugMode(): bool
    {
        $env = strtolower(trim((string)($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'production')));
        $debugValue = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: false;
        return $env !== 'production' || filter_var($debugValue, FILTER_VALIDATE_BOOLEAN);
    }
}

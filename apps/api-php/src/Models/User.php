<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class User
{
    public static function create(array $data): int
    {
        $columns = ['nombre', 'email', 'password_hash', 'telefono', 'foto_url', 'google_id', 'created_at'];
        $values = [':nombre', ':email', ':password_hash', ':telefono', ':foto_url', ':google_id', 'NOW()'];
        $params = [
            ':nombre' => $data['nombre'],
            ':email' => $data['email'],
            ':password_hash' => $data['password_hash'] ?? null,
            ':telefono' => $data['telefono'] ?? null,
            ':foto_url' => $data['foto_url'] ?? null,
            ':google_id' => $data['google_id'] ?? null
        ];

        if (self::columnExists('mobile_usuarios', 'fecha_nacimiento')) {
            $columns[] = 'fecha_nacimiento';
            $values[] = ':fecha_nacimiento';
            $params[':fecha_nacimiento'] = $data['fecha_nacimiento'] ?? null;
        }

        if (self::columnExists('mobile_usuarios', 'terms_accepted_at') && !empty($data['terms_accepted_at'])) {
            $columns[] = 'terms_accepted_at';
            $values[] = ':terms_accepted_at';
            $params[':terms_accepted_at'] = $data['terms_accepted_at'];
        }

        if (self::columnExists('mobile_usuarios', 'onboarding_completed_at') && !empty($data['onboarding_completed_at'])) {
            $columns[] = 'onboarding_completed_at';
            $values[] = ':onboarding_completed_at';
            $params[':onboarding_completed_at'] = $data['onboarding_completed_at'];
        }

        $sql = 'INSERT INTO mobile_usuarios (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', $values) . ')';

        return Database::execute($sql, $params);
    }

    public static function findByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM mobile_usuarios WHERE email = :email LIMIT 1";
        return Database::queryOne($sql, [':email' => $email]);
    }

    public static function findStaffByEmail(string $email): ?array
    {
        if (!self::tableExists('usuarios') || !self::tableExists('rest_staff')) {
            return null;
        }

        $hasRoles = self::tableExists('roles');
        $roleJoin = $hasRoles ? 'LEFT JOIN roles r ON r.id = u.rol_id' : '';
        $roleField = $hasRoles ? ", COALESCE(rs.rol_slug, r.slug) AS staff_role_slug" : ", rs.rol_slug AS staff_role_slug";

        $sql = "SELECT u.id, u.nombre, u.email, u.telefono, u.avatar AS foto_url,
                       u.password AS password_hash, u.activo, u.created_at,
                       rs.restaurante_id AS current_restaurante_id,
                       rs.codigo AS staff_codigo,
                       rs.rol_slug AS rest_staff_role_slug
                       {$roleField}
                  FROM usuarios u
                 JOIN rest_staff rs ON rs.usuario_id = u.id
                  {$roleJoin}
                 WHERE LOWER(u.email) = LOWER(:email)
                   AND u.activo = 1
                   AND rs.activo = 1
                   AND rs.rol_slug IN ('mesero','hostess','hostes','host','anfitrion','anfitriona','portero')
              ORDER BY CASE
                    WHEN rs.rol_slug = 'mesero' THEN 0
                    WHEN rs.rol_slug IN ('hostess','hostes','host','anfitrion','anfitriona','portero') THEN 1
                    ELSE 2
                  END,
                  rs.id ASC
                 LIMIT 1";

        $staff = Database::queryOne($sql, [':email' => $email]);
        return $staff ? self::normalizeStaffUser($staff) : null;
    }

    public static function findByPhone(string $phone): ?array
    {
        $sql = "SELECT * FROM mobile_usuarios WHERE telefono = :telefono LIMIT 1";
        return Database::queryOne($sql, [':telefono' => $phone]);
    }

    public static function findById(int $id): ?array
    {
        $fechaNacimientoField = self::columnExists('mobile_usuarios', 'fecha_nacimiento')
            ? 'fecha_nacimiento'
            : 'NULL AS fecha_nacimiento';
        $onboardingCompletedField = self::columnExists('mobile_usuarios', 'onboarding_completed_at')
            ? 'onboarding_completed_at'
            : 'NULL AS onboarding_completed_at';
        $termsAcceptedField = self::columnExists('mobile_usuarios', 'terms_accepted_at')
            ? 'terms_accepted_at'
            : 'NULL AS terms_accepted_at';
        $marketingOptInField = self::columnExists('mobile_usuarios', 'marketing_opt_in')
            ? 'marketing_opt_in'
            : '0 AS marketing_opt_in';

        $sql = "SELECT id, nombre, email, rol, telefono, {$fechaNacimientoField}, {$onboardingCompletedField},
                       {$termsAcceptedField}, {$marketingOptInField}, foto_url, google_id, activo, created_at, updated_at,
                       edad, genero, sexualidad, descripcion AS biografia, intereses AS gustos,
                       que_busca, redes_sociales, is_social_active, current_restaurante_id, mesa
                FROM mobile_usuarios WHERE id = :id LIMIT 1";
        return Database::queryOne($sql, [':id' => $id]);
    }

    public static function findByIdWithPassword(int $id): ?array
    {
        $sql = "SELECT * FROM mobile_usuarios WHERE id = :id LIMIT 1";
        return Database::queryOne($sql, [':id' => $id]);
    }

    public static function findAuthenticated(int $id, ?string $authSource = null): ?array
    {
        if ($authSource === 'staff') {
            return self::findStaffById($id);
        }

        $user = self::findById($id);
        if ($user) {
            if (array_key_exists('activo', $user) && (int)$user['activo'] !== 1) {
                return null;
            }

            if (self::shouldPreferStaffIdentity($user)) {
                $staff = self::findStaffByEmail((string)$user['email']);
                if ($staff) {
                    return $staff;
                }
            }

            $user['auth_source'] = 'mobile';
            return $user;
        }

        return $authSource === null ? self::findStaffById($id) : null;
    }

    public static function findStaffById(int $id): ?array
    {
        if (!self::tableExists('usuarios') || !self::tableExists('rest_staff')) {
            return null;
        }

        $hasRoles = self::tableExists('roles');
        $roleJoin = $hasRoles ? 'LEFT JOIN roles r ON r.id = u.rol_id' : '';
        $roleField = $hasRoles ? ", COALESCE(rs.rol_slug, r.slug) AS staff_role_slug" : ", rs.rol_slug AS staff_role_slug";

        $sql = "SELECT u.id, u.nombre, u.email, u.telefono, u.avatar AS foto_url,
                       u.activo, u.created_at,
                       rs.restaurante_id AS current_restaurante_id,
                       rs.codigo AS staff_codigo,
                       rs.rol_slug AS rest_staff_role_slug
                       {$roleField}
                  FROM usuarios u
                 JOIN rest_staff rs ON rs.usuario_id = u.id
                  {$roleJoin}
                 WHERE u.id = :id
                   AND u.activo = 1
                   AND rs.activo = 1
                   AND rs.rol_slug IN ('mesero','hostess','hostes','host','anfitrion','anfitriona','portero')
              ORDER BY CASE
                    WHEN rs.rol_slug = 'mesero' THEN 0
                    WHEN rs.rol_slug IN ('hostess','hostes','host','anfitrion','anfitriona','portero') THEN 1
                    ELSE 2
                  END,
                  rs.id ASC
                 LIMIT 1";

        $staff = Database::queryOne($sql, [':id' => $id]);
        return $staff ? self::normalizeStaffUser($staff) : null;
    }

    /**
     * Listar todos los usuarios (para panel de administrador).
     * Solo devuelve campos seguros (sin password_hash).
     */
    public static function getAll(int $limit = 100, int $offset = 0, ?string $search = null): array
    {
        $params = [];
        $where = 'WHERE activo = 1';

        if ($search !== null && $search !== '') {
            $where .= ' AND (nombre LIKE :search OR email LIKE :search2)';
            $params[':search']  = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }

        $sql = "SELECT id, nombre, email, rol, telefono, foto_url, activo, created_at
                FROM mobile_usuarios
                {$where}
                ORDER BY nombre ASC
                LIMIT :limit OFFSET :offset";

        $params[':limit']  = $limit;
        $params[':offset'] = $offset;

        return Database::query($sql, $params);
    }

    /**
     * Contar usuarios activos (para paginación en admin).
     */
    public static function countAll(?string $search = null): int
    {
        $params = [];
        $where = 'WHERE activo = 1';

        if ($search !== null && $search !== '') {
            $where .= ' AND (nombre LIKE :search OR email LIKE :search2)';
            $params[':search']  = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }

        $sql = "SELECT COUNT(*) as total FROM mobile_usuarios {$where}";
        $result = Database::queryOne($sql, $params);
        return (int)($result['total'] ?? 0);
    }

    public static function findByGoogleId(string $googleId): ?array
    {
        $sql = "SELECT * FROM mobile_usuarios WHERE google_id = :google_id LIMIT 1";
        return Database::queryOne($sql, [':google_id' => $googleId]);
    }

    public static function update(int $id, array $data): bool
    {
        $setClause = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            if ($value !== null) {
                $setClause[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }

        if (empty($setClause)) {
            return false;
        }

        $setClause[] = "updated_at = NOW()";
        $sql = "UPDATE mobile_usuarios SET " . implode(', ', $setClause) . " WHERE id = :id";
        
        return Database::rowCount($sql, $params) > 0;
    }

    public static function updatePassword(int $id, string $password): bool
    {
        $sql = "UPDATE mobile_usuarios SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id";
        return Database::rowCount($sql, [
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':id' => $id
        ]) > 0;
    }

    public static function storePasswordResetCode(int $id, string $code, int $ttlMinutes = 15): bool
    {
        if (
            !self::columnExists('mobile_usuarios', 'password_reset_code_hash') ||
            !self::columnExists('mobile_usuarios', 'password_reset_expires_at') ||
            !self::columnExists('mobile_usuarios', 'password_reset_requested_at')
        ) {
            return false;
        }

        $expiresAt = date('Y-m-d H:i:s', time() + (max(1, $ttlMinutes) * 60));
        $sql = "UPDATE mobile_usuarios
                   SET password_reset_code_hash = :code_hash,
                       password_reset_expires_at = :expires_at,
                       password_reset_requested_at = NOW(),
                       updated_at = NOW()
                 WHERE id = :id";

        return Database::rowCount($sql, [
            ':code_hash' => password_hash($code, PASSWORD_DEFAULT),
            ':expires_at' => $expiresAt,
            ':id' => $id
        ]) > 0;
    }

    public static function clearPasswordResetCode(int $id): bool
    {
        if (!self::columnExists('mobile_usuarios', 'password_reset_code_hash')) {
            return false;
        }

        $set = [
            'password_reset_code_hash = NULL',
        ];

        if (self::columnExists('mobile_usuarios', 'password_reset_expires_at')) {
            $set[] = 'password_reset_expires_at = NULL';
        }
        if (self::columnExists('mobile_usuarios', 'password_reset_requested_at')) {
            $set[] = 'password_reset_requested_at = NULL';
        }
        if (self::columnExists('mobile_usuarios', 'updated_at')) {
            $set[] = 'updated_at = NOW()';
        }

        $sql = 'UPDATE mobile_usuarios SET ' . implode(', ', $set) . ' WHERE id = :id';
        return Database::rowCount($sql, [':id' => $id]) > 0;
    }

    public static function hasValidPasswordResetCode(array $user, string $code): bool
    {
        $hash = (string)($user['password_reset_code_hash'] ?? '');
        $expiresAt = (string)($user['password_reset_expires_at'] ?? '');

        if ($hash === '' || $expiresAt === '') {
            return false;
        }

        if (strtotime($expiresAt) === false || strtotime($expiresAt) < time()) {
            return false;
        }

        return password_verify($code, $hash);
    }

    public static function updateGoogleId(int $id, string $googleId): bool
    {
        $sql = "UPDATE mobile_usuarios SET google_id = :google_id, updated_at = NOW() WHERE id = :id";
        return Database::rowCount($sql, [
            ':google_id' => $googleId,
            ':id' => $id
        ]) > 0;
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function existsByEmail(string $email, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM mobile_usuarios WHERE email = :email";
        $params = [':email' => $email];
        
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $result = Database::queryOne($sql, $params);
        return ($result['count'] ?? 0) > 0;
    }

    public static function existsByPhone(string $phone, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM mobile_usuarios WHERE telefono = :telefono";
        $params = [':telefono' => $phone];

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $result = Database::queryOne($sql, $params);
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Valida duplicados aunque el telefono este guardado con o sin lada MX.
     */
    public static function existsByAnyPhoneCandidate(string $phone, ?int $excludeId = null): bool
    {
        foreach (self::phoneLookupCandidates($phone) as $candidate) {
            if (self::existsByPhone($candidate, $excludeId)) {
                return true;
            }
        }

        return false;
    }

    public static function deleteIncompleteGoogleOnboarding(int $id): bool
    {
        $user = self::findByIdWithPassword($id);
        if (!$user) {
            return false;
        }

        $isIncompleteGoogleUser =
            trim((string)($user['google_id'] ?? '')) !== '' &&
            trim((string)($user['password_hash'] ?? '')) === '' &&
            trim((string)($user['telefono'] ?? '')) === '' &&
            trim((string)($user['terms_accepted_at'] ?? '')) === '' &&
            trim((string)($user['onboarding_completed_at'] ?? '')) === '';

        if (!$isIncompleteGoogleUser || self::hasUserActivity($id)) {
            return false;
        }

        return Database::rowCount(
            'DELETE FROM mobile_usuarios WHERE id = :id LIMIT 1',
            [':id' => $id]
        ) > 0;
    }

    /**
     * @return array<int, string>
     */
    private static function phoneLookupCandidates(string $phone): array
    {
        $phone = preg_replace('/\D+/', '', $phone);
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

    private static function hasUserActivity(int $id): bool
    {
        $checks = [
            ['rest_pedidos', 'mobile_usuario_id'],
            ['mobile_direcciones', 'usuario_id'],
            ['mobile_favoritos', 'usuario_id'],
            ['mobile_push_tokens', 'usuario_id'],
            ['amare_wallets', 'user_id'],
            ['amare_wallets', 'usuario_id'],
            ['amare_wallet_transactions', 'user_id'],
            ['amare_wallet_transactions', 'usuario_id'],
            ['social_likes', 'liker_user_id'],
            ['social_likes', 'liked_user_id'],
            ['social_gift_orders', 'sender_user_id'],
            ['social_gift_orders', 'recipient_user_id'],
            ['social_account_notifications', 'user_id'],
            ['invoice_requests', 'mobile_usuario_id'],
        ];

        foreach ($checks as [$table, $column]) {
            if (self::countUserReferences($table, $column, $id) > 0) {
                return true;
            }
        }

        return false;
    }

    private static function countUserReferences(string $tableName, string $columnName, int $id): int
    {
        if (!self::tableExists($tableName) || !self::columnExists($tableName, $columnName)) {
            return 0;
        }

        $row = Database::queryOne(
            "SELECT COUNT(*) AS total FROM `{$tableName}` WHERE `{$columnName}` = :id",
            [':id' => $id]
        );

        return (int)($row['total'] ?? 0);
    }

    private static function normalizeStaffUser(array $staff): array
    {
        $roleSlug = strtolower(trim((string)($staff['staff_role_slug'] ?? $staff['rest_staff_role_slug'] ?? '')));
        $appRole = self::normalizeStaffRoleForApp($roleSlug);
        $fullName = trim((string)($staff['nombre'] ?? ''));

        return [
            'id' => (int)$staff['id'],
            'nombre' => $fullName,
            'email' => $staff['email'] ?? '',
            'rol' => $appRole,
            'telefono' => $staff['telefono'] ?? null,
            'fecha_nacimiento' => null,
            'onboarding_completed_at' => null,
            'terms_accepted_at' => null,
            'marketing_opt_in' => false,
            'requires_onboarding' => false,
            'foto_url' => $staff['foto_url'] ?? null,
            'password_hash' => $staff['password_hash'] ?? null,
            'activo' => (bool)($staff['activo'] ?? true),
            'created_at' => $staff['created_at'] ?? '',
            'current_restaurante_id' => isset($staff['current_restaurante_id']) && $staff['current_restaurante_id'] !== null
                ? (int)$staff['current_restaurante_id']
                : null,
            'staff_codigo' => $staff['staff_codigo'] ?? null,
            'staff_role_slug' => $roleSlug,
            'auth_source' => 'staff',
            'google_id' => null,
            'social_photos' => [],
            'is_social_active' => false,
            'modo_social' => false,
            'mesa' => null,
        ];
    }

    private static function normalizeStaffRoleForApp(string $roleSlug): string
    {
        return match ($roleSlug) {
            'mesero' => 'mesero',
            'hostess', 'hostes', 'host', 'anfitrion', 'anfitriona', 'portero' => 'hostess',
            'admin', 'admin_restaurante', 'admin_local', 'comprador' => 'admin',
            default => $roleSlug !== '' ? $roleSlug : 'staff',
        };
    }

    private static function shouldPreferStaffIdentity(array $user): bool
    {
        $role = strtolower(trim((string)($user['rol'] ?? '')));
        $email = trim((string)($user['email'] ?? ''));

        return $email !== '' && in_array($role, [
            'mesero',
            'hostess',
            'hostes',
            'host',
            'anfitrion',
            'anfitriona',
            'portero',
            'staff',
        ], true);
    }

    private static function tableExists(string $tableName): bool
    {
        $exists = Database::query("SHOW TABLES LIKE '{$tableName}'");
        return !empty($exists);
    }

    private static function columnExists(string $tableName, string $columnName): bool
    {
        $row = Database::queryOne(
            'SELECT COUNT(*) AS total
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table_name
                AND COLUMN_NAME = :column_name',
            [
                ':table_name' => $tableName,
                ':column_name' => $columnName,
            ]
        );

        return (int)($row['total'] ?? 0) > 0;
    }
}

<?php
class UsuarioModel extends BaseModel
{
    protected string $table = 'usuarios';

    public function getByEmail(string $email): ?array
    {
        return $this->queryOne(
            'SELECT u.*, r.slug AS rol_slug, r.nombre AS rol_nombre,
                    CONCAT(u.nombre, " ", u.apellido_paterno) AS nombre_completo
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
              WHERE u.email = ?',
            [$email]
        );
    }

    public function getByEmpresa(int $empresaId): array
    {
        return $this->query(
            'SELECT u.*, r.slug AS rol_slug, r.nombre AS rol_nombre,
                    CONCAT(u.nombre, " ", u.apellido_paterno) AS nombre_completo
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
              WHERE u.empresa_id = ?
              ORDER BY u.activo DESC, r.id, u.nombre',
            [$empresaId]
        );
    }

    public function getComprador(int $id, int $empresaId): ?array
    {
        return $this->queryOne(
            "SELECT u.id, u.nombre, u.apellido_paterno, u.email
               FROM usuarios u JOIN roles r ON r.id = u.rol_id
              WHERE u.id = ? AND u.empresa_id = ? AND r.slug = 'comprador'",
            [$id, $empresaId]
        );
    }

    public function getByRolEmpresa(string $rolSlug, int $empresaId): array
    {
        return $this->query(
            'SELECT u.*, r.slug AS rol_slug
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
              WHERE r.slug = ? AND u.empresa_id = ? AND u.activo = 1
              ORDER BY u.nombre',
            [$rolSlug, $empresaId]
        );
    }

    /** Roles que un admin_empresa puede crear para su empresa */
    public function rolesPermitidosPorAdminEmpresa(): array
    {
        return $this->query(
            "SELECT * FROM roles WHERE slug IN ('supervisor','comprador','repartidor')"
        );
    }

    /** Roles que superadmin puede crear (solo admin_empresa) */
    public function rolesPermitidosPorAdmin(): array
    {
        return $this->query(
            "SELECT * FROM roles WHERE slug = 'admin_empresa'"
        );
    }

    /** Roles que el superadmin puede crear (admin, admin_empresa y otro superadmin) */
    public function rolesPermitidosPorSuperAdmin(): array
    {
        return $this->query(
            "SELECT * FROM roles WHERE slug IN ('superadmin','admin','admin_empresa') ORDER BY id"
        );
    }

    public function existeEmail(string $email, ?int $excluirId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM usuarios WHERE email = ?';
        $params = [$email];
        if ($excluirId) { $sql .= ' AND id != ?'; $params[] = $excluirId; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function listadoConRol(array $filtros = [], int $page = 1): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtros['empresa_id'])) {
            $where[]  = 'u.empresa_id = ?';
            $params[] = $filtros['empresa_id'];
        }
        if (!empty($filtros['rol_slug'])) {
            $where[]  = 'r.slug = ?';
            $params[] = $filtros['rol_slug'];
        }
        if (!empty($filtros['buscar'])) {
            $where[]  = '(u.nombre LIKE ? OR u.email LIKE ?)';
            $params[] = '%' . $filtros['buscar'] . '%';
            $params[] = '%' . $filtros['buscar'] . '%';
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT u.id, u.nombre, u.apellido_paterno, u.email, u.telefono,
                       u.activo, u.created_at, r.slug AS rol_slug, r.nombre AS rol_nombre,
                       e.razon_social AS empresa_nombre
                  FROM usuarios u
                  JOIN roles r ON r.id = u.rol_id
             LEFT JOIN empresas e ON e.id = u.empresa_id
                  $sqlWhere
              ORDER BY u.created_at DESC";

        return $this->paginate($sql, $params, $page);
    }

    public function crear(array $data, string $password): int
    {
        $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        return $this->insert($data);
    }

    public function getConRol(int $id): ?array
    {
        return $this->queryOne(
            'SELECT u.*, r.slug AS rol_slug, r.nombre AS rol_nombre
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
              WHERE u.id = ?',
            [$id]
        );
    }

    public function getRolPorSlug(string $slug): ?array
    {
        return $this->queryOne('SELECT id, slug, nombre FROM roles WHERE slug = ?', [$slug]);
    }

    public function getRolPorId(int $rolId): ?array
    {
        return $this->queryOne('SELECT * FROM roles WHERE id = ?', [$rolId]);
    }

    public function getRepartidoresGlobal(): array
    {
        return $this->query(
            "SELECT u.id, u.nombre, u.apellido_paterno, e.razon_social AS empresa_nombre
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
               JOIN empresas e ON e.id = u.empresa_id
              WHERE r.slug = 'repartidor' AND u.activo = 1
              ORDER BY e.razon_social, u.nombre"
        );
    }

    public function getRepartidoresPorEmpresa(int $empresaId): array
    {
        return $this->getByRolEmpresa('repartidor', $empresaId);
    }

    public function compradorValido(int $compradorId, int $empresaId): bool
    {
        return (bool)$this->queryOne(
            "SELECT u.id FROM usuarios u JOIN roles r ON r.id = u.rol_id
              WHERE u.id = ? AND u.empresa_id = ? AND r.slug = 'comprador' AND u.activo = 1",
            [$compradorId, $empresaId]
        );
    }

    public function getByResetToken(string $token): ?array
    {
        return $this->queryOne(
            'SELECT id, nombre, apellido_paterno, email, email_verificado, activo, token_expira
               FROM usuarios
              WHERE token_verificacion = ? AND activo = 1
              LIMIT 1',
            [$token]
        );
    }

    public function actualizarPassword(int $id, string $newPassword): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'UPDATE usuarios
                SET password = ?, token_verificacion = NULL, token_expira = NULL
              WHERE id = ?'
        );
        $stmt->execute([password_hash($newPassword, PASSWORD_BCRYPT), $id]);
    }
}

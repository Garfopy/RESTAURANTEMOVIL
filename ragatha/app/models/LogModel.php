<?php
class LogModel extends BaseModel
{
    protected string $table = 'action_logs';

    public function registrar(
        ?int    $usuarioId,
        string  $rol        = '',
        ?int    $empresaId  = null,
        string  $accion     = '',
        string  $modulo     = '',
        string  $descripcion = ''
    ): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        try {
            $this->execute(
                'INSERT INTO action_logs (usuario_id, rol, empresa_id, accion, modulo, descripcion, ip)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$usuarioId, $rol, $empresaId, $accion, $modulo, $descripcion, $ip]
            );
        } catch (\Throwable $e) {
            // La auditoría es opcional: si la tabla no existe (p.ej. deploy mínimo
            // de restaurante) no debemos romper el flujo principal. Solo logueamos.
            error_log('[LogModel::registrar] ' . $e->getMessage());
        }
    }

    public function registrarError(string $nivel, string $mensaje, string $archivo = '', int $linea = 0, ?array $contexto = null): void
    {
        try {
            $this->execute(
                'INSERT INTO error_logs (nivel, mensaje, archivo, linea, contexto)
                 VALUES (?, ?, ?, ?, ?)',
                [$nivel, $mensaje, $archivo, $linea, $contexto ? json_encode($contexto) : null]
            );
        } catch (\Throwable $e) {
            error_log('[LogModel::registrarError] ' . $e->getMessage());
        }
    }

    public function getBitacora(array $filtros = [], int $page = 1): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtros['modulo'])) {
            $where[]  = 'al.modulo = ?';
            $params[] = $filtros['modulo'];
        }
        if (!empty($filtros['usuario_id'])) {
            $where[]  = 'al.usuario_id = ?';
            $params[] = $filtros['usuario_id'];
        }
        if (!empty($filtros['fecha'])) {
            $where[]  = 'DATE(al.created_at) = ?';
            $params[] = $filtros['fecha'];
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT al.*, u.nombre AS usuario_nombre, u.email
                  FROM action_logs al
             LEFT JOIN usuarios u ON u.id = al.usuario_id
                  $sqlWhere
              ORDER BY al.created_at DESC";
        return $this->paginate($sql, $params, $page);
    }

    public function getErrores(int $page = 1): array
    {
        $sql = 'SELECT * FROM error_logs ORDER BY created_at DESC';
        return $this->paginate($sql, [], $page);
    }

    public function getAccesosUsuario(int $usuarioId, int $limite = 10): array
    {
        return $this->query(
            "SELECT accion, ip, descripcion, created_at
               FROM action_logs
              WHERE usuario_id = ? AND modulo = 'auth'
              ORDER BY created_at DESC
              LIMIT $limite",
            [$usuarioId]
        );
    }
}

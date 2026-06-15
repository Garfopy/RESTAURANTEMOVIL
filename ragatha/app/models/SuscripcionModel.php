<?php
class SuscripcionModel extends BaseModel
{
    protected string $table = 'suscripciones';

    public function getPlanesActivos(): array
    {
        return $this->query(
            'SELECT * FROM planes_saas WHERE activo = 1 ORDER BY precio_mensual ASC'
        );
    }

    public function getPlanPorSlug(string $slug): ?array
    {
        return $this->queryOne(
            'SELECT * FROM planes_saas WHERE slug = ? AND activo = 1',
            [$slug]
        );
    }

    public function getPlanPorId(int $id): ?array
    {
        return $this->queryOne('SELECT * FROM planes_saas WHERE id = ?', [$id]);
    }

    public function getByEmpresa(int $empresaId): ?array
    {
        return $this->queryOne(
            'SELECT s.*, p.slug AS plan_slug, p.nombre AS plan_nombre,
                    p.precio_mensual, p.precio_anual,
                    p.max_usuarios, p.max_productos, p.max_pedidos_mes, p.max_sucursales,
                    p.features, p.paypal_plan_id, p.paypal_plan_id_anual,
                    p.paypal_plan_id_live, p.paypal_plan_id_anual_live
             FROM suscripciones s
             JOIN planes_saas p ON p.id = s.plan_id
             WHERE s.empresa_id = ?',
            [$empresaId]
        );
    }

    public function getByPaypalId(string $paypalSubId): ?array
    {
        return $this->queryOne(
            'SELECT s.*, p.slug AS plan_slug, p.nombre AS plan_nombre
             FROM suscripciones s
             JOIN planes_saas p ON p.id = s.plan_id
             WHERE s.paypal_subscription_id = ?',
            [$paypalSubId]
        );
    }

    public function listado(array $filtros = [], int $page = 1): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtros['plan_id'])) {
            $where[]  = 's.plan_id = ?';
            $params[] = (int)$filtros['plan_id'];
        }
        if (!empty($filtros['estado'])) {
            $where[]  = 's.estado = ?';
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['buscar'])) {
            $where[]  = 'e.razon_social LIKE ?';
            $params[] = '%' . $filtros['buscar'] . '%';
        }

        $cond = implode(' AND ', $where);
        $sql  = "SELECT s.*, e.razon_social, e.email AS empresa_email,
                        p.slug AS plan_slug, p.nombre AS plan_nombre, p.precio_mensual
                 FROM suscripciones s
                 JOIN empresas e ON e.id = s.empresa_id
                 JOIN planes_saas p ON p.id = s.plan_id
                 WHERE $cond
                 ORDER BY s.created_at DESC";

        return $this->paginate($sql, $params, $page);
    }

    public function crear(array $data): int
    {
        $this->db->beginTransaction();
        try {
            $this->execute(
                'INSERT INTO suscripciones
                 (empresa_id, plan_id, estado, ciclo, fecha_inicio, fecha_vencimiento,
                  paypal_subscription_id, paypal_status, notas, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $data['empresa_id'],
                    $data['plan_id'],
                    $data['estado']                 ?? 'activo',
                    $data['ciclo']                  ?? 'mensual',
                    $data['fecha_inicio']           ?? date('Y-m-d'),
                    $data['fecha_vencimiento']      ?? null,
                    $data['paypal_subscription_id'] ?? null,
                    $data['paypal_status']          ?? null,
                    $data['notas']                  ?? null,
                    $data['created_by']             ?? null,
                ]
            );
            $id = (int)$this->db->lastInsertId();

            $estadoEmpresa = ($data['estado'] ?? 'activo') === 'pendiente_paypal'
                ? 'sin_plan'
                : 'activo';
            $this->execute(
                'UPDATE empresas SET suscripcion_estado = ? WHERE id = ?',
                [$estadoEmpresa, $data['empresa_id']]
            );

            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function cambiarPlan(int $susId, int $planId): void
    {
        $this->execute(
            'UPDATE suscripciones SET plan_id = ? WHERE id = ?',
            [$planId, $susId]
        );
    }

    public function cambiarEstado(int $susId, string $estado): void
    {
        $this->db->beginTransaction();
        try {
            $this->execute(
                'UPDATE suscripciones SET estado = ? WHERE id = ?',
                [$estado, $susId]
            );

            $estadoEmpresa = match ($estado) {
                'activo'           => 'activo',
                'suspendido',
                'cancelado'        => 'suspendido',
                default            => 'sin_plan',
            };
            $this->execute(
                'UPDATE empresas SET suscripcion_estado = ?
                 WHERE id = (SELECT empresa_id FROM suscripciones WHERE id = ?)',
                [$estadoEmpresa, $susId]
            );

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function guardarPaypalId(int $susId, string $paypalSubId, string $paypalStatus): void
    {
        $this->execute(
            'UPDATE suscripciones SET paypal_subscription_id = ?, paypal_status = ? WHERE id = ?',
            [$paypalSubId, $paypalStatus, $susId]
        );
    }

    public function activarDesdePaypal(string $paypalSubId): void
    {
        $this->db->beginTransaction();
        try {
            $this->execute(
                'UPDATE suscripciones
                 SET estado = "activo", paypal_status = "ACTIVE", fecha_inicio = CURDATE()
                 WHERE paypal_subscription_id = ?',
                [$paypalSubId]
            );
            $this->execute(
                'UPDATE empresas SET suscripcion_estado = "activo"
                 WHERE id = (SELECT empresa_id FROM suscripciones WHERE paypal_subscription_id = ?)',
                [$paypalSubId]
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function renovar(int $susId, string $nuevaFecha): void
    {
        $this->execute(
            'UPDATE suscripciones SET fecha_vencimiento = ? WHERE id = ?',
            [$nuevaFecha, $susId]
        );
    }

    public function actualizarLimitesPlan(int $planId, array $data): void
    {
        $permitidos = ['nombre','descripcion','precio_mensual','precio_anual',
                       'max_usuarios','max_productos','max_pedidos_mes','max_sucursales'];
        $campos = array_filter($data, fn($k) => in_array($k, $permitidos, true), ARRAY_FILTER_USE_KEY);
        if (empty($campos)) return;
        $sets   = implode(', ', array_map(fn($k) => "$k = ?", array_keys($campos)));
        $params = array_values($campos);
        $params[] = $planId;
        $this->db->prepare("UPDATE planes_saas SET $sets WHERE id = ?")->execute($params);
    }

    public function guardarPaypalPlanId(int $planSaasId, string $paypalPlanId): void
    {
        $this->execute(
            'UPDATE planes_saas SET paypal_plan_id = ? WHERE id = ?',
            [$paypalPlanId, $planSaasId]
        );
    }

    public function guardarPaypalPlanIdAnual(int $planSaasId, string $paypalPlanId): void
    {
        $this->execute(
            'UPDATE planes_saas SET paypal_plan_id_anual = ? WHERE id = ?',
            [$paypalPlanId, $planSaasId]
        );
    }

    public function guardarPaypalPlanIdLive(int $planSaasId, string $paypalPlanId): void
    {
        $this->execute(
            'UPDATE planes_saas SET paypal_plan_id_live = ? WHERE id = ?',
            [$paypalPlanId, $planSaasId]
        );
    }

    public function guardarPaypalPlanIdAnualLive(int $planSaasId, string $paypalPlanId): void
    {
        $this->execute(
            'UPDATE planes_saas SET paypal_plan_id_anual_live = ? WHERE id = ?',
            [$paypalPlanId, $planSaasId]
        );
    }

    /**
     * Limpia los IDs de PayPal del modo indicado para forzar recreación
     * al cambiar precios del plan.
     */
    public function limpiarPaypalPlanIds(int $planId, string $modo): void
    {
        if ($modo === 'live') {
            $this->execute(
                'UPDATE planes_saas SET paypal_plan_id_live = NULL, paypal_plan_id_anual_live = NULL WHERE id = ?',
                [$planId]
            );
        } else {
            $this->execute(
                'UPDATE planes_saas SET paypal_plan_id = NULL, paypal_plan_id_anual = NULL WHERE id = ?',
                [$planId]
            );
        }
    }

    public function verificarLimite(int $empresaId, string $tipo): bool
    {
        $sus = $this->getByEmpresa($empresaId);
        if (!$sus) return false;

        $columna = match ($tipo) {
            'usuarios'    => 'max_usuarios',
            'productos'   => 'max_productos',
            'pedidos_mes' => 'max_pedidos_mes',
            'sucursales'  => 'max_sucursales',
            default       => null,
        };
        if (!$columna) return true;

        $maximo = (int)$sus[$columna];
        if ($maximo === 0) return true; // 0 = ilimitado

        $actual = match ($tipo) {
            'usuarios' => (int)($this->queryOne(
                'SELECT COUNT(*) AS n FROM usuarios WHERE empresa_id = ? AND activo = 1',
                [$empresaId]
            )['n'] ?? 0),
            'productos' => (int)($this->queryOne(
                'SELECT COUNT(*) AS n FROM productos WHERE empresa_id = ? AND activo = 1',
                [$empresaId]
            )['n'] ?? 0),
            'pedidos_mes' => (int)($this->queryOne(
                'SELECT COUNT(*) AS n FROM pedidos
                 WHERE empresa_id = ?
                   AND MONTH(created_at) = MONTH(CURDATE())
                   AND YEAR(created_at)  = YEAR(CURDATE())',
                [$empresaId]
            )['n'] ?? 0),
            'sucursales' => (int)($this->queryOne(
                'SELECT COUNT(*) AS n FROM sucursales WHERE empresa_id = ? AND activo = 1',
                [$empresaId]
            )['n'] ?? 0),
            default => 0,
        };

        return $actual < $maximo;
    }
}

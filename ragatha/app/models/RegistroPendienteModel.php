<?php
/**
 * CarniHub — Modelo de Registros Pendientes
 * Gestiona los registros desde la landing page antes de verificar el email
 */
class RegistroPendienteModel extends BaseModel
{
    protected string $table = 'registros_pendientes';

    /**
     * Crear un nuevo registro pendiente
     */
    public function crear(array $datos): int
    {
        return $this->insert($datos);
    }

    /**
     * Buscar por token de verificación
     */
    public function getByToken(string $token): ?array
    {
        return $this->queryOne(
            'SELECT * FROM registros_pendientes WHERE token_verificacion = ? LIMIT 1',
            [$token]
        );
    }

    /**
     * Buscar por ID de suscripción de PayPal
     */
    public function getByPaypalId(string $paypalId): ?array
    {
        return $this->queryOne(
            'SELECT * FROM registros_pendientes WHERE paypal_subscription_id = ? LIMIT 1',
            [$paypalId]
        );
    }

    /**
     * Buscar por email y estado (para verificar duplicados)
     */
    public function getByEmailActivo(string $email): ?array
    {
        return $this->queryOne(
            'SELECT * FROM registros_pendientes
             WHERE email = ?
               AND estado IN ("pendiente_pago", "pendiente_verificacion")
             LIMIT 1',
            [$email]
        );
    }

    /**
     * Actualizar el estado de PayPal después del checkout
     */
    public function actualizarPaypalStatus(int $id, string $status, string $paypalId): bool
    {
        return $this->execute(
            'UPDATE registros_pendientes
             SET paypal_subscription_id = ?,
                 paypal_status = ?,
                 estado = "pendiente_verificacion"
             WHERE id = ?',
            [$paypalId, $status, $id]
        );
    }

    /**
     * Actualizar token de verificación y contraseña después del pago exitoso
     */
    public function actualizarTokenYPassword(int $id, string $token, string $passwordHash): bool
    {
        $expira = date('Y-m-d H:i:s', strtotime('+24 hours'));
        return $this->execute(
            'UPDATE registros_pendientes
             SET token_verificacion = ?,
                 token_expira = ?,
                 password_hash = ?
             WHERE id = ?',
            [$token, $expira, $passwordHash, $id]
        );
    }

    /**
     * Marcar registro como completado
     */
    public function marcarCompletado(int $id): bool
    {
        return $this->execute(
            'UPDATE registros_pendientes
             SET estado = "completado",
                 completed_at = NOW()
             WHERE id = ?',
            [$id]
        );
    }

    /**
     * Eliminar registros expirados (más de 48 horas desde creación)
     * y registros pendientes de pago (más de 2 horas)
     */
    public function limpiarExpirados(): int
    {
        // Marcar como expirados los tokens que ya vencieron
        $this->execute(
            'UPDATE registros_pendientes
             SET estado = "expirado"
             WHERE estado = "pendiente_verificacion"
               AND token_expira < NOW()'
        );

        // Eliminar registros muy antiguos (más de 48 horas)
        $stmt = $this->db->prepare(
            'DELETE FROM registros_pendientes
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)
               AND estado IN ("expirado", "pendiente_pago")'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Cancelar un registro pendiente
     */
    public function cancelar(int $id): bool
    {
        return $this->delete($id);
    }
}

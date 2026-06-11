<?php
/**
 * POST /auth/logout
 * Header: Authorization: Bearer <token>
 */
db_exec(
    "UPDATE mobile_sesiones SET activo = 0 WHERE usuario_id = ? AND activo = 1",
    [$userId]
);

http_response_code(204);
exit;
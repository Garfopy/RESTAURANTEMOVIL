-- Push notifications: device tokens for Firebase Cloud Messaging.

CREATE TABLE IF NOT EXISTS mobile_push_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fcm_token VARCHAR(255) NOT NULL,
    platform VARCHAR(30) NULL,
    device_id VARCHAR(190) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP NULL,
    UNIQUE KEY uniq_mobile_push_token (fcm_token),
    INDEX idx_mobile_push_tokens_usuario (usuario_id, enabled),
    INDEX idx_mobile_push_tokens_device (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

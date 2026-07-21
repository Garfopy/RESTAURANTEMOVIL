-- Persiste la solicitud fiscal antes de abrir PaymentSheet para que el webhook
-- pueda completarla si la aplicacion se cierra despues del cobro.

CREATE TABLE IF NOT EXISTS stripe_pending_invoice_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    request_json JSON NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'pending',
    invoice_request_id INT UNSIGNED NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    processed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_stripe_pending_invoice_order (order_id),
    KEY idx_stripe_pending_invoice_status (status, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

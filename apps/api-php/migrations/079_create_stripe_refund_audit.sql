CREATE TABLE IF NOT EXISTS stripe_refund_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  stripe_refund_id VARCHAR(255) NOT NULL,
  payment_intent_id VARCHAR(255) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  admin_user_id INT UNSIGNED NULL,
  request_key VARCHAR(120) NOT NULL,
  amount_mxn DECIMAL(12,2) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  status VARCHAR(40) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stripe_refund_audit_refund (stripe_refund_id),
  KEY idx_stripe_refund_audit_user (user_id, created_at),
  KEY idx_stripe_refund_audit_request (request_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

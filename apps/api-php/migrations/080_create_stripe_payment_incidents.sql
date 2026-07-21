CREATE TABLE IF NOT EXISTS stripe_payment_incidents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  incident_type VARCHAR(80) NOT NULL,
  stripe_object_id VARCHAR(255) NOT NULL,
  payment_intent_id VARCHAR(255) NULL,
  details_json TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stripe_payment_incident (incident_type, stripe_object_id),
  KEY idx_stripe_payment_incident_intent (payment_intent_id),
  KEY idx_stripe_payment_incident_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

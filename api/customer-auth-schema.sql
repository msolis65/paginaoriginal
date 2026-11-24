CREATE TABLE customer_accounts (
  phone_e164 VARCHAR(16) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_verified_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  consented_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (phone_e164)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customer_phone_codes (
  phone_e164 VARCHAR(16) NOT NULL,
  code_hash CHAR(64) NULL,
  expires_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
  sent_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  request_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  request_window_started_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
  blocked_until BIGINT UNSIGNED NOT NULL DEFAULT 0,
  consented_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (phone_e164),
  KEY idx_customer_phone_codes_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customer_auth_ip_limits (
  ip_hash CHAR(64) NOT NULL,
  window_started_at BIGINT UNSIGNED NOT NULL,
  request_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (ip_hash),
  KEY idx_customer_auth_ip_limits_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

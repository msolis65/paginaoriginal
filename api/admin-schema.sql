-- Ejecutar una vez en la base de datos del sitio.
CREATE TABLE IF NOT EXISTS site_admins (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_site_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_sales (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  development VARCHAR(40) NOT NULL,
  units INT UNSIGNED NOT NULL DEFAULT 1,
  sold_on DATE NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_sales_development (development),
  KEY idx_admin_sales_sold_on (sold_on),
  CONSTRAINT fk_admin_sales_admin FOREIGN KEY (created_by) REFERENCES site_admins(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

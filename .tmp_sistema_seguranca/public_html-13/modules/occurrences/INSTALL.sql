-- Módulo: Livro de Ocorrências (RH Segurança)

CREATE TABLE IF NOT EXISTS occurrence_sequences (
  id INT AUTO_INCREMENT PRIMARY KEY,
  year INT NOT NULL,
  sector VARCHAR(50) NOT NULL,
  last_number INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_year_sector (year, sector)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS occurrences (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  protocol VARCHAR(30) NOT NULL UNIQUE,
  sector VARCHAR(50) NOT NULL,
  occurred_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  status ENUM('draft','registered','closed','canceled') NOT NULL DEFAULT 'registered',

  location VARCHAR(180) NOT NULL,
  reference_point VARCHAR(180) NULL,
  nature VARCHAR(80) NOT NULL,
  involved MEDIUMTEXT NULL,
  agencies JSON NULL,
  description MEDIUMTEXT NOT NULL,
  actions_taken MEDIUMTEXT NULL,
  observations MEDIUMTEXT NULL,

  vehicle_prefix VARCHAR(30) NULL,
  vehicle_plate VARCHAR(20) NULL,
  km_start INT NULL,
  km_end INT NULL,

  created_by BIGINT NOT NULL,
  closed_by BIGINT NULL,
  closed_at DATETIME NULL,

  INDEX idx_sector_date (sector, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS occurrence_attachments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  occurrence_id BIGINT NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_path VARCHAR(255) NOT NULL,
  mime VARCHAR(100) NOT NULL,
  size_bytes BIGINT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT NOT NULL,
  FOREIGN KEY (occurrence_id) REFERENCES occurrences(id) ON DELETE CASCADE,
  INDEX idx_occ (occurrence_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS occurrence_audit (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  occurrence_id BIGINT NOT NULL,
  action VARCHAR(60) NOT NULL,
  meta JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT NOT NULL,
  FOREIGN KEY (occurrence_id) REFERENCES occurrences(id) ON DELETE CASCADE,
  INDEX idx_audit (occurrence_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

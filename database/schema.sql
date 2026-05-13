-- ============================================================
-- Schema para sistema de sensores - Tesis de Ingeniería
-- Base de datos: MySQL
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Eliminar tablas residuales de otros esquemas o versiones (evita ERROR 3780 por incompatibilidad de tipos)
DROP TABLE IF EXISTS `alerts`;
DROP TABLE IF EXISTS `alert_rules`;
DROP TABLE IF EXISTS `measurements`;
DROP TABLE IF EXISTS `formulas`;
DROP TABLE IF EXISTS `sensor_variables`;
DROP TABLE IF EXISTS `sensors`;
DROP TABLE IF EXISTS `datos`;

-- ------------------------------------------------------------
-- Tabla: sensors
-- Almacena los sensores (ej: sensor de tanque, estación climática)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `sensors`;
CREATE TABLE `sensors` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `api_key` VARCHAR(64) DEFAULT NULL COMMENT 'Llave para enviar datos por GET (identifica el sensor)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sensors_api_key` (`api_key`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: sensor_variables
-- Variables de cada sensor (medidas o calculadas)
-- type: 'measure' = medida directa, 'calculated' = resultado de fórmula
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `sensor_variables`;
CREATE TABLE `sensor_variables` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sensor_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(80) NOT NULL COMMENT 'Nombre de la variable (ej: nivel, temperatura, grasas)',
  `type` ENUM('measure', 'calculated') NOT NULL DEFAULT 'measure',
  `unit` VARCHAR(20) DEFAULT NULL COMMENT 'Unidad opcional (ej: °C, cm, %)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sensor_variable` (`sensor_id`, `name`),
  KEY `idx_sensor_id` (`sensor_id`),
  CONSTRAINT `fk_sensor_variables_sensor` FOREIGN KEY (`sensor_id`) REFERENCES `sensors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: formulas
-- Fórmulas que generan variables calculadas
-- expression: texto como "nivel*a1 + temperatura*a2 + b"
-- result_variable_id: variable calculada que se actualiza
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `formulas`;
CREATE TABLE `formulas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sensor_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL COMMENT 'Nombre descriptivo de la fórmula',
  `expression` TEXT NOT NULL COMMENT 'Expresión matemática con variables y parámetros',
  `result_variable_id` INT UNSIGNED NOT NULL COMMENT 'Variable calculada que almacena el resultado',
  `parameters` JSON NOT NULL COMMENT 'Parámetros: {"a1": 1, "a2": 0.5, "b": 0}',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sensor_id` (`sensor_id`),
  KEY `idx_result_variable_id` (`result_variable_id`),
  CONSTRAINT `fk_formulas_sensor` FOREIGN KEY (`sensor_id`) REFERENCES `sensors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_formulas_result_variable` FOREIGN KEY (`result_variable_id`) REFERENCES `sensor_variables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: measurements
-- Valores medidos y calculados por sensor y timestamp
-- variable_id + measured_at identifican una medición (o se permite múltiples por timestamp)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `measurements`;
CREATE TABLE `measurements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sensor_id` INT UNSIGNED NOT NULL,
  `variable_id` INT UNSIGNED NOT NULL,
  `value` DECIMAL(20, 6) NOT NULL,
  `measured_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sensor_id` (`sensor_id`),
  KEY `idx_variable_id` (`variable_id`),
  KEY `idx_measured_at` (`measured_at`),
  KEY `idx_sensor_measured` (`sensor_id`, `measured_at`),
  CONSTRAINT `fk_measurements_sensor` FOREIGN KEY (`sensor_id`) REFERENCES `sensors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_measurements_variable` FOREIGN KEY (`variable_id`) REFERENCES `sensor_variables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: alert_rules
-- Reglas de alerta: variable_id operador valor_umbral
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `alert_rules`;
CREATE TABLE `alert_rules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sensor_id` INT UNSIGNED NOT NULL,
  `variable_id` INT UNSIGNED NOT NULL,
  `operator` ENUM('>', '<', '>=', '<=', '=') NOT NULL,
  `threshold_value` DECIMAL(20, 6) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sensor_id` (`sensor_id`),
  KEY `idx_variable_id` (`variable_id`),
  CONSTRAINT `fk_alert_rules_sensor` FOREIGN KEY (`sensor_id`) REFERENCES `sensors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alert_rules_variable` FOREIGN KEY (`variable_id`) REFERENCES `sensor_variables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabla: alerts
-- Alertas disparadas cuando se cumple una regla
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `alerts`;
CREATE TABLE `alerts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `alert_rule_id` INT UNSIGNED NOT NULL,
  `sensor_id` INT UNSIGNED NOT NULL,
  `variable_id` INT UNSIGNED NOT NULL,
  `value` DECIMAL(20, 6) NOT NULL COMMENT 'Valor que disparó la alerta',
  `threshold_value` DECIMAL(20, 6) NOT NULL,
  `operator` VARCHAR(5) NOT NULL,
  `message` VARCHAR(500) DEFAULT NULL,
  `triggered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` DATETIME DEFAULT NULL COMMENT 'Cuando el usuario marcó como leída',
  PRIMARY KEY (`id`),
  KEY `idx_alert_rule_id` (`alert_rule_id`),
  KEY `idx_sensor_id` (`sensor_id`),
  KEY `idx_triggered_at` (`triggered_at`),
  CONSTRAINT `fk_alerts_rule` FOREIGN KEY (`alert_rule_id`) REFERENCES `alert_rules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alerts_sensor` FOREIGN KEY (`sensor_id`) REFERENCES `sensors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alerts_variable` FOREIGN KEY (`variable_id`) REFERENCES `sensor_variables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Fin del schema
-- ============================================================

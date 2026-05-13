-- Añadir api_key a sensors (para instalaciones que ya tenían el schema sin esta columna)
-- Ejecutar: mysql -u usuario -p cloudsensor < database/migrations/001_add_sensor_api_key.sql

ALTER TABLE `sensors`
  ADD COLUMN `api_key` VARCHAR(64) DEFAULT NULL COMMENT 'Llave para enviar datos por GET' AFTER `description`,
  ADD UNIQUE KEY `uk_sensors_api_key` (`api_key`);

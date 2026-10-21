-- SENN AI-X / MySQL >= 8.0.16 ou MariaDB >= 10.4. UTF-8, InnoDB.
-- Importação inicial pelo phpMyAdmin do XAMPP, com conta autorizada a criar bancos.
-- Não apaga dados. Se já houver tabelas, não reimporte como migração.
CREATE DATABASE IF NOT EXISTS `senn_aix`
 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `senn_aix`;
SET NAMES utf8mb4;
CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','gestor','despachante','motorista') NOT NULL,
 admin_singleton TINYINT GENERATED ALWAYS AS (CASE WHEN role='admin' THEN 1 ELSE NULL END) STORED,
 UNIQUE KEY uq_users_single_admin(admin_singleton),
 active BOOLEAN NOT NULL DEFAULT TRUE, privacy_accepted_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE vehicles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, plate VARCHAR(8) NOT NULL UNIQUE,
 type VARCHAR(60) NOT NULL, capacity_kg DECIMAL(10,2) NOT NULL,
 capacity_m3 DECIMAL(10,3) NOT NULL, autonomy_km DECIMAL(10,2) NOT NULL,
 status ENUM('disponivel','em_rota','manutencao') NOT NULL DEFAULT 'disponivel',
 maintenance_date DATE NOT NULL, cost_per_km DECIMAL(8,2) NOT NULL DEFAULT 2.50,
 CHECK(capacity_kg>0 AND capacity_m3>0 AND autonomy_km>0 AND cost_per_km>=0)
) ENGINE=InnoDB;
CREATE TABLE drivers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL UNIQUE, cnh VARCHAR(11) NULL UNIQUE,
 cnh_category ENUM('A','B','AB','C','D','E','AC','AD','AE') NOT NULL,
 cnh_expires DATE NOT NULL, phone VARCHAR(24) NOT NULL,
 available BOOLEAN NOT NULL DEFAULT TRUE, vehicle_id BIGINT UNSIGNED NULL UNIQUE,
 FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(vehicle_id) REFERENCES vehicles(id)
) ENGINE=InnoDB;
CREATE TABLE deliveries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, reference VARCHAR(50) NOT NULL UNIQUE,
 recipient_name VARCHAR(120) NULL, address VARCHAR(255) NULL, region VARCHAR(80) NOT NULL,
 lat DECIMAL(10,7) NOT NULL, lng DECIMAL(10,7) NOT NULL,
 window_start DATETIME NOT NULL, window_end DATETIME NOT NULL,
 weight_kg DECIMAL(10,2) NOT NULL, volume_m3 DECIMAL(10,3) NOT NULL,
 priority ENUM('normal','alta','urgente') NOT NULL DEFAULT 'normal',
 status ENUM('pendente','planejada','a_caminho','entregue','falha') NOT NULL DEFAULT 'pendente',
 confirmation_hash VARCHAR(255) NULL, created_by BIGINT UNSIGNED NOT NULL,
 delivered_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id),
 CHECK(window_end>window_start AND weight_kg>0 AND volume_m3>0),
 CHECK(lat BETWEEN -90 AND 90 AND lng BETWEEN -180 AND 180),
 INDEX idx_delivery_window(status,window_end), INDEX idx_delivery_region(region,created_at)
) ENGINE=InnoDB;
CREATE TABLE routes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, vehicle_id BIGINT UNSIGNED NOT NULL,
 driver_id BIGINT UNSIGNED NOT NULL, created_by BIGINT UNSIGNED NOT NULL,
 status ENUM('planejada','em_andamento','concluida') NOT NULL DEFAULT 'planejada',
 departure_at DATETIME NOT NULL, completed_at DATETIME NULL,
 estimated_km DECIMAL(10,3) NOT NULL, actual_km DECIMAL(10,3) NOT NULL DEFAULT 0,
 estimated_cost DECIMAL(12,2) NOT NULL, cost_per_km DECIMAL(8,2) NOT NULL,
 origin_lat DECIMAL(10,7) NOT NULL, origin_lng DECIMAL(10,7) NOT NULL,
 algorithm VARCHAR(80) NOT NULL DEFAULT 'nearest-feasible-v1',
 FOREIGN KEY(vehicle_id) REFERENCES vehicles(id), FOREIGN KEY(driver_id) REFERENCES drivers(id),
 FOREIGN KEY(created_by) REFERENCES users(id), INDEX idx_route_driver(driver_id,status)
) ENGINE=InnoDB;
CREATE TABLE route_stops (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, route_id BIGINT UNSIGNED NOT NULL,
 delivery_id BIGINT UNSIGNED NOT NULL UNIQUE, sequence_no INT NOT NULL,
 eta DATETIME NOT NULL, leg_km DECIMAL(10,3) NOT NULL,
 UNIQUE(route_id,sequence_no), FOREIGN KEY(route_id) REFERENCES routes(id),
 FOREIGN KEY(delivery_id) REFERENCES deliveries(id)
) ENGINE=InnoDB;
CREATE TABLE positions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, route_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NOT NULL, lat DECIMAL(10,7) NOT NULL, lng DECIMAL(10,7) NOT NULL,
 recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(route_id) REFERENCES routes(id), FOREIGN KEY(user_id) REFERENCES users(id),
 CHECK(lat BETWEEN -90 AND 90 AND lng BETWEEN -180 AND 180), INDEX idx_position_route(route_id,id)
) ENGINE=InnoDB;
CREATE TABLE delivery_proofs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, delivery_id BIGINT UNSIGNED NOT NULL UNIQUE,
 driver_id BIGINT UNSIGNED NOT NULL, delivered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 lat DECIMAL(10,7) NOT NULL, lng DECIMAL(10,7) NOT NULL,
 evidence_type ENUM('codigo') NOT NULL DEFAULT 'codigo',
 FOREIGN KEY(delivery_id) REFERENCES deliveries(id), FOREIGN KEY(driver_id) REFERENCES drivers(id),
 CHECK(lat BETWEEN -90 AND 90 AND lng BETWEEN -180 AND 180)
) ENGINE=InnoDB;
CREATE TABLE alerts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, dedup_key VARCHAR(190) NOT NULL UNIQUE,
 kind VARCHAR(40) NOT NULL, message VARCHAR(255) NOT NULL,
 route_id BIGINT UNSIGNED NULL, resolved_at DATETIME NULL, resolved_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(route_id) REFERENCES routes(id), FOREIGN KEY(resolved_by) REFERENCES users(id),
 INDEX idx_alert_open(resolved_at,created_at)
) ENGINE=InnoDB;
CREATE TABLE notification_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, alert_id BIGINT UNSIGNED NOT NULL,
 channel ENUM('painel','email') NOT NULL, status ENUM('pendente','enviado','falha') NOT NULL,
 attempts INT NOT NULL DEFAULT 0, attempted_at DATETIME NULL,
 FOREIGN KEY(alert_id) REFERENCES alerts(id), UNIQUE(alert_id,channel)
) ENGINE=InnoDB;
CREATE TABLE audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NULL,
 action VARCHAR(80) NOT NULL, entity_type VARCHAR(50) NOT NULL, entity_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL, INDEX idx_audit_time(created_at)
) ENGINE=InnoDB;
CREATE TABLE login_attempts (
 key_hash CHAR(64) PRIMARY KEY, failures INT NOT NULL DEFAULT 0,
 blocked_until DATETIME NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

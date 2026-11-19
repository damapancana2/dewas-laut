-- Jalankan pada database dewas yang sudah ada (MariaDB 10.4).
-- Menambah tabel telemetri laut. Data banjir/kebakaran dan pengaturan lama tetap ada.
USE `dewas`;

CREATE TABLE IF NOT EXISTS `telemetry_laut` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `device_id` VARCHAR(40) NOT NULL,
  `boot_id` VARCHAR(32) NOT NULL,
  `seq` INT UNSIGNED NOT NULL,
  `uptime_ms` BIGINT UNSIGNED NOT NULL,
  `a16_valid` TINYINT UNSIGNED NOT NULL,
  `distance_mm` SMALLINT UNSIGNED DEFAULT NULL,
  `calibrated_height_cm` INT DEFAULT NULL,
  `calibrated_status` TINYINT UNSIGNED DEFAULT NULL,
  `calibration_version` INT UNSIGNED DEFAULT NULL,
  `probe_waspada` TINYINT UNSIGNED NOT NULL,
  `probe_siaga` TINYINT UNSIGNED NOT NULL,
  `probe_awas` TINYINT UNSIGNED NOT NULL,
  `probe_level` TINYINT UNSIGNED DEFAULT NULL,
  `wind_valid` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `wind_hz` DECIMAL(12,4) UNSIGNED DEFAULT NULL,
  `wind_pulses` INT UNSIGNED DEFAULT NULL,
  `wind_window_ms` INT UNSIGNED DEFAULT NULL,
  `wind_mps` DECIMAL(12,4) UNSIGNED DEFAULT NULL,
  `bmp180_valid` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `pressure_hpa` DECIMAL(7,2) UNSIGNED DEFAULT NULL,
  `bmp180_temp_c` DECIMAL(6,2) DEFAULT NULL,
  `relay_on` TINYINT UNSIGNED NOT NULL,
  `device_status` TINYINT UNSIGNED NOT NULL,
  `config_version` INT UNSIGNED NOT NULL,
  `rssi` SMALLINT NOT NULL,
  `received_at` TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `packet` (`device_id`, `boot_id`, `seq`),
  KEY `device_time` (`device_id`, `id`),
  KEY `device_received` (`device_id`, `received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Pengaturan laut sudah ada di dump: settings.device_id = 'laut-001'.
-- Perintah suara memakai audio_commands dengan device_id yang sama.
-- API laut akan menulis telemetry_laut dan mengisi kolom kalibrasi.
-- Gunakan NULL untuk sensor belum valid; 0 Hz boleh berarti tidak ada pulsa.

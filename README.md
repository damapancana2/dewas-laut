# DEWaS Laut Monitoring Core & Web Dashboard

Layanan pemantauan sensor maritim / pasang surut air laut (Sea & Tidal Monitoring) untuk ekosistem **DEWaS** (*Disaster Early Warning System*).
Terintegrasi secara terpusat dengan backend **DEWaS-BE** menggunakan struktur tabel unified: `sensors`, `telemetry_logs`, dan `audio_commands`.

## Fitur Utama
- **Monitoring Pasang Surut Air Laut**: Sensor ultrasonik A16 (tinggi muka air cm, status: Normal, Waspada, Siaga, Awas) dan 3 probe cadangan air laut.
- **Sensor Cuaca Maritim**: Anemometer kecepatan angin (Hz / m/s) dan sensor tekanan & suhu udara BMP180.
- **Integrasi Terpadu dewas-be**:
  - Konfigurasi ambang batas (`height_cm`, `offset_cm`, `waspada_cm`, `siaga_cm`, `awas_cm`), token keamanan (`device_token`), dan sandi operator disimpan di kolom `sensors.config` (JSON) dan kolom tabel `sensors`.
  - Ingestion data telemetri otomatis tersimpan ke tabel `telemetry_logs` (`domain = 'sea'`, `metadata_json` lengkap).
  - Antrean perintah peringatan suara dan sirene maritim ESP32 terintegrasi ke tabel `audio_commands`.
- **Web Dashboard & Visualisasi**: Grafik dinamis pasang surut air laut, log telemetri real-time, tes audio peringatan dini, dan pengaturan kalibrasi sensor.

## Konfigurasi
Koneksi database MySQL diatur melalui `config.php`:
```php
return [
    'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=dewas_db;charset=utf8mb4',
    'user' => 'root',
    'password' => 'root',
];
```

## API Endpoint
- `GET /api.php?action=build`: Informasi build & domain backend.
- `GET /api.php?action=devices`: Daftar sensor maritim yang terdaftar.
- `GET /api.php?action=latest`: Data telemetri maritim terbaru.
- `GET /api.php?action=history`: Riwayat 120 data telemetri.
- `POST /api.php?action=ingest`: Ingest telemetri dari mikrokontroler ESP32 (Header: `X-Device-Token`).
- `GET /api.php?action=audio_command`: Polling antrean audio oleh ESP32.
- `POST /api.php?action=audio_ack`: Konfirmasi pemutaran audio dari ESP32.
- `POST /api.php?action=save`: Simpan kalibrasi sensor ke `sensors.config`.
- `GET /periksa.php`: Diagnostik koneksi database dan status tabel.

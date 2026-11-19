<?php
declare(strict_types=1);

const APP_BUILD = 'DEWAS-LAUT-V2';

// 1. Konfigurasi koneksi database
function config(): array {
    static $c;
    return $c ?? ($c = require __DIR__ . '/config.php');
}

// 2. Koneksi PDO ke MySQL dewas_db
function db(): PDO {
    static $p;
    if (!$p) {
        $cfg = config();
        $next = new PDO($cfg['dsn'], $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        $next->exec("SET time_zone = '+00:00'");
        $p = $next;
    }
    return $p;
}

// 3. Response JSON Helper
function reply(array $data, int $code = 200): void {
    http_response_code($code);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Device-Token, X-Device-ID');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}

function respond(array $data, int $code = 200): void {
    reply($data, $code);
}

// 4. Request JSON Body Parser
function body(): array {
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) reply(['error' => 'Payload terlalu besar'], 413);
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > 8192) reply(['error' => 'Payload terlalu besar'], 413);
    try {
        $d = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        reply(['error' => 'JSON tidak valid'], 400);
    }
    if (!is_array($d)) reply(['error' => 'JSON harus objek'], 400);
    return $d;
}

function numericValue($v): bool {
    return (is_int($v) || is_float($v)) && is_finite((float)$v);
}

// 5. Ambil data Sensor Maritim dari tabel sensors (dewas-be)
function getSensor(string $deviceId, bool $refresh = false): ?array {
    static $cache = [];
    if (!$refresh && isset($cache[$deviceId])) return $cache[$deviceId];

    try {
        $q = db()->prepare("SELECT * FROM sensors WHERE device_id = ? LIMIT 1");
        $q->execute([$deviceId]);
        $row = $q->fetch();
        if ($row) {
            $cache[$deviceId] = $row;
            return $row;
        }
    } catch (Throwable $e) {}
    unset($cache[$deviceId]);
    return null;
}

// 6. Ambil default device_id untuk domain sea murni dari tabel sensors dewas-be
function getDefaultDeviceId(): string {
    static $defId;
    if ($defId !== null) return $defId;

    try {
        $q = db()->query("SELECT device_id FROM sensors WHERE domain = 'sea' ORDER BY id ASC LIMIT 1");
        $dev = $q->fetchColumn();
        if ($dev) {
            return $defId = (string)$dev;
        }
    } catch (Throwable $e) {}

    return $defId = 'laut-001';
}

function selectedDevice(): string {
    $id = $_GET['device_id'] ?? $_SERVER['HTTP_X_DEVICE_ID'] ?? getDefaultDeviceId();
    return is_string($id) && $id !== '' ? $id : 'laut-001';
}

// 7. Ambil pengaturan sensor laut murni dari tabel sensors (dewas-be sensors.config JSON)
function settings(?string $deviceId = null, bool $refresh = false): array {
    $devId = $deviceId ?? selectedDevice();
    $sensor = getSensor($devId, $refresh);

    $cfg = [];
    if ($sensor && !empty($sensor['config'])) {
        $parsed = json_decode((string)$sensor['config'], true);
        if (is_array($parsed)) {
            $cfg = $parsed;
        }
    }

    $height_cm = isset($cfg['height_cm']) && numericValue($cfg['height_cm']) ? (float)$cfg['height_cm'] : 230.0;
    $offset_cm = isset($cfg['offset_cm']) && numericValue($cfg['offset_cm']) ? (float)$cfg['offset_cm'] : 0.0;
    $waspada_cm = isset($cfg['waspada_cm']) && numericValue($cfg['waspada_cm']) ? (float)$cfg['waspada_cm'] : 70.0;
    $siaga_cm = isset($cfg['siaga_cm']) && numericValue($cfg['siaga_cm']) ? (float)$cfg['siaga_cm'] : 100.0;
    $awas_cm = isset($cfg['awas_cm']) && numericValue($cfg['awas_cm']) ? (float)$cfg['awas_cm'] : (isset($cfg['bahaya_cm']) && numericValue($cfg['bahaya_cm']) ? (float)$cfg['bahaya_cm'] : 150.0);
    $enabled = isset($cfg['enabled']) ? (bool)$cfg['enabled'] : true;
    $version = isset($cfg['version']) ? (int)$cfg['version'] : 1;
    $windMpsPerHz = isset($cfg['wind_mps_per_hz']) && numericValue($cfg['wind_mps_per_hz']) ? (float)$cfg['wind_mps_per_hz'] : null;
    $adminPassword = !empty($cfg['admin_password']) ? (string)$cfg['admin_password'] : (getenv('DEWAS_LAUT_ADMIN_PASSWORD') ?: '');

    $token = !empty($sensor['device_token'])
        ? (string)$sensor['device_token']
        : (!empty($cfg['device_token']) ? (string)$cfg['device_token'] : 'dewas-2026');

    $lat = ($sensor && isset($sensor['latitude']) && $sensor['latitude'] !== null)
        ? (float)$sensor['latitude']
        : (isset($cfg['latitude']) && $cfg['latitude'] !== null ? (float)$cfg['latitude'] : -8.7492);

    $lng = ($sensor && isset($sensor['longitude']) && $sensor['longitude'] !== null)
        ? (float)$sensor['longitude']
        : (isset($cfg['longitude']) && $cfg['longitude'] !== null ? (float)$cfg['longitude'] : 115.2155);

    return [
        'device_id' => $devId,
        'name' => $sensor['name'] ?? ('Stasiun Laut ' . $devId),
        'domain' => $sensor['domain'] ?? 'sea',
        'height_cm' => $height_cm,
        'offset_cm' => $offset_cm,
        'waspada_cm' => $waspada_cm,
        'siaga_cm' => $siaga_cm,
        'awas_cm' => $awas_cm,
        'bahaya_cm' => $awas_cm,
        'enabled' => $enabled,
        'version' => $version,
        'device_token' => $token,
        'admin_password' => $adminPassword,
        'latitude' => $lat,
        'longitude' => $lng,
        'wind_mps_per_hz' => $windMpsPerHz,
    ];
}

// 8. Autentikasi perangkat ESP32 dari sensors.device_token
function deviceAuth(string $deviceId): string {
    if ($deviceId === '') {
        reply(['error' => 'Identitas device_id tidak valid'], 400);
    }
    $token = $_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '';
    if ($token === '') {
        reply(['error' => 'Header X-Device-Token wajib disertakan'], 401);
    }

    $s = settings($deviceId);
    $expected = $s['device_token'] ?? '';
    if ($expected !== '' && hash_equals($expected, $token)) {
        return $deviceId;
    }
    reply(['error' => 'Token perangkat salah'], 401);
}

// 9. Klasifikasi Status Tinggi Air Pasut (0=Normal, 1=Waspada, 2=Siaga, 3=Awas)
function classify(float $h, array $s): int {
    $awas = $s['awas_cm'] ?? $s['bahaya_cm'] ?? 150.0;
    $siaga = $s['siaga_cm'] ?? 100.0;
    $waspada = $s['waspada_cm'] ?? 70.0;
    return $h >= $awas ? 3 : ($h >= $siaga ? 2 : ($h >= $waspada ? 1 : 0));
}

function validateSettings(array $d): bool {
    foreach (['height_cm', 'offset_cm', 'waspada_cm', 'siaga_cm', 'awas_cm'] as $k) {
        if (!isset($d[$k]) || !numericValue($d[$k])) return false;
    }
    $height = (float)$d['height_cm'];
    $offset = (float)$d['offset_cm'];
    $waspada = (float)$d['waspada_cm'];
    $siaga = (float)$d['siaga_cm'];
    $awas = (float)($d['awas_cm'] ?? $d['bahaya_cm'] ?? 0);

    return isset($d['enabled']) && is_bool($d['enabled']) &&
        $height >= 50 && $height <= 1500 &&
        abs($offset) <= 1000 &&
        $waspada >= 0 && $waspada < $siaga && $siaga < $awas &&
        $awas <= ($height - 50 + $offset);
}

// 10. Otentikasi Operator untuk Dashboard & Pengujian Manual (disimpan di sensors.config / env)
function requireOperator(?string $deviceId = null): void {
    $s = settings($deviceId);
    $pass = (string)($s['admin_password'] ?? '');
    $peer = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($pass === '') {
        if (in_array($peer, ['127.0.0.1', '::1'], true)) return;
        reply(['error' => 'Akses pengaturan dan tes suara hanya dari server lokal sampai sandi operator diatur di sensors.config.'], 403);
    }
    if (($_SERVER['PHP_AUTH_USER'] ?? '') === 'operator' && hash_equals($pass, $_SERVER['PHP_AUTH_PW'] ?? '')) {
        return;
    }
    header('WWW-Authenticate: Basic realm="DEWaS Laut"');
    reply(['error' => 'Masuk sebagai operator.'], 401);
}

// 11. Normalisasi Rekaman Telemetri dari telemetry_logs (metadata_json) atau telemetry_laut
function recordView(array $r, array $s): array {
    $meta = [];
    if (!empty($r['metadata_json'])) {
        $parsed = json_decode((string)$r['metadata_json'], true);
        if (is_array($parsed)) {
            $meta = $parsed;
        }
    }
    $row = array_merge($meta, $r);
    $row['id'] = (int)($r['id'] ?? 0);
    $row['device_id'] = (string)($row['device_id'] ?? $r['sensor_id'] ?? selectedDevice());

    // Timestamp & Usia Data
    $recAt = $row['recorded_at'] ?? $row['received_at'] ?? date('Y-m-d H:i:s');
    $row['received_at'] = $recAt;
    $row['recorded_at'] = $recAt;
    $row['age_seconds'] = isset($r['age_seconds']) ? (float)$r['age_seconds'] : 0.0;

    // A16 Ultrasonic & Tinggi Air
    $row['a16_valid'] = (bool)($row['a16_valid'] ?? false);
    $row['distance_mm'] = isset($row['distance_mm']) && $row['distance_mm'] !== null ? (int)$row['distance_mm'] : null;

    $h = null;
    if ($row['a16_valid'] && ($s['enabled'] ?? true) && $row['distance_mm'] !== null) {
        $h = (float)($s['height_cm'] - ($row['distance_mm'] / 10.0) + $s['offset_cm']);
    } elseif (isset($row['calibrated_height_cm']) && $row['calibrated_height_cm'] !== null) {
        $h = (float)$row['calibrated_height_cm'];
    } elseif (isset($row['height_cm']) && $row['height_cm'] !== null) {
        $h = (float)$row['height_cm'];
    }

    $row['height_cm'] = $h === null ? null : (int)round($h);
    $row['height_stored'] = ($row['height_cm'] !== null);
    $row['calibrated_height_cm'] = $row['height_cm'];

    $calcStatus = $h === null ? 0 : classify($h, $s);
    $row['calculated_status'] = $calcStatus;
    $row['calibrated_status'] = $calcStatus;
    $stNames = [0 => 'NORMAL', 1 => 'WASPADA', 2 => 'SIAGA', 3 => 'AWAS'];
    $row['status_text'] = $stNames[$calcStatus] ?? 'NORMAL';

    // Probe Level Kontak Air
    foreach (['probe_waspada', 'probe_siaga', 'probe_awas', 'relay_on'] as $k) {
        $row[$k] = (bool)($row[$k] ?? false);
    }
    $row['probe_waspada_level'] = $row['probe_waspada'] ? 1 : null;
    $row['probe_siaga_level'] = $row['probe_siaga'] ? 2 : null;
    $row['probe_awas_level'] = $row['probe_awas'] ? 3 : null;
    $row['probe_level'] = $row['probe_awas_level'] ?? $row['probe_siaga_level'] ?? $row['probe_waspada_level'];
    $row['validation_match'] = ($row['calculated_status'] > 0 && $row['calculated_status'] === $row['probe_level']);

    // Anemometer & Kecepatan Angin
    $row['wind_valid'] = (bool)($row['wind_valid'] ?? false);
    $row['wind_hz'] = isset($row['wind_hz']) && $row['wind_hz'] !== null ? (float)$row['wind_hz'] : null;
    $row['wind_mps'] = isset($row['wind_mps']) && $row['wind_mps'] !== null ? (float)$row['wind_mps'] : null;
    $row['wind_pulses'] = isset($row['wind_pulses']) && $row['wind_pulses'] !== null ? (int)$row['wind_pulses'] : null;
    $row['wind_window_ms'] = isset($row['wind_window_ms']) && $row['wind_window_ms'] !== null ? (int)$row['wind_window_ms'] : null;

    // Barometer & Suhu BMP180
    $row['bmp180_valid'] = (bool)($row['bmp180_valid'] ?? false);
    $row['pressure_hpa'] = isset($row['pressure_hpa']) && $row['pressure_hpa'] !== null ? (float)$row['pressure_hpa'] : null;
    $row['bmp180_temp_c'] = isset($row['bmp180_temp_c']) && $row['bmp180_temp_c'] !== null ? (float)$row['bmp180_temp_c'] : null;

    // Status Perangkat & Diagnostik
    $row['device_status'] = (int)($row['device_status'] ?? 0);
    $row['config_version'] = (int)($row['config_version'] ?? ($s['version'] ?? 1));
    $row['calibration_version'] = (int)($row['calibration_version'] ?? ($s['version'] ?? 1));
    $row['rssi'] = (int)($row['rssi'] ?? -70);
    $row['relay_on'] = (bool)($row['relay_on'] ?? false);

    return $row;
}

// 12. Audio Command Antrean Suara ESP32 (menggunakan tabel audio_commands dewas_db)
function latestAudioCommand(?string $deviceId = null): ?array {
    $devId = $deviceId ?? selectedDevice();
    try {
        $q = db()->prepare("
            SELECT id, track, state, created_at, acknowledged_at,
                   TIMESTAMPDIFF(SECOND, created_at, CURRENT_TIMESTAMP()) AS age
            FROM audio_commands
            WHERE device_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $q->execute([$devId]);
        $r = $q->fetch();
        if (!$r) return null;
        $r['id'] = (int)$r['id'];
        $r['track'] = (int)$r['track'];
        if ($r['state'] === 'pending' && (int)$r['age'] > 30) {
            $r['state'] = 'expired';
        }
        return $r;
    } catch (Throwable $e) {
        return null;
    }
}

<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

try {
    $action = $_GET['action'] ?? 'latest';
    // Toleransi jika URL salah ketik menggunakan tanda tanya kedua (?action=history?device_id=xxx)
    if (str_contains($action, '?')) {
        [$cleanAction, $extraQuery] = explode('?', $action, 2);
        $action = $cleanAction;
        parse_str($extraQuery, $extraParams);
        $_GET = array_merge($extraParams, $_GET);
    }
    $method = $_SERVER['REQUEST_METHOD'];
    $device = selectedDevice();

    // 1. Informasi Build & Skema
    if ($action === 'build' && $method === 'GET') {
        reply([
            'build' => APP_BUILD,
            'php' => PHP_VERSION,
            'database' => 'dewas_db',
            'domain' => 'sea',
            'backend_sync' => 'dewas-be'
        ]);
    }

    // 2. Daftar Perangkat Sensor Laut (tabel sensors)
    if ($action === 'devices' && $method === 'GET') {
        try {
            $q = db()->query("SELECT id, name, type, device_id, device_token, domain, location_name, latitude, longitude, config, timestamp FROM sensors WHERE domain = 'sea' ORDER BY id ASC");
            $sensors = $q->fetchAll();
            reply(['devices' => $sensors]);
        } catch (Throwable $e) {
            reply(['devices' => [['device_id' => $device, 'name' => 'Stasiun Laut ' . $device]]]);
        }
    }

    // 3. Ambil Konfigurasi Sensor (tabel sensors.config)
    if ($action === 'settings' && $method === 'GET') {
        $devId = $_GET['device_id'] ?? $_SERVER['HTTP_X_DEVICE_ID'] ?? $device;
        deviceAuth($devId);
        reply(settings($devId));
    }

    // 4. Pengujian Suara Manual
    if ($action === 'test_audio' && $method === 'POST') {
        requireOperator();
        session_start();
        $d = body();
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($_SESSION['dewaslaut_csrf']) || !hash_equals($_SESSION['dewaslaut_csrf'], $csrfToken)) {
            reply(['error' => 'Sesi berakhir, muat ulang halaman'], 403);
        }
        session_write_close();

        if (!isset($d['track']) || !is_int($d['track']) || $d['track'] < 1 || $d['track'] > 4) {
            reply(['error' => 'Nomor file harus 1, 2, 3, atau 4'], 422);
        }

        $devId = $d['device_id'] ?? $device;
        $q = db()->prepare("
            SELECT TIMESTAMPDIFF(SECOND, STR_TO_DATE(recorded_at, '%Y-%m-%d %H:%i:%s'), CURRENT_TIMESTAMP())
            FROM telemetry_logs
            WHERE sensor_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $q->execute([$devId]);
        $age = $q->fetchColumn();

        if ($age === false) {
            // Fallback cek telemetry_laut jika belum ada di telemetry_logs
            try {
                $q2 = db()->prepare("SELECT TIMESTAMPDIFF(SECOND, received_at, CURRENT_TIMESTAMP()) FROM telemetry_laut WHERE device_id = ? ORDER BY id DESC LIMIT 1");
                $q2->execute([$devId]);
                $age = $q2->fetchColumn();
            } catch (Throwable $e) {}
        }

        if ($age === false || (int)$age > 30) {
            reply(['error' => 'Perangkat offline. Tunggu koneksi ESP32 sebelum tes.'], 409);
        }

        $q = db()->prepare("SELECT COUNT(*) FROM audio_commands WHERE device_id = ? AND state = 'pending' AND created_at >= CURRENT_TIMESTAMP() - INTERVAL 30 SECOND");
        $q->execute([$devId]);
        if ((int)$q->fetchColumn() >= 5) {
            reply(['error' => 'Antrean tes penuh. Tunggu perintah sebelumnya diproses.'], 429);
        }

        $q = db()->prepare('INSERT INTO audio_commands(device_id, track) VALUES(?, ?)');
        $q->execute([$devId, $d['track']]);
        reply(['ok' => true, 'id' => (int)db()->lastInsertId(), 'message' => 'Perintah tes masuk antrean, menunggu ESP32.'], 202);
    }

    // 5. Polling Perintah Suara oleh ESP32
    if ($action === 'audio_command' && $method === 'GET') {
        $devId = deviceAuth($device);
        $q = db()->prepare("
            SELECT id, track
            FROM audio_commands
            WHERE device_id = ? AND state = 'pending' AND created_at >= CURRENT_TIMESTAMP() - INTERVAL 30 SECOND
            ORDER BY id ASC
            LIMIT 1
        ");
        $q->execute([$devId]);
        $r = $q->fetch();
        reply(['command' => $r ? ['id' => (int)$r['id'], 'track' => (int)$r['track']] : null]);
    }

    // 6. Acknowledgment Eksekusi Suara dari ESP32
    if ($action === 'audio_ack' && $method === 'POST') {
        $devId = deviceAuth($device);
        $d = body();
        if (!isset($d['id']) || !is_int($d['id']) || $d['id'] < 1 || $d['id'] > 4294967295) {
            reply(['error' => 'ID perintah tidak valid'], 422);
        }
        $q = db()->prepare("UPDATE audio_commands SET state = 'processed', acknowledged_at = CURRENT_TIMESTAMP() WHERE id = ? AND device_id = ? AND state = 'pending'");
        $q->execute([$d['id'], $devId]);
        reply(['ok' => true]);
    }

    // 7. INGEST TELEMETRI DARI ESP32 (Sinkron ke telemetry_logs domain 'sea' & sensors)
    if ($action === 'ingest' && $method === 'POST') {
        $d = body();
        $targetDev = $d['device_id'] ?? '';
        if (!is_string($targetDev) || $targetDev === '') {
            reply(['error' => 'device_id wajib diisi'], 400);
        }

        // Autentikasi perangkat dari sensors.device_token
        $devId = deviceAuth($targetDev);

        if (!is_string($d['boot_id'] ?? null) || !preg_match('/^[a-f0-9]{16}$/D', $d['boot_id'])) {
            reply(['error' => 'Identitas boot_id perangkat tidak valid (wajib hex 16 karakter)'], 400);
        }

        foreach (['seq', 'uptime_ms', 'config_version'] as $k) {
            if (!isset($d[$k]) || !is_int($d[$k]) || $d[$k] < 0 || $d[$k] > 4294967295) {
                reply(['error' => 'Nilai ' . $k . ' tidak valid'], 400);
            }
        }

        foreach (['a16_valid', 'probe_waspada', 'probe_siaga', 'probe_awas', 'relay_on'] as $k) {
            if (!isset($d[$k]) || !is_bool($d[$k])) {
                reply(['error' => 'Boolean ' . $k . ' tidak valid'], 400);
            }
        }

        if (!isset($d['device_status']) || !is_int($d['device_status']) || $d['device_status'] < 0 || $d['device_status'] > 3 ||
            !isset($d['rssi']) || !is_int($d['rssi']) || $d['rssi'] < -150 || $d['rssi'] > 0) {
            reply(['error' => 'Status/RSSI tidak valid'], 400);
        }

        if ($d['a16_valid']) {
            if (!isset($d['distance_mm']) || !is_int($d['distance_mm']) || $d['distance_mm'] < 500 || $d['distance_mm'] > 15000) {
                reply(['error' => 'Jarak di luar rentang'], 400);
            }
        } else {
            $d['distance_mm'] = null;
        }

        $expected = [$d['probe_waspada'] ? 1 : null, $d['probe_siaga'] ? 2 : null, $d['probe_awas'] ? 3 : null];
        foreach (['probe_waspada_level', 'probe_siaga_level', 'probe_awas_level'] as $i => $k) {
            if (array_key_exists($k, $d) && $d[$k] !== $expected[$i]) reply(['error' => 'Level probe tidak cocok'], 400);
            $d[$k] = $expected[$i];
        }
        $highest = $expected[2] ?? $expected[1] ?? $expected[0];
        if (array_key_exists('probe_level', $d) && $d['probe_level'] !== $highest) reply(['error' => 'Level tertinggi tidak cocok'], 400);
        $d['probe_level'] = $highest;

        foreach (['wind_valid', 'bmp180_valid'] as $k) {
            if (!is_bool($d[$k] ?? null)) reply(['error' => 'Boolean ' . $k . ' tidak valid'], 400);
        }

        foreach (['wind_hz' => [0, 1000], 'wind_mps' => [0, 99999999], 'pressure_hpa' => [300, 1100], 'bmp180_temp_c' => [-40, 85]] as $k => [$min, $max]) {
            $v = $d[$k] ?? null;
            if ($v !== null && (!numericValue($v) || $v < $min || $v > $max)) reply(['error' => 'Nilai ' . $k . ' tidak valid'], 400);
            $d[$k] = $v;
        }

        foreach (['wind_pulses' => 0, 'wind_window_ms' => 1] as $k => $min) {
            $v = $d[$k] ?? null;
            if ($v !== null && (!is_int($v) || $v < $min || $v > 4294967295)) reply(['error' => 'Nilai ' . $k . ' tidak valid'], 400);
            $d[$k] = $v;
        }

        if (!$d['wind_valid']) {
            $d['wind_mps'] = null;
        } elseif ($d['wind_mps'] === null || $d['wind_hz'] === null) {
            reply(['error' => 'Data angin tidak lengkap'], 400);
        }

        if (!$d['bmp180_valid']) {
            $d['pressure_hpa'] = null;
            $d['bmp180_temp_c'] = null;
        } elseif ($d['pressure_hpa'] === null || $d['bmp180_temp_c'] === null) {
            reply(['error' => 'Data BMP180 tidak lengkap'], 400);
        }

        // Ambil konfigurasi kalibrasi sensor dari database sensors.config
        $cal = settings($devId);
        $matching = (int)$d['config_version'] === $cal['version'];
        $height = ($d['a16_valid'] && $matching && $d['distance_mm'] !== null)
            ? ($cal['height_cm'] - ($d['distance_mm'] / 10.0) + $cal['offset_cm'])
            : null;
        $heightStored = $height === null ? null : (int)round($height);
        $levelStored = $height === null ? null : classify($height, $cal);
        $stNames = [0 => 'NORMAL', 1 => 'WASPADA', 2 => 'SIAGA', 3 => 'AWAS'];
        $statusText = $stNames[$levelStored ?? 0] ?? 'NORMAL';
        $validationMatch = ($levelStored !== null && $levelStored > 0 && $levelStored === $d['probe_level']);

        // 100% METADATA JSON LENGKAP UNTUK dewas-be (tabel telemetry_logs)
        $metadata = [
            'device_id' => $devId,
            'sensor_id' => $devId,
            'domain' => 'sea',
            'boot_id' => $d['boot_id'],
            'seq' => (int)$d['seq'],
            'uptime_ms' => (int)$d['uptime_ms'],
            'distance_mm' => $d['distance_mm'],
            'height_cm' => $heightStored,
            'calibrated_height_cm' => $heightStored,
            'calibrated_status' => $levelStored,
            'calibration_version' => $matching ? (int)$cal['version'] : null,
            'calculated_status' => $levelStored ?? 0,
            'status' => $statusText,
            'status_text' => $statusText,
            'a16_valid' => (bool)$d['a16_valid'],
            'probe_waspada' => (bool)$d['probe_waspada'],
            'probe_siaga' => (bool)$d['probe_siaga'],
            'probe_awas' => (bool)$d['probe_awas'],
            'probe_waspada_level' => $d['probe_waspada_level'],
            'probe_siaga_level' => $d['probe_siaga_level'],
            'probe_awas_level' => $d['probe_awas_level'],
            'probe_level' => $d['probe_level'],
            'validation_match' => (bool)$validationMatch,
            'wind_valid' => (bool)$d['wind_valid'],
            'wind_hz' => $d['wind_hz'],
            'wind_mps' => $d['wind_mps'],
            'wind_pulses' => $d['wind_pulses'],
            'wind_window_ms' => $d['wind_window_ms'],
            'bmp180_valid' => (bool)$d['bmp180_valid'],
            'pressure_hpa' => $d['pressure_hpa'],
            'bmp180_temp_c' => $d['bmp180_temp_c'],
            'relay_on' => (bool)$d['relay_on'],
            'device_status' => (int)$d['device_status'],
            'config_version' => (int)$d['config_version'],
            'rssi' => (int)$d['rssi'],
            'value' => $heightStored,
            'water_level' => $heightStored,
            'unit' => 'cm',
            'battery' => 95,
            'signal' => $d['rssi'] . ' dBm',
            'signal_type' => 'WiFi / LoRa (' . $d['rssi'] . ' dBm)',
            'tide_type' => $statusText,
        ];

        $nowStr = date('Y-m-d H:i:s');
        $insertedId = 0;

        // 1. Simpan ke telemetry_logs (Struktur Baku dewas-be: id, sensor_id, domain, metadata_json, recorded_at)
        try {
            $q = db()->prepare("
                INSERT INTO telemetry_logs (sensor_id, domain, metadata_json, recorded_at)
                VALUES (?, 'sea', ?, ?)
            ");
            $q->execute([$devId, json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $nowStr]);
            $insertedId = (int)db()->lastInsertId();
        } catch (Throwable $e) {
            error_log("[DEWAS-LAUT] Error inserting into telemetry_logs: " . $e->getMessage());
        }

        // 2. Simpan juga ke telemetry_laut jika tabelnya tersedia (kompatibilitas backward)
        try {
            $cols = explode(',', 'device_id,boot_id,seq,uptime_ms,distance_mm,a16_valid,probe_waspada,probe_siaga,probe_awas,relay_on,device_status,config_version,rssi,probe_level,wind_valid,wind_hz,wind_pulses,wind_window_ms,wind_mps,bmp180_valid,pressure_hpa,bmp180_temp_c,calibrated_height_cm,calibrated_status,calibration_version');
            $d['calibrated_height_cm'] = $heightStored;
            $d['calibrated_status'] = $levelStored;
            $d['calibration_version'] = $matching ? (int)$cal['version'] : null;
            $values = array_map(static function($k) use ($d) {
                $v = $d[$k];
                return is_bool($v) ? (int)$v : $v;
            }, $cols);

            $qLegacy = db()->prepare('INSERT INTO telemetry_laut(' . implode(',', $cols) . ') VALUES(' . implode(',', array_fill(0, count($cols), '?')) . ') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
            $qLegacy->execute($values);
            if ($insertedId === 0) {
                $insertedId = (int)db()->lastInsertId();
            }
        } catch (Throwable $e) {}

        // 3. Perbarui timestamp pada tabel sensors dewas-be
        try {
            $qUp = db()->prepare("UPDATE sensors SET timestamp = NOW() WHERE device_id = ?");
            $qUp->execute([$devId]);
        } catch (Throwable $e) {}

        reply(['ok' => true, 'id' => $insertedId]);
    }

    // 8. SIMPAN PENGATURAN KALIBRASI (Disimpan ke sensors.config JSON)
    if ($action === 'save' && $method === 'POST') {
        requireOperator();
        session_start();
        $d = body();
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($_SESSION['dewaslaut_csrf']) || !hash_equals($_SESSION['dewaslaut_csrf'], $csrfToken)) {
            reply(['error' => 'Sesi berakhir, muat ulang halaman'], 403);
        }
        session_write_close();

        $targetDev = $d['device_id'] ?? $device;
        if (!validateSettings($d)) {
            reply(['error' => 'Periksa tinggi sensor, offset, dan urutan Waspada < Siaga < Awas. Awas harus di luar zona buta 50 cm.'], 422);
        }

        $s = settings($targetDev, true);
        $newVersion = ($s['version'] ?? 1) + 1;
        $awasVal = (float)($d['awas_cm'] ?? $d['bahaya_cm'] ?? 150.0);
        $waspadaVal = (float)($d['waspada_cm'] ?? 70.0);
        $siagaVal = (float)($d['siaga_cm'] ?? 100.0);

        $cfg = array_merge($s, [
            'height_cm' => (float)$d['height_cm'],
            'offset_cm' => (float)$d['offset_cm'],
            'waspada_cm' => $waspadaVal,
            'siaga_cm' => $siagaVal,
            'awas_cm' => $awasVal,
            'bahaya_cm' => $awasVal,
            'enabled' => (bool)$d['enabled'],
            'version' => $newVersion
        ]);
        if (isset($d['admin_password']) && is_string($d['admin_password'])) {
            $cfg['admin_password'] = trim($d['admin_password']);
        }

        $tokenToSave = isset($d['device_token']) && is_string($d['device_token']) ? trim($d['device_token']) : ($s['device_token'] ?? 'dewas-2026');
        $latToSave = isset($d['latitude']) && numericValue($d['latitude']) ? (float)$d['latitude'] : ($s['latitude'] ?? -8.7492);
        $lngToSave = isset($d['longitude']) && numericValue($d['longitude']) ? (float)$d['longitude'] : ($s['longitude'] ?? 115.2155);

        // 1. Simpan ke tabel sensors dewas-be
        try {
            $q = db()->prepare("
                UPDATE sensors 
                SET config = ?, 
                    device_token = ?, 
                    latitude = ?, 
                    longitude = ?, 
                    timestamp = NOW() 
                WHERE device_id = ?
            ");
            $q->execute([
                json_encode($cfg, JSON_UNESCAPED_SLASHES),
                $tokenToSave,
                $latToSave,
                $lngToSave,
                $targetDev
            ]);
        } catch (Throwable $e) {}

        // 2. Simpan juga ke settings jika tabel settings tersedia
        try {
            $q = db()->prepare("
                UPDATE settings 
                SET height_cm = ?, offset_cm = ?, waspada_cm = ?, siaga_cm = ?, awas_cm = ?, enabled = ?, version = version + 1 
                WHERE device_id = ?
            ");
            $q->execute([
                (float)$d['height_cm'], (float)$d['offset_cm'], $waspadaVal, $siagaVal, $awasVal,
                (int)$d['enabled'], $targetDev
            ]);
        } catch (Throwable $e) {}

        reply(['ok' => true, 'settings' => settings($targetDev, true)]);
    }

    // 9. BACA DATA TELEMETRI TERBARU / RIWAYAT (Membaca dari telemetry_logs dewas-be)
    if (($action === 'latest' || $action === 'history') && $method === 'GET') {
        $targetDev = $device;
        $s = settings($targetDev);
        $limit = ($action === 'latest') ? 1 : 120;
        $rows = [];

        // 1. Ambil dari telemetry_logs (dewas-be)
        try {
            $q = db()->prepare("
                SELECT id, sensor_id AS device_id, metadata_json, recorded_at,
                       TIMESTAMPDIFF(SECOND, STR_TO_DATE(recorded_at, '%Y-%m-%d %H:%i:%s'), CURRENT_TIMESTAMP()) AS age_seconds
                FROM telemetry_logs
                WHERE sensor_id = ? AND domain = 'sea'
                ORDER BY id DESC
                LIMIT {$limit}
            ");
            $q->execute([$targetDev]);
            $rows = $q->fetchAll();
        } catch (Throwable $e) {}

        // 2. Fallback ke telemetry_laut jika telemetry_logs belum ada datanya
        if (empty($rows)) {
            try {
                $q = db()->prepare("
                    SELECT *, TIMESTAMPDIFF(MICROSECOND, received_at, CURRENT_TIMESTAMP(3))/1000000 AS age_seconds
                    FROM telemetry_laut
                    WHERE device_id = ?
                    ORDER BY id DESC
                    LIMIT {$limit}
                ");
                $q->execute([$targetDev]);
                $rows = $q->fetchAll();
            } catch (Throwable $e) {}
        }

        if ($action === 'latest') {
            $r = $rows ? recordView($rows[0], $s) : null;
            $online = ($r !== null && (float)($r['age_seconds'] ?? 999) <= 20);
            reply([
                'build' => APP_BUILD,
                'settings' => $s,
                'online' => $online,
                'data' => $r,
                'audio_command' => latestAudioCommand($targetDev)
            ]);
        }

        reply([
            'build' => APP_BUILD,
            'rows' => array_map(function($r) use ($s) {
                return recordView($r, $s);
            }, $rows),
            'note' => 'Tinggi dan level A16 riwayat memakai kalibrasi saat data diterima atau kalibrasi saat ini.'
        ]);
    }

    reply(['error' => 'Endpoint/metode tidak ditemukan'], 404);
} catch (Throwable $e) {
    error_log((string)$e);
    reply(['error' => 'Server/database belum siap: ' . $e->getMessage()], 500);
}

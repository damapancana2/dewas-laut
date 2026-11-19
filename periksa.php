<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
header('Cache-Control: no-store');

function esc($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$checks = [];
$connected = false;
$dev = selectedDevice();

try {
    $pdo = db();
    $connected = true;
    $checks[] = 'MySQL terhubung ke database dewas_db.';

    foreach (['sensors', 'telemetry_logs', 'audio_commands', 'telemetry_laut'] as $table) {
        try {
            $count = $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
            $checks[] = 'Tabel ' . $table . ': tersedia (' . $count . ' baris).';
        } catch (Throwable $e) {
            if ($table === 'telemetry_laut') {
                $checks[] = 'Tabel telemetry_laut (legacy): belum ada (menggunakan telemetry_logs).';
            } else {
                $checks[] = 'Tabel ' . $table . ': belum dapat diakses (' . $e->getMessage() . ').';
            }
        }
    }

    $sensor = getSensor($dev, true);
    if ($sensor) {
        $checks[] = 'Sensor ' . $dev . ': terdaftar di tabel sensors (domain: ' . ($sensor['domain'] ?? 'sea') . ', token: ' . (!empty($sensor['device_token']) ? 'terpasang' : 'belum diatur') . ').';
    } else {
        $checks[] = 'Sensor ' . $dev . ': belum terdaftar di tabel sensors.';
    }

    $s = settings($dev, true);
    $checks[] = 'Pengaturan ' . $dev . ': versi ' . $s['version'] . ' (Tinggi ' . $s['height_cm'] . 'cm, Offset ' . $s['offset_cm'] . 'cm, Waspada ' . $s['waspada_cm'] . 'cm, Siaga ' . $s['siaga_cm'] . 'cm, Awas ' . $s['awas_cm'] . 'cm).';

    $q = $pdo->prepare("
        SELECT recorded_at,
               TIMESTAMPDIFF(SECOND, STR_TO_DATE(recorded_at, '%Y-%m-%d %H:%i:%s'), CURRENT_TIMESTAMP()) AS age_seconds
        FROM telemetry_logs
        WHERE sensor_id = ? AND domain = 'sea'
        ORDER BY id DESC
        LIMIT 1
    ");
    $q->execute([$dev]);
    $lastLog = $q->fetch();

    if ($lastLog) {
        $checks[] = 'Data telemetri terbaru di telemetry_logs: ' . $lastLog['recorded_at'] . ' (' . $lastLog['age_seconds'] . ' detik lalu).';
    } else {
        $checks[] = 'Data telemetri di telemetry_logs: belum ada data masuk untuk ' . $dev . '.';
    }
} catch (Throwable $e) {
    error_log((string)$e);
    $checks[] = 'Error koneksi/pemeriksaan: ' . $e->getMessage();
}
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Pemeriksaan DEWaS Laut (Struktur dewas-be)</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 40px; line-height: 1.6; color: #1e293b; background: #f8fafc; }
        .card { background: white; padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; max-width: 720px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        h1 { margin-top: 0; font-size: 20px; color: #0f766e; }
        ul { padding-left: 20px; }
        li { margin-bottom: 8px; }
        a { color: #0f766e; font-weight: bold; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 12px; font-weight: bold; background: #ccfbf1; color: #0f766e; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Pemeriksaan DEWaS Laut <span class="badge"><?= esc(APP_BUILD) ?></span></h1>
        <p>Struktur Tabel: <strong>dewas_db (sensors &amp; telemetry_logs)</strong></p>
        <ul>
            <?php foreach ($checks as $check): ?>
                <li><?= esc($check) ?></li>
            <?php endforeach; ?>
        </ul>
        <p><a href="./">Buka Dashboard Laut</a> &middot; <a href="api.php?action=build">Versi API</a> &middot; <a href="api.php?action=devices">Daftar Sensor</a></p>
    </div>
</body>
</html>

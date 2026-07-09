<?php
/* ============================================================
   ASETKU · api.php
   Menyimpan & membaca catatan aset sebagai file JSON terenkripsi
   di folder /data pada domain ini. Tidak memakai layanan luar.
   ============================================================ */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Hanya terima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metode tidak diizinkan']);
    exit;
}

// Siapkan folder data
$dir = __DIR__ . '/data/';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// Baca body JSON
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Permintaan tidak valid']);
    exit;
}

// Validasi ID: wajib hash SHA-256 (64 karakter heksadesimal).
// Ini mencegah nama file aneh / path traversal seperti "../../".
$id = $body['id'] ?? '';
if (!preg_match('/^[a-f0-9]{64}$/', $id)) {
    http_response_code(400);
    echo json_encode(['error' => 'ID tidak valid']);
    exit;
}

$file   = $dir . $id . '.json';
$action = $body['action'] ?? '';

if ($action === 'get') {
    // Ambil data: ada → kirim isinya, tidak ada → null (kode tak terdaftar)
    if (is_file($file)) {
        echo json_encode(['data' => file_get_contents($file)]);
    } else {
        echo json_encode(['data' => null]);
    }
    exit;
}

if ($action === 'put') {
    // Simpan data (sudah terenkripsi dari browser, server tak bisa membacanya)
    $data = $body['data'] ?? null;
    if (!is_string($data) || strlen($data) < 10 || strlen($data) > 500000) {
        http_response_code(400);
        echo json_encode(['error' => 'Data tidak valid atau terlalu besar']);
        exit;
    }
    // Pastikan formatnya sesuai paket enkripsi: base64.base64.base64
    if (!preg_match('/^[A-Za-z0-9+\/=]+\.[A-Za-z0-9+\/=]+\.[A-Za-z0-9+\/=]+$/', $data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Format data tidak dikenali']);
        exit;
    }
    if (file_put_contents($file, $data, LOCK_EX) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Gagal menulis file — cek izin folder data']);
        exit;
    }
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Aksi tidak dikenal']);

<?php
declare(strict_types=1);

/* ===== Pengaturan dasar ===== */
date_default_timezone_set('Asia/Jakarta');
const APP_NAME   = 'CatatanKu';
const DATA_DIR   = __DIR__ . '/data';
const UPLOAD_DIR = __DIR__ . '/uploads';
const MAX_IMG    = 2097152; // 2 MB

session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_start();

/* ===== Helper umum ===== */
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function uid(): string { return bin2hex(random_bytes(6)); }
function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function redirect(string $to): void { header('Location: ' . $to); exit; }
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function fmt_date($t): string { return date('d M Y, H:i', (int)$t); }

/* ===== Penyimpanan JSON (dengan file lock) ===== */
function db_path(string $n): string { return DATA_DIR . "/$n.json"; }

function db_read(string $n): array {
    $f = db_path($n);
    if (!is_file($f)) return [];
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : [];
}

/** Baca-ubah-tulis aman. $fn menerima array by reference dan boleh mengembalikan nilai. */
function db_update(string $n, callable $fn) {
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0775, true);
    $lock = fopen(DATA_DIR . '/.lock', 'c');
    flock($lock, LOCK_EX);
    $data = db_read($n);
    $res  = $fn($data);
    $tmp  = db_path($n) . '.tmp';
    file_put_contents($tmp, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    rename($tmp, db_path($n));
    flock($lock, LOCK_UN);
    fclose($lock);
    return $res;
}

/* ===== Inisialisasi pertama kali (akun admin bawaan) ===== */
function bootstrap(): void {
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
    if (!db_read('users')) {
        db_update('users', function (array &$u) {
            if (!$u) $u[] = [
                'id' => uid(), 'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'role' => 'admin', 'banned' => false, 'created' => time(),
            ];
        });
    }
    if (!is_file(db_path('notes'))) db_update('notes', function (array &$n) {});
}
bootstrap();

/* ===== Pengguna & sesi ===== */
function find_user_by(string $field, $val): ?array {
    foreach (db_read('users') as $u) {
        if ($field === 'username' ? strtolower($u['username']) === strtolower((string)$val) : ($u[$field] ?? null) === $val) return $u;
    }
    return null;
}

function current_user(): ?array {
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    if (!empty($_SESSION['uid'])) {
        $u = find_user_by('id', $_SESSION['uid']);
        if ($u && empty($u['banned'])) $cache = $u;
        else unset($_SESSION['uid']); // dihapus / dibanned -> otomatis keluar
    }
    return $cache;
}

function require_login(): array {
    $u = current_user();
    if (!$u) { flash('error', 'Silakan masuk terlebih dahulu.'); redirect('login.php'); }
    return $u;
}

function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') { flash('error', 'Halaman ini khusus admin.'); redirect('notes.php'); }
    return $u;
}

function valid_username(string $n): bool { return (bool)preg_match('/^[A-Za-z0-9_]{3,20}$/', $n); }

/* ===== CSRF ===== */
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function verify_csrf(): void {
    if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) {
        flash('error', 'Sesi kedaluwarsa, silakan coba lagi.');
        redirect(basename($_SERVER['SCRIPT_NAME']));
    }
}

/* ===== Hyperlink: URL otomatis + format [teks](https://url) ===== */
function linkify(string $text): string {
    $safe = e($text);
    $out = preg_replace_callback(
        '/\[([^\]\n]+)\]\((https?:\/\/[^\s<)]+)\)|(https?:\/\/[^\s<]+)/u',
        function ($m) {
            if (!empty($m[2])) { $label = $m[1]; $url = $m[2]; $tail = ''; }
            else {
                $url = $m[3];
                preg_match('/[.,;:!?)]+$/', $url, $t);
                $tail = $t[0] ?? '';
                if ($tail !== '') $url = substr($url, 0, -strlen($tail));
                $label = $url;
            }
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer nofollow">' . $label . '</a>' . $tail;
        },
        $safe
    );
    return nl2br($out ?? $safe, false);
}

/* ===== Upload gambar ===== */
function save_image(array $f): array { // [namaFile|null, pesanError|null]
    $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($err !== UPLOAD_ERR_OK) return [null, 'Upload gambar gagal.'];
    if ($f['size'] > MAX_IMG) return [null, 'Ukuran gambar maksimal 2 MB.'];
    $info = @getimagesize($f['tmp_name']);
    $map  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!$info || !isset($map[$info['mime']])) return [null, 'Format gambar harus JPG, PNG, GIF, atau WebP.'];
    $name = bin2hex(random_bytes(12)) . '.' . $map[$info['mime']];
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $name)) return [null, 'Gagal menyimpan gambar.'];
    return [$name, null];
}
function delete_image(?string $n): void {
    if ($n && preg_match('/^[a-f0-9]{24}\.(jpg|png|gif|webp)$/', $n)) @unlink(UPLOAD_DIR . '/' . $n);
}

/* ===== Layout ===== */
function page_head(string $title, bool $full_nav = true): void {
    $u = current_user();
    ?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= APP_NAME ?></title>
<script>try{if(localStorage.getItem("theme")==="dark")document.documentElement.setAttribute("data-theme","dark")}catch(e){}</script>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="nav">
  <div class="wrap nav-in">
    <a class="brand" href="index.php"><span class="logo">✎</span><?= APP_NAME ?></a>
    <nav class="nav-r">
      <?php if ($u && $full_nav): ?>
        <a href="notes.php">Catatan</a>
        <?php if ($u['role'] === 'admin'): ?><a href="admin.php">Admin</a><?php endif; ?>
        <span class="who"><?= e($u['username']) ?></span>
      <?php endif; ?>
      <button type="button" id="themeBtn" class="icon-btn" aria-label="Ganti tema">🌙</button>
      <?php if ($u && $full_nav): ?>
        <form method="post" action="logout.php"><?= csrf_field() ?><button class="btn btn-ghost btn-sm">Keluar</button></form>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="wrap">
<?php
    foreach ($_SESSION['flash'] ?? [] as [$t, $m]) echo '<div class="flash ' . e($t) . '">' . e($m) . '</div>';
    unset($_SESSION['flash']);
}

function page_foot(): void { ?>
</main>
<script src="assets/app.js"></script>
</body>
</html>
<?php }

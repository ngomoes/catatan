<?php
require __DIR__ . '/config.php';
if (current_user()) redirect('notes.php');

if (is_post()) {
    verify_csrf();
    $name = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');
    $err = null;
    if (!valid_username($name))      $err = 'Username 3–20 karakter: huruf, angka, atau garis bawah.';
    elseif (strlen($pass) < 6)       $err = 'Password minimal 6 karakter.';
    elseif ($pass !== $pass2)        $err = 'Konfirmasi password tidak cocok.';
    elseif (find_user_by('username', $name)) $err = 'Username sudah dipakai.';

    if (!$err) {
        $id = uid();
        $ok = db_update('users', function (array &$u) use ($id, $name, $pass) {
            foreach ($u as $x) if (strtolower($x['username']) === strtolower($name)) return false;
            $u[] = ['id' => $id, 'username' => $name, 'password' => password_hash($pass, PASSWORD_DEFAULT),
                    'role' => 'user', 'banned' => false, 'created' => time()];
            return true;
        });
        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['uid'] = $id;
            flash('ok', 'Akun berhasil dibuat. Selamat datang!');
            redirect('notes.php');
        }
        $err = 'Username sudah dipakai.';
    }
    flash('error', $err);
    redirect('signup.php');
}
page_head('Daftar', false);
?>
<section class="auth card">
  <h1>Buat akun</h1>
  <p class="muted">Gratis, cukup username dan password.</p>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Username<input name="username" required minlength="3" maxlength="20" pattern="[A-Za-z0-9_]+" autocomplete="username"></label>
    <label>Password<input type="password" name="password" required minlength="6" autocomplete="new-password"></label>
    <label>Ulangi password<input type="password" name="password2" required minlength="6" autocomplete="new-password"></label>
    <button class="btn">Daftar</button>
  </form>
  <p class="muted center">Sudah punya akun? <a href="login.php">Masuk</a></p>
</section>
<?php page_foot();

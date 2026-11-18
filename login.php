<?php
require __DIR__ . '/config.php';
if (current_user()) redirect('notes.php');

if (is_post()) {
    verify_csrf();
    $u = find_user_by('username', trim((string)($_POST['username'] ?? '')));
    if ($u && password_verify((string)($_POST['password'] ?? ''), $u['password'])) {
        if (!empty($u['banned'])) {
            flash('error', 'Akun ini diblokir (banned). Hubungi admin.');
            redirect('login.php');
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = $u['id'];
        redirect('notes.php');
    }
    usleep(400000);
    flash('error', 'Username atau password salah.');
    redirect('login.php');
}
page_head('Masuk', false);
?>
<section class="auth card">
  <h1>Masuk</h1>
  <p class="muted">Lanjutkan ke catatanmu.</p>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Username<input name="username" required autofocus autocomplete="username"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn">Masuk</button>
  </form>
  <p class="muted center">Belum punya akun? <a href="signup.php">Daftar</a></p>
</section>
<?php page_foot();

<?php
require __DIR__ . '/config.php';
$me = require_admin();

/* Hapus semua catatan milik satu pengguna (beserta gambarnya) */
function purge_user_notes(string $uid): void {
    $imgs = db_update('notes', function (array &$n) use ($uid) {
        $im = [];
        foreach ($n as $i => $x) if ($x['user_id'] === $uid) { if (!empty($x['image'])) $im[] = $x['image']; unset($n[$i]); }
        return $im;
    });
    foreach ($imgs as $i) delete_image($i);
}

/* Validasi data pengguna; kembalikan pesan error atau null */
function check_user_input(string $name, string $pass, bool $pass_required, string $except_id = ''): ?string {
    if (!valid_username($name)) return 'Username 3–20 karakter: huruf, angka, atau garis bawah.';
    $dup = find_user_by('username', $name);
    if ($dup && $dup['id'] !== $except_id) return 'Username sudah dipakai.';
    if (($pass_required || $pass !== '') && strlen($pass) < 6) return 'Password minimal 6 karakter.';
    return null;
}

if (is_post()) {
    verify_csrf();
    $act  = $_POST['action'] ?? '';
    $id   = (string)($_POST['id'] ?? '');
    $self = ($id === $me['id']);
    $tab  = ($act === 'note_delete') ? 'notes' : 'users';

    if ($act === 'user_create') {
        $name = trim((string)($_POST['username'] ?? '')); $pass = (string)($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user';
        $err = check_user_input($name, $pass, true);
        if ($err) flash('error', $err);
        else {
            db_update('users', function (array &$u) use ($name, $pass, $role) {
                $u[] = ['id' => uid(), 'username' => $name, 'password' => password_hash($pass, PASSWORD_DEFAULT),
                        'role' => $role, 'banned' => false, 'created' => time()];
            });
            flash('ok', "Pengguna $name dibuat.");
        }
    }

    if ($act === 'user_update') {
        $name = trim((string)($_POST['username'] ?? '')); $pass = (string)($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user';
        $err = check_user_input($name, $pass, false, $id);
        if ($err) flash('error', $err);
        else {
            db_update('users', function (array &$u) use ($id, $name, $pass, $role, $self) {
                foreach ($u as &$x) if ($x['id'] === $id) {
                    $x['username'] = $name;
                    if ($pass !== '') $x['password'] = password_hash($pass, PASSWORD_DEFAULT);
                    if (!$self) $x['role'] = $role;
                }
            });
            flash('ok', 'Data pengguna diperbarui.');
        }
    }

    if ($act === 'user_ban' || $act === 'user_unban') {
        if ($self) flash('error', 'Kamu tidak bisa mem-ban dirimu sendiri.');
        else {
            $ban = ($act === 'user_ban');
            db_update('users', function (array &$u) use ($id, $ban) {
                foreach ($u as &$x) if ($x['id'] === $id) $x['banned'] = $ban;
            });
            flash('ok', $ban ? 'Pengguna di-ban.' : 'Ban pengguna dicabut.');
        }
    }

    if ($act === 'user_delete') {
        if ($self) flash('error', 'Kamu tidak bisa menghapus akunmu sendiri.');
        else {
            purge_user_notes($id);
            db_update('users', function (array &$u) use ($id) {
                foreach ($u as $i => $x) if ($x['id'] === $id) unset($u[$i]);
            });
            flash('ok', 'Pengguna dan semua catatannya dihapus.');
        }
    }

    if ($act === 'note_delete') {
        $img = db_update('notes', function (array &$n) use ($id) {
            foreach ($n as $i => $x) if ($x['id'] === $id) { $im = $x['image'] ?? null; unset($n[$i]); return $im ?: true; }
            return false;
        });
        if ($img) { if (is_string($img)) delete_image($img); flash('ok', 'Catatan dihapus.'); }
    }
    redirect('admin.php?tab=' . $tab);
}

$tab   = ($_GET['tab'] ?? 'users') === 'notes' ? 'notes' : 'users';
$users = db_read('users');
$notes = db_read('notes');
$names = []; $count = [];
foreach ($users as $u) $names[$u['id']] = $u['username'];
foreach ($notes as $n) $count[$n['user_id']] = ($count[$n['user_id']] ?? 0) + 1;
$banned = count(array_filter($users, fn($u) => !empty($u['banned'])));

page_head('Admin');
?>
<h1>Panel admin</h1>
<div class="stats">
  <div class="card stat"><b><?= count($users) ?></b><span class="muted small">Pengguna</span></div>
  <div class="card stat"><b><?= count($notes) ?></b><span class="muted small">Catatan</span></div>
  <div class="card stat"><b><?= $banned ?></b><span class="muted small">Di-ban</span></div>
</div>
<div class="tabs">
  <a href="admin.php?tab=users" class="<?= $tab === 'users' ? 'on' : '' ?>">Pengguna</a>
  <a href="admin.php?tab=notes" class="<?= $tab === 'notes' ? 'on' : '' ?>">Semua catatan</a>
</div>

<?php if ($tab === 'users'): ?>
  <details class="card add-user">
    <summary class="btn btn-sm">+ Tambah pengguna</summary>
    <form method="post" class="stack">
      <?= csrf_field() ?><input type="hidden" name="action" value="user_create">
      <label>Username<input name="username" required minlength="3" maxlength="20" pattern="[A-Za-z0-9_]+"></label>
      <label>Password<input type="password" name="password" required minlength="6" autocomplete="new-password"></label>
      <label>Peran<select name="role"><option value="user">Pengguna</option><option value="admin">Admin</option></select></label>
      <button class="btn">Buat pengguna</button>
    </form>
  </details>

  <?php foreach ($users as $x): $self = ($x['id'] === $me['id']); ?>
  <div class="card urow">
    <div class="uinfo">
      <strong><?= e($x['username']) ?></strong>
      <span class="badge <?= $x['role'] === 'admin' ? 'b-admin' : '' ?>"><?= $x['role'] === 'admin' ? 'Admin' : 'Pengguna' ?></span>
      <?php if (!empty($x['banned'])): ?><span class="badge b-ban">Banned</span><?php endif; ?>
      <?php if ($self): ?><span class="badge">Kamu</span><?php endif; ?>
      <div class="muted small"><?= (int)($count[$x['id']] ?? 0) ?> catatan · bergabung <?= fmt_date($x['created']) ?></div>
    </div>
    <div class="uact">
      <details class="edit">
        <summary class="btn btn-ghost btn-sm">Edit</summary>
        <form method="post" class="stack">
          <?= csrf_field() ?><input type="hidden" name="action" value="user_update"><input type="hidden" name="id" value="<?= e($x['id']) ?>">
          <label>Username<input name="username" value="<?= e($x['username']) ?>" required minlength="3" maxlength="20" pattern="[A-Za-z0-9_]+"></label>
          <label>Password baru <span class="muted small">(kosongkan jika tidak diubah)</span><input type="password" name="password" minlength="6" autocomplete="new-password"></label>
          <?php if (!$self): ?>
          <label>Peran<select name="role"><option value="user" <?= $x['role'] === 'user' ? 'selected' : '' ?>>Pengguna</option><option value="admin" <?= $x['role'] === 'admin' ? 'selected' : '' ?>>Admin</option></select></label>
          <?php endif; ?>
          <button class="btn btn-sm">Simpan perubahan</button>
        </form>
      </details>
      <?php if (!$self): ?>
        <form method="post" data-confirm="<?= !empty($x['banned']) ? 'Cabut ban pengguna ini?' : 'Ban pengguna ini? Ia tidak bisa masuk lagi.' ?>">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($x['id']) ?>">
          <?php if (!empty($x['banned'])): ?>
            <input type="hidden" name="action" value="user_unban"><button class="btn btn-ghost btn-sm">Unban</button>
          <?php else: ?>
            <input type="hidden" name="action" value="user_ban"><button class="btn btn-ghost btn-sm">Ban</button>
          <?php endif; ?>
        </form>
        <form method="post" data-confirm="Hapus pengguna ini beserta semua catatannya?">
          <?= csrf_field() ?><input type="hidden" name="action" value="user_delete"><input type="hidden" name="id" value="<?= e($x['id']) ?>">
          <button class="btn btn-danger btn-sm">Hapus</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

<?php else: ?>
  <?php if (!$notes): ?><p class="empty">Belum ada catatan dari pengguna mana pun.</p><?php endif; ?>
  <section class="grid">
  <?php foreach ($notes as $n): ?>
    <article class="card note">
      <?php if (!empty($n['image'])): ?><a href="uploads/<?= e($n['image']) ?>" target="_blank" rel="noopener"><img src="uploads/<?= e($n['image']) ?>" loading="lazy" alt="Gambar catatan"></a><?php endif; ?>
      <?php if ($n['title'] !== ''): ?><h3><?= e($n['title']) ?></h3><?php endif; ?>
      <?php if ($n['body'] !== ''): ?><div class="body"><?= linkify($n['body']) ?></div><?php endif; ?>
      <footer>
        <span class="muted small">oleh <b><?= e($names[$n['user_id']] ?? '(dihapus)') ?></b> · <?= fmt_date($n['created']) ?></span>
        <form method="post" data-confirm="Hapus catatan ini?">
          <?= csrf_field() ?><input type="hidden" name="action" value="note_delete"><input type="hidden" name="id" value="<?= e($n['id']) ?>">
          <button class="btn btn-danger btn-sm">Hapus</button>
        </form>
      </footer>
    </article>
  <?php endforeach; ?>
  </section>
<?php endif; ?>
<?php page_foot();

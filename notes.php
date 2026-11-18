<?php
require __DIR__ . '/config.php';
$user = require_login();

if (is_post()) {
    verify_csrf();
    $act = $_POST['action'] ?? '';

    if ($act === 'add') {
        $title = trim((string)($_POST['title'] ?? ''));
        $body  = trim((string)($_POST['body'] ?? ''));
        if (mb_strlen($title) > 100)  { flash('error', 'Judul maksimal 100 karakter.');  redirect('notes.php'); }
        if (mb_strlen($body) > 5000)  { flash('error', 'Isi maksimal 5000 karakter.');   redirect('notes.php'); }
        [$img, $err] = save_image($_FILES['image'] ?? []);
        if ($err) { flash('error', $err); redirect('notes.php'); }
        if ($body === '' && !$img) { flash('error', 'Tulis isi catatan atau pilih gambar.'); redirect('notes.php'); }

        db_update('notes', function (array &$n) use ($user, $title, $body, $img) {
            array_unshift($n, ['id' => uid(), 'user_id' => $user['id'], 'title' => $title,
                               'body' => $body, 'image' => $img, 'created' => time()]);
        });
        flash('ok', 'Catatan disimpan.');
    }

    if ($act === 'delete') {
        $id = (string)($_POST['id'] ?? '');
        $img = db_update('notes', function (array &$n) use ($id, $user) {
            foreach ($n as $i => $x) {
                if ($x['id'] === $id && $x['user_id'] === $user['id']) { $im = $x['image'] ?? null; unset($n[$i]); return $im ?: true; }
            }
            return false;
        });
        if ($img) { if (is_string($img)) delete_image($img); flash('ok', 'Catatan dihapus.'); }
    }
    redirect('notes.php');
}

$mine = array_values(array_filter(db_read('notes'), fn($n) => $n['user_id'] === $user['id']));
page_head('Catatan');
?>
<section class="card compose">
  <form method="post" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <input name="title" maxlength="100" placeholder="Judul (opsional)" aria-label="Judul">
    <textarea name="body" rows="4" maxlength="5000" placeholder="Tulis catatan… Tempel link atau pakai [teks](https://alamat.com)" aria-label="Isi catatan"></textarea>
    <img id="preview" class="preview" alt="" hidden>
    <div class="row">
      <label class="btn btn-ghost btn-sm file">📎 Gambar<input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp" hidden></label>
      <span class="muted small" id="fname">Maks. 2 MB</span>
      <button class="btn push">Simpan</button>
    </div>
  </form>
</section>

<?php if (!$mine): ?>
  <p class="empty">Belum ada catatan. Tulis yang pertama di atas.</p>
<?php else: ?>
<section class="grid">
<?php foreach ($mine as $n): ?>
  <article class="card note">
    <?php if (!empty($n['image'])): ?><a href="uploads/<?= e($n['image']) ?>" target="_blank" rel="noopener"><img src="uploads/<?= e($n['image']) ?>" loading="lazy" alt="Gambar catatan"></a><?php endif; ?>
    <?php if ($n['title'] !== ''): ?><h3><?= e($n['title']) ?></h3><?php endif; ?>
    <?php if ($n['body'] !== ''): ?><div class="body"><?= linkify($n['body']) ?></div><?php endif; ?>
    <footer>
      <span class="muted small"><?= fmt_date($n['created']) ?></span>
      <form method="post" data-confirm="Hapus catatan ini?">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($n['id']) ?>">
        <button class="btn btn-danger btn-sm">Hapus</button>
      </form>
    </footer>
  </article>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php page_foot();

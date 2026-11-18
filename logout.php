<?php
require __DIR__ . '/config.php';
if (is_post()) {
    verify_csrf();
    unset($_SESSION['uid']);
    session_regenerate_id(true);
    flash('ok', 'Kamu sudah keluar.');
}
redirect('login.php');

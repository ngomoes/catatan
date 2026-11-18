<?php
require __DIR__ . '/config.php';
redirect(current_user() ? 'notes.php' : 'login.php');

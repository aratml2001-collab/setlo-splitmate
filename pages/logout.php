<?php
require __DIR__ . '/../includes/bootstrap.php';

$wasAdmin = (current_user()['role'] ?? '') === 'admin';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
    logout_user();
}
redirect($wasAdmin ? 'pages/admin-login.php' : 'pages/login.php');

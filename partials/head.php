<?php
/**
 * Page head. Set before including:
 *   $title      page title (required)
 *   $bodyClass  optional extra classes for <body>
 *   $loginPage  where the API client sends expired sessions (default login.php)
 */
// Pages carry the session's CSRF token and personal data: never serve them from a cache.
header('Cache-Control: no-store');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="<?= h(csrf_token()) ?>" />
<meta name="api-base" content="<?= h(url('api/')) ?>" />
<meta name="login-page" content="<?= h($loginPage ?? 'login.php') ?>" />
<title><?= h($title) ?> · Setlo</title>
<link rel="icon" type="image/png" href="<?= h(url('assets/icons/icon-192.png')) ?>" />
<link rel="manifest" href="<?= h(url('manifest.json')) ?>" />
<meta name="theme-color" content="#0d9488" />
<link rel="apple-touch-icon" href="<?= h(url('assets/icons/icon-180.png')) ?>" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-title" content="Setlo" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="<?= h(url('assets/css/app.css')) ?>?v=<?= @filemtime(__DIR__ . '/../assets/css/app.css') ?>" />
<script src="https://cdn.jsdelivr.net/npm/vue@3.5.13/dist/vue.global.prod.js" integrity="sha384-W/1Fp/LgAYO/oTn9Gs+PbeWuMuq1eQCnUMPCeg8POmMYchhzxctjEqtbiCIxDOON" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js" integrity="sha384-YB/DdIkloKoRpclWB8bNcYXWakt57USgtQPDzvnIDHYU0lasD5eWlXVo1S4ODukY" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js" integrity="sha384-8FWZA6BGMXhsfO+BLtrJK0We6gg5o1JyO8xQm6peWDEUs17ACA5ziE/NIAkl9z2k" crossorigin="anonymous"></script>
<script src="<?= h(url('assets/js/api.js')) ?>?v=<?= @filemtime(__DIR__ . '/../assets/js/api.js') ?>"></script>
<script src="<?= h(url('assets/js/ui.js')) ?>?v=<?= @filemtime(__DIR__ . '/../assets/js/ui.js') ?>"></script>
<script src="<?= h(url('assets/js/validate.js')) ?>?v=<?= @filemtime(__DIR__ . '/../assets/js/validate.js') ?>"></script>
<script>
  // Installable app + offline shell. Browsers only allow this on https:// or localhost.
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () { navigator.serviceWorker.register(<?= json_encode(url('sw.js')) ?>).catch(function () {}); });
  }
</script>
</head>
<body class="<?= h($bodyClass ?? '') ?>">

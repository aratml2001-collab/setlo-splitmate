<?php
// The old welcome screen was replaced by the login landing page; keep this URL working for old links/bookmarks.
require __DIR__ . '/../includes/bootstrap.php';
redirect(current_user() ? 'index.php' : 'pages/login.php');

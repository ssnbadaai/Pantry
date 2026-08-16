<?php
$_GET['route'] = trim((string) ($_GET['slug'] ?? ''), '/');
require __DIR__ . '/index.php';

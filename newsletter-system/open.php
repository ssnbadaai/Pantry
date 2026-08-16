<?php
$_GET['route'] = 'open/' . trim((string) ($_GET['token'] ?? ''), '/');
require __DIR__ . '/index.php';

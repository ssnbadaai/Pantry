<?php
$_GET['route'] = 'click/' . trim((string) ($_GET['token'] ?? ''), '/');
require __DIR__ . '/index.php';

<?php
require_once __DIR__ . '/functions.php';
$pageTitle = (string) ($GLOBALS['pageTitle'] ?? 'Reading Club 2000');
$title = $pageTitle === 'Reading Club 2000' ? $pageTitle : $pageTitle . ' | Reading Club 2000';
$bodyClass = trim('site ' . (string) ($GLOBALS['pageClass'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <script>try{if(localStorage.getItem('rc2000-theme')==='dark')document.documentElement.dataset.theme='dark'}catch(e){}</script>
  <link rel="stylesheet" href="<?= e(app_url('public/assets/css/style.css?v=66')) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<?php require __DIR__ . '/navbar.php'; ?>

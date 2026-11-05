<?php /* DSM Klaim - cangkang aplikasi. Semua data lewat api.php. */ require_once __DIR__ . '/lib/db.php'; ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#10394E">
<title>DSM Klaim</title>
<link rel="stylesheet" href="assets/app.css?v=<?= filemtime(__DIR__ . '/assets/app.css') ?>">
</head>
<body>
<div id="app"></div>
<script>window.CLAIM_DSM = <?= dsm_present() ? 'true' : 'false' ?>;</script>
<?php if (dsm_present() && is_file(__DIR__ . '/../assets/person_picker.js')): ?><script src="../assets/person_picker.js?v=<?= filemtime(__DIR__ . '/../assets/person_picker.js') ?>"></script><?php endif; ?>
<script src="assets/core.js?v=<?= filemtime(__DIR__ . '/assets/core.js') ?>"></script>
<script src="assets/sign.js?v=<?= filemtime(__DIR__ . '/assets/sign.js') ?>"></script>
<script src="assets/form.js?v=<?= filemtime(__DIR__ . '/assets/form.js') ?>"></script>
<script src="assets/screens.js?v=<?= filemtime(__DIR__ . '/assets/screens.js') ?>"></script>
<script src="assets/admin.js?v=<?= filemtime(__DIR__ . '/assets/admin.js') ?>"></script>
</body>
</html>

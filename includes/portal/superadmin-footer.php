<?php
/** includes/superadmin-footer.php - closes the page opened by superadmin-header.php. Optional $extraJs. */
$extraJs = $extraJs ?? [];
?>
    </main>
</div>
<script src="<?= e(asset('js/main.js')) ?>?v=<?= (int) filemtime(dirname(__DIR__, 2) . '/public/assets/js/main.js') ?>"></script>
<script src="<?= e(asset('js/admin-theme.js')) ?>"></script>
<?php foreach ($extraJs as $js): ?>
<script src="<?= e(asset('js/' . $js)) ?>?v=<?= (int) filemtime(dirname(__DIR__, 2) . '/public/assets/js/' . $js) ?>"></script>
<?php endforeach; ?>
</body>
</html>

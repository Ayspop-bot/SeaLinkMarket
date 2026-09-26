<?php
/**
* SeaLink Web Application
* File: /includes/layout_bottom.php
* Purpose: Closes layout HTML and loads shared JS.
*/
require_once __DIR__ . '/../config/app.php';
?>

<?php require_once __DIR__ . '/notification_modal.php'; ?>
</div>
<script src="<?php echo $base_path; ?>/assets/js/base.js"></script>
</body>
</html>
<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
?>
<!-- Dependencias JavaScript -->
<script src="<?= login_html(login_url('asset/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= login_html(login_url('asset/vendor/jquery/jquery.min.js')) ?>"></script>
<script src="<?= login_html(login_url('asset/vendor/datatables/jquery.dataTables.min.js')) ?>"></script>
<script src="<?= login_html(login_url('asset/vendor/datatables/dataTables.bootstrap5.min.js')) ?>"></script>
<script src="<?= login_html(login_url('asset/js/navegacion.js?v=1')) ?>" defer></script>
<script src="<?= login_html(login_url('asset/js/interfaz.js?v=1')) ?>" defer></script>
</body>
</html>

<?php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config.php';
}
$projectRoot = realpath(__DIR__ . '/../../');
function vendor_asset_js($rel, $projectRoot) {
    $fs = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
    if (file_exists($fs)) return BASE_URL . $rel;
    return null;
}

$jq = vendor_asset_js('assets/vendor/jquery/jquery.min.js', $projectRoot);
$bs = vendor_asset_js('assets/vendor/bootstrap/js/bootstrap.bundle.min.js', $projectRoot);
$lte = vendor_asset_js('assets/vendor/adminlte/js/adminlte.min.js', $projectRoot);
?>

<?php if ($jq): ?>
<script src="<?php echo $jq; ?>"></script>
<?php else: ?>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<?php endif; ?>

<?php if ($bs): ?>
<script src="<?php echo $bs; ?>"></script>
<?php else: ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>

<?php if ($lte): ?>
<script src="<?php echo $lte; ?>"></script>
<?php else: ?>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<?php endif; ?>

<!-- End common scripts -->

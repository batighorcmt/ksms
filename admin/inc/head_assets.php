<?php
// Common <head> with local-first assets and optional CDN fallback
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config.php';
}

$pageTitle = $pageTitle ?? '';

// Helper: build local vendor asset URL if file exists
$projectRoot = realpath(__DIR__ . '/../../'); // points to ksms/ root
function vendor_asset($rel, $projectRoot) {
    $fs = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
    if (file_exists($fs)) {
        return BASE_URL . $rel;
    }
    return null;
}

// Resolve local assets
$faCss = vendor_asset('assets/vendor/fontawesome/css/all.min.css', $projectRoot);
$bsCss = vendor_asset('assets/vendor/bootstrap/css/bootstrap.min.css', $projectRoot);
$lteCss = vendor_asset('assets/vendor/adminlte/css/adminlte.min.css', $projectRoot);
$solaiman = vendor_asset('assets/vendor/solaiman-lipi/solaiman-lipi.css', $projectRoot);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <?php if ($solaiman): ?>
        <link rel="stylesheet" href="<?php echo $solaiman; ?>">
    <?php endif; ?>

    <?php if ($faCss): ?>
        <link rel="stylesheet" href="<?php echo $faCss; ?>">
    <?php else: ?>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php endif; ?>

    <?php if ($bsCss): ?>
        <link rel="stylesheet" href="<?php echo $bsCss; ?>">
    <?php else: ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <?php endif; ?>

    <?php if ($lteCss): ?>
        <link rel="stylesheet" href="<?php echo $lteCss; ?>">
    <?php else: ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <?php endif; ?>

    <style>
        /* Minimal fallback when neither local nor CDN is available */
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, 'SolaimanLipi', sans-serif; }
        .wrapper{min-height:100vh;}
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

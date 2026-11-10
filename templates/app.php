<?php
/**
 * Vollbild-Vorlage für das Vereinsportal – ohne Theme der Homepage.
 */
if (!defined('ABSPATH')) {
    exit;
}
$tsvp_icons = TSVP_URL . 'assets/icons/';
$tsvp_sw    = add_query_arg('tsvp_sw', 1, tsvp_page_url());
?><!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Vereinsportal · <?php echo esc_html(get_bloginfo('name')); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0b6b3a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Vereinsportal">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="manifest" href="<?php echo esc_url(add_query_arg('tsvp_manifest', 1, tsvp_page_url())); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url($tsvp_icons . 'favicon-32.png'); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url($tsvp_icons . 'apple-touch-icon.png'); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(TSVP_URL . 'assets/portal.css?ver=' . TSVP_VERSION); ?>">
</head>
<body class="tsvp-body">
<?php echo tsvp_render_portal(); // phpcs:ignore WordPress.Security.EscapeOutput -- Ausgabe ist intern escaped. ?>
<script src="<?php echo esc_url(TSVP_URL . 'assets/portal.js?ver=' . TSVP_VERSION); ?>"></script>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register(<?php echo wp_json_encode($tsvp_sw, JSON_UNESCAPED_SLASHES); ?>, { scope: <?php echo wp_json_encode(tsvp_app_scope()); ?> }).catch(function () {});
    });
}
</script>
</body>
</html>

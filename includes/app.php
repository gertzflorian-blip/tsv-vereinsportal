<?php
/**
 * Eigenständige App-Ansicht: Die Portal-Seite wird ohne Theme (Kopfzeile,
 * Menü, Fußzeile der Homepage) ausgeliefert und lässt sich auf dem Handy
 * als App installieren (Web-App-Manifest + Service Worker).
 */
if (!defined('ABSPATH')) {
    exit;
}

function tsvp_is_standalone()
{
    $id = (int) get_option('tsvp_page_id');
    return $id && is_page($id) && apply_filters('tsvp_standalone', true);
}

/** Pfad der Portal-Seite, z. B. "/vereinsportal/" – Geltungsbereich der App. */
function tsvp_app_scope()
{
    $path = wp_parse_url(tsvp_page_url(), PHP_URL_PATH) ?: '/';
    return trailingslashit($path);
}

function tsvp_app_name()
{
    return 'Vereinsportal ' . wp_strip_all_tags(get_bloginfo('name'));
}

// Eigene Vorlage statt Theme
add_filter('template_include', function ($template) {
    return tsvp_is_standalone() ? TSVP_DIR . 'templates/app.php' : $template;
}, 99);

// Manifest, Service Worker und Offline-Seite
add_action('template_redirect', function () {
    if (isset($_GET['tsvp_manifest'])) {
        tsvp_send_manifest();
    }
    if (isset($_GET['tsvp_sw'])) {
        tsvp_send_service_worker();
    }
    if (isset($_GET['tsvp_offline'])) {
        tsvp_send_offline();
    }
}, 1);

function tsvp_send_manifest()
{
    $icons = TSVP_URL . 'assets/icons/';
    $manifest = [
        'name'             => tsvp_app_name(),
        'short_name'       => 'Vereinsportal',
        'description'      => 'Abrechnungen, Auszahlungen und Dokumente für den Verein.',
        'lang'             => 'de',
        'start_url'        => tsvp_page_url(),
        'scope'            => tsvp_app_scope(),
        'display'          => 'standalone',
        'background_color' => '#f5f7f5',
        'theme_color'      => '#0b6b3a',
        'icons'            => [
            ['src' => $icons . 'icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => $icons . 'icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ['src' => $icons . 'icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
    ];
    nocache_headers();
    header('Content-Type: application/manifest+json; charset=utf-8');
    echo wp_json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function tsvp_send_service_worker()
{
    $offline = add_query_arg('tsvp_offline', 1, tsvp_page_url());
    $assets  = [
        $offline,
        TSVP_URL . 'assets/portal.css?ver=' . TSVP_VERSION,
        TSVP_URL . 'assets/portal.js?ver=' . TSVP_VERSION,
        TSVP_URL . 'assets/icons/icon-192.png',
    ];
    nocache_headers();
    header('Content-Type: application/javascript; charset=utf-8');
    header('Service-Worker-Allowed: ' . tsvp_app_scope());
    ?>
// Vereinsportal – Service Worker <?php echo esc_js(TSVP_VERSION); ?>

const CACHE = 'tsvp-<?php echo esc_js(TSVP_VERSION); ?>';
const OFFLINE = <?php echo wp_json_encode($offline, JSON_UNESCAPED_SLASHES); ?>;
const ASSETS = <?php echo wp_json_encode($assets, JSON_UNESCAPED_SLASHES); ?>;

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k.startsWith('tsvp-') && k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;

    // Seiten immer frisch vom Server – nur ohne Internet die Offline-Seite.
    if (req.mode === 'navigate') {
        e.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
        return;
    }
    // Design und Symbole des Portals aus dem Zwischenspeicher.
    if (ASSETS.includes(req.url)) {
        e.respondWith(caches.match(req).then((hit) => hit || fetch(req)));
    }
});
<?php
    exit;
}

function tsvp_send_offline()
{
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Offline · Vereinsportal</title>
    <meta name="theme-color" content="#0b6b3a">
    <link rel="stylesheet" href="<?php echo esc_url(TSVP_URL . 'assets/portal.css?ver=' . TSVP_VERSION); ?>">
</head>
<body class="tsvp-body">
<div class="tsvp">
    <div class="tsvp-auth"><div class="tsvp-card tsvp-auth__card">
        <div class="tsvp-auth__brand"><span class="tsvp-logo">TSV</span><div><strong>Vereinsportal</strong><span><?php echo esc_html(get_bloginfo('name')); ?></span></div></div>
        <h2>Keine Verbindung</h2>
        <p class="tsvp-muted">Das Vereinsportal braucht eine Internetverbindung. Sobald du wieder online bist, geht's weiter.</p>
        <p><a class="tsvp-btn tsvp-btn--primary tsvp-btn--block" href="<?php echo esc_url(tsvp_page_url()); ?>">Erneut versuchen</a></p>
    </div></div>
</div>
</body>
</html>
    <?php
    exit;
}

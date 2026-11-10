<?php
/**
 * Updates direkt aus GitHub.
 * Einstellungen: WordPress → Einstellungen → Vereinsportal.
 */
if (!defined('ABSPATH')) {
    exit;
}

use YahnisElsts\PluginUpdateChecker\v6\PucFactory;

require_once TSVP_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

function tsvp_updater_init()
{
    $repo = trim((string) get_option('tsvp_github_repo', ''));
    if ($repo === '' || !preg_match('#^https://github\.com/[^/]+/[^/]+/?$#', $repo)) {
        return;
    }
    $checker = PucFactory::buildUpdateChecker(trailingslashit($repo), TSVP_FILE, 'tsv-vereinsportal');
    $checker->setBranch(get_option('tsvp_github_branch', 'main') ?: 'main');
    $token = trim((string) get_option('tsvp_github_token', ''));
    if ($token !== '') {
        $checker->setAuthentication($token);
    }
}
tsvp_updater_init();

/* ------------------------------------------------------------------ */
/* Einstellungsseite                                                   */
/* ------------------------------------------------------------------ */

add_action('admin_menu', function () {
    add_options_page('Vereinsportal', 'Vereinsportal', 'manage_options', 'tsvp-einstellungen', 'tsvp_settings_page');
});

add_action('admin_init', function () {
    register_setting('tsvp_settings', 'tsvp_github_repo', [
        'type'              => 'string',
        'sanitize_callback' => function ($v) {
            $v = esc_url_raw(trim((string) $v));
            return preg_match('#^https://github\.com/[^/]+/[^/]+/?$#', $v) ? untrailingslashit($v) : '';
        },
    ]);
    register_setting('tsvp_settings', 'tsvp_github_branch', [
        'type'              => 'string',
        'sanitize_callback' => function ($v) {
            $v = preg_replace('/[^A-Za-z0-9._\/-]/', '', (string) $v);
            return $v ?: 'main';
        },
    ]);
    register_setting('tsvp_settings', 'tsvp_github_token', [
        'type'              => 'string',
        'sanitize_callback' => function ($v) {
            $v = trim((string) $v);
            // Leeres Feld = bisherigen Token behalten; "-" = Token löschen.
            if ($v === '') {
                return get_option('tsvp_github_token', '');
            }
            return $v === '-' ? '' : preg_replace('/[^A-Za-z0-9_]/', '', $v);
        },
    ]);
});

add_filter('plugin_action_links_' . plugin_basename(TSVP_FILE), function ($links) {
    $links[] = '<a href="' . esc_url(admin_url('options-general.php?page=tsvp-einstellungen')) . '">Einstellungen</a>';
    return $links;
});

function tsvp_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $hat_token = get_option('tsvp_github_token', '') !== '';
    $check_url = wp_nonce_url(
        add_query_arg(['puc_check_for_updates' => 1, 'puc_slug' => 'tsv-vereinsportal'], self_admin_url('plugins.php')),
        'puc_check_for_updates'
    );
    ?>
    <div class="wrap">
        <h1>Vereinsportal</h1>
        <p>Installierte Version: <strong><?php echo esc_html(TSVP_VERSION); ?></strong> ·
            Portal: <a href="<?php echo esc_url(tsvp_page_url()); ?>" target="_blank"><?php echo esc_html(tsvp_page_url()); ?></a></p>

        <h2>Updates über GitHub</h2>
        <p>Sobald das Repository eingetragen ist, zeigt WordPress neue Versionen wie bei jedem anderen Plugin unter <em>Plugins</em> bzw. <em>Dashboard → Aktualisierungen</em> an.</p>
        <form method="post" action="options.php">
            <?php settings_fields('tsvp_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="tsvp_github_repo">GitHub-Repository</label></th>
                    <td><input name="tsvp_github_repo" id="tsvp_github_repo" type="url" class="regular-text" placeholder="https://github.com/dein-name/tsv-vereinsportal"
                               value="<?php echo esc_attr(get_option('tsvp_github_repo', '')); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tsvp_github_branch">Branch</label></th>
                    <td><input name="tsvp_github_branch" id="tsvp_github_branch" type="text" class="small-text" style="width:10em"
                               value="<?php echo esc_attr(get_option('tsvp_github_branch', 'main')); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tsvp_github_token">Zugriffstoken</label></th>
                    <td><input name="tsvp_github_token" id="tsvp_github_token" type="password" class="regular-text" autocomplete="off"
                               placeholder="<?php echo $hat_token ? '•••••••• gespeichert – leer lassen zum Behalten' : 'nur bei privatem Repository nötig'; ?>">
                        <p class="description">Für ein <strong>privates</strong> Repository: GitHub → Settings → Developer settings → Fine-grained token, nur dieses Repository, Berechtigung „Contents: Read-only“. Zum Löschen ein „-“ eintragen.</p></td>
                </tr>
            </table>
            <?php submit_button('Speichern'); ?>
        </form>

        <?php if (get_option('tsvp_github_repo')) : ?>
            <p><a class="button" href="<?php echo esc_url($check_url); ?>">Jetzt nach Updates suchen</a></p>
        <?php endif; ?>

        <h2>So veröffentlichst du ein Update</h2>
        <ol>
            <li>In <code>tsv-vereinsportal.php</code> die Versionsnummer erhöhen – im Kopf (<code>Version: 0.2.1</code>) <em>und</em> bei <code>TSVP_VERSION</code>.</li>
            <li>Änderungen in den Branch oben pushen.</li>
            <li>Im WordPress-Backend erscheint das Update (spätestens nach 12 Stunden, sofort mit „Jetzt nach Updates suchen“).</li>
        </ol>
    </div>
    <?php
}

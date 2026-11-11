<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ------------------------------------------------------------------ */
/* Kleine Bausteine                                                    */
/* ------------------------------------------------------------------ */

function tsvp_form_fields($action)
{
    echo '<input type="hidden" name="tsvp_action" value="' . esc_attr($action) . '">';
    echo '<input type="hidden" name="_tsvp" value="' . esc_attr(tsvp_nonce($action)) . '">';
}

function tsvp_abteilung_options($selected = 0, $leer = 'Gesamtverein')
{
    $out = $leer !== null ? '<option value="0">' . esc_html($leer) . '</option>' : '';
    foreach (tsvp_abteilungen() as $id => $name) {
        $out .= '<option value="' . (int) $id . '"' . selected((int) $selected, $id, false) . '>' . esc_html($name) . '</option>';
    }
    return $out;
}

function tsvp_back($label, $args)
{
    echo '<a class="tsvp-back" href="' . tsvp_link($args) . '">← ' . esc_html($label) . '</a>';
}

function tsvp_empty($titel, $text = '')
{
    echo '<div class="tsvp-empty"><strong>' . esc_html($titel) . '</strong>' . ($text ? '<p>' . esc_html($text) . '</p>' : '') . '</div>';
}

function tsvp_notice()
{
    if (empty($_GET['tsvp_msg'])) {
        return;
    }
    $m = [
        'abgemeldet'        => ['ok', 'Du wurdest abgemeldet.'],
        'sitzung'           => ['err', 'Das Formular war zu lange offen. Bitte versuche es noch einmal.'],
        'login'             => ['err', 'Bitte melde dich an.'],
        'login_falsch'      => ['err', 'E-Mail oder Passwort ist falsch.'],
        'gesperrt'          => ['err', 'Zu viele Fehlversuche. Bitte warte 15 Minuten.'],
        'token'             => ['err', 'Der Link ist ungültig oder abgelaufen. Fordere hier einfach einen neuen an.'],
        'pw_kurz'           => ['err', 'Das Passwort muss mindestens 8 Zeichen lang sein.'],
        'pw_ungleich'       => ['err', 'Die beiden Passwörter stimmen nicht überein.'],
        'pw_falsch'         => ['err', 'Das aktuelle Passwort ist falsch.'],
        'pw_gesetzt'        => ['ok', 'Dein Passwort ist gespeichert – willkommen im Vereinsportal!'],
        'link_gesendet'     => ['ok', 'Falls die Adresse bei uns hinterlegt ist, ist ein Link unterwegs. Schau auch im Spam-Ordner nach.'],
        'gespeichert'       => ['ok', 'Gespeichert.'],
        'eingereicht'       => ['ok', 'Eingereicht – die zuständige Person wurde per E-Mail benachrichtigt.'],
        'status'            => ['ok', 'Status aktualisiert.'],
        'geloescht'         => ['ok', 'Gelöscht.'],
        'hochgeladen'       => ['ok', 'Dokument hochgeladen.'],
        'keine_rechte'      => ['err', 'Dafür fehlen dir die Rechte.'],
        'pflicht'           => ['err', 'Bitte fülle alle Pflichtfelder aus.'],
        'positionen'        => ['err', 'Bitte prüfe die Einheiten: Jede Zeile braucht ein Datum und 0,25–24 Stunden.'],
        'monat_passt'       => ['err', 'Alle Einheiten müssen im gewählten Monat liegen.'],
        'kein_satz'         => ['err', 'Für dich ist noch kein Stundensatz hinterlegt. Bitte wende dich an deine Abteilungsleitung.'],
        'doppelt'           => ['err', 'Für diesen Monat hast du bereits eine Abrechnung eingereicht.'],
        'kommentar'         => ['err', 'Bitte gib bei einer Ablehnung eine kurze Begründung an.'],
        'upload'            => ['err', 'Die Datei konnte nicht hochgeladen werden. Erlaubt sind PDF, Bilder und Office-Dateien bis 20 MB.'],
        'iban'              => ['err', 'Die IBAN ist nicht gültig. Bitte prüfe sie noch einmal.'],
        'email'             => ['err', 'Bitte gib eine gültige E-Mail-Adresse an.'],
        'email_vergeben'    => ['err', 'Diese E-Mail-Adresse hat bereits einen Zugang.'],
        'eingeladen'        => ['ok', 'Gespeichert und E-Mail mit Zugangslink verschickt.'],
        'mail_fehler'       => ['err', 'Gespeichert, aber die E-Mail konnte nicht verschickt werden. Prüfe den Mailversand von WordPress (z. B. mit einem SMTP-Plugin).'],
        'abteilung_belegt'  => ['err', 'Der Abteilung sind noch Personen zugeordnet – sie kann nicht gelöscht werden.'],
        'abteilung_fehlt'   => ['err', 'Eine Abteilungsleitung braucht eine Abteilung.'],
        'abteilung_waehlen' => ['err', 'Bitte wähle die Abteilung aus, die das Dokument sehen soll.'],
        'selbst'            => ['err', 'Du kannst dich nicht selbst deaktivieren oder deine eigene Rolle ändern.'],
    ];
    $key = sanitize_key($_GET['tsvp_msg']);
    if (!isset($m[$key])) {
        return;
    }
    echo '<div class="tsvp-notice tsvp-notice--' . $m[$key][0] . '" role="status">' . esc_html($m[$key][1]) . '</div>';
}

/* ------------------------------------------------------------------ */
/* Einstieg                                                            */
/* ------------------------------------------------------------------ */

function tsvp_render_portal()
{
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return '<p>[Vereinsportal]</p>';
    }
    ob_start();
    echo '<div class="tsvp" id="tsvp">';
    $u = tsvp_current_user();
    if (!$u) {
        tsvp_view_login();
    } else {
        tsvp_view_app($u);
    }
    echo '</div>';
    return ob_get_clean();
}

/* ------------------------------------------------------------------ */
/* Anmeldung                                                           */
/* ------------------------------------------------------------------ */

function tsvp_view_login()
{
    $ansicht = isset($_GET['ansicht']) ? sanitize_key($_GET['ansicht']) : '';
    echo '<div class="tsvp-auth"><div class="tsvp-card tsvp-auth__card">';
    echo '<div class="tsvp-auth__brand"><span class="tsvp-logo">TSV</span><div><strong>Vereinsportal</strong><span>' . esc_html(get_bloginfo('name')) . '</span></div></div>';
    tsvp_notice();

    if ($ansicht === 'passwort') {
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        $u     = tsvp_user_by_token($token);
        if (!$u) {
            echo '<div class="tsvp-notice tsvp-notice--err">Der Link ist ungültig oder abgelaufen.</div>';
            echo '<p><a class="tsvp-btn tsvp-btn--primary tsvp-btn--block" href="' . tsvp_link(['ansicht' => 'vergessen']) . '">Neuen Link anfordern</a></p>';
        } else { ?>
            <h2>Hallo <?php echo esc_html(strtok($u->name, ' ')); ?>!</h2>
            <p class="tsvp-muted">Lege jetzt dein Passwort für das Vereinsportal fest. Anmelden wirst du dich mit <strong><?php echo esc_html($u->email); ?></strong>.</p>
            <form method="post" class="tsvp-form">
                <?php tsvp_form_fields('passwort_setzen'); ?>
                <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
                <label>Neues Passwort <small>mind. 8 Zeichen</small><input type="password" name="passwort" minlength="8" required autocomplete="new-password"></label>
                <label>Passwort wiederholen<input type="password" name="passwort2" minlength="8" required autocomplete="new-password"></label>
                <button class="tsvp-btn tsvp-btn--primary tsvp-btn--block">Passwort speichern</button>
            </form>
        <?php }
    } elseif ($ansicht === 'vergessen') { ?>
        <h2>Passwort vergessen?</h2>
        <p class="tsvp-muted">Gib deine E-Mail-Adresse ein. Wir schicken dir einen Link, mit dem du ein neues Passwort festlegst.</p>
        <form method="post" class="tsvp-form">
            <?php tsvp_form_fields('passwort_vergessen'); ?>
            <label>E-Mail<input type="email" name="email" required autocomplete="username"></label>
            <button class="tsvp-btn tsvp-btn--primary tsvp-btn--block">Link anfordern</button>
        </form>
        <p class="tsvp-small"><a href="<?php echo tsvp_link(); ?>">Zurück zur Anmeldung</a></p>
    <?php } else { ?>
        <h2>Anmelden</h2>
        <form method="post" class="tsvp-form">
            <?php tsvp_form_fields('login'); ?>
            <label>E-Mail<input type="email" name="email" required autocomplete="username"></label>
            <label>Passwort<input type="password" name="passwort" required autocomplete="current-password"></label>
            <button class="tsvp-btn tsvp-btn--primary tsvp-btn--block">Anmelden</button>
        </form>
        <p class="tsvp-small"><a href="<?php echo tsvp_link(['ansicht' => 'vergessen']); ?>">Passwort vergessen?</a></p>
        <p class="tsvp-small tsvp-muted">Noch keinen Zugang? Den bekommst du von deiner Abteilungsleitung oder vom Vorstand.</p>
        <p class="tsvp-small tsvp-wplogin"><a href="<?php echo esc_url(wp_login_url(tsvp_page_url())); ?>">Als Admin mit WordPress anmelden</a></p>
    <?php }
    echo '</div></div>';
}

/* ------------------------------------------------------------------ */
/* App-Rahmen                                                          */
/* ------------------------------------------------------------------ */

function tsvp_menu($u)
{
    $m = ['start' => 'Start', 'abrechnungen' => 'Abrechnungen'];
    if (tsvp_darf_verwalten($u)) {
        $m['auszahlungen'] = 'Auszahlungen';
    }
    if (tsvp_darf_dokumente($u)) {
        $m['dokumente'] = 'Dokumente';
    }
    if (tsvp_darf_verwalten($u)) {
        $m['verwaltung'] = 'Verwaltung';
    }
    return $m;
}

function tsvp_view_app($u)
{
    $bereich = isset($_GET['bereich']) ? sanitize_key($_GET['bereich']) : 'start';
    $menu    = tsvp_menu($u);
    if (!isset($menu[$bereich]) && $bereich !== 'konto') {
        $bereich = 'start';
    }
    $rolle = tsvp_rollen()[$u->rolle] ?? '';
    if ($u->abteilung_id) {
        $rolle .= ' · ' . tsvp_abteilung_name($u->abteilung_id);
    }
    ?>
    <header class="tsvp-header">
        <a class="tsvp-brand" href="<?php echo tsvp_link(); ?>">
            <span class="tsvp-logo">TSV</span>
            <span><strong>Vereinsportal</strong><span><?php echo esc_html(get_bloginfo('name')); ?></span></span>
        </a>
        <div class="tsvp-user">
            <a class="tsvp-user__name<?php echo $bereich === 'konto' ? ' is-active' : ''; ?>" href="<?php echo tsvp_link(['bereich' => 'konto']); ?>">
                <span class="tsvp-avatar"><?php echo esc_html(mb_strtoupper(mb_substr($u->name, 0, 1))); ?></span>
                <span><?php echo esc_html($u->name); ?><small><?php echo esc_html($rolle); ?></small></span>
            </a>
            <?php if (tsvp_via_wordpress()) : ?>
                <a class="tsvp-btn tsvp-btn--ghost tsvp-btn--sm" href="<?php echo esc_url(wp_logout_url(tsvp_page_url(['tsvp_msg' => 'abgemeldet']))); ?>" title="Meldet dich auch bei WordPress ab">Abmelden</a>
            <?php else : ?>
                <a class="tsvp-btn tsvp-btn--ghost tsvp-btn--sm" href="<?php echo tsvp_link(['tsvp_logout' => 1, 'n' => tsvp_nonce('logout')]); ?>">Abmelden</a>
            <?php endif; ?>
        </div>
    </header>
    <nav class="tsvp-nav" aria-label="Bereiche">
        <?php foreach ($menu as $key => $label) : ?>
            <a href="<?php echo tsvp_link(['bereich' => $key]); ?>" class="<?php echo $key === $bereich ? 'is-active' : ''; ?>"<?php echo $key === $bereich ? ' aria-current="page"' : ''; ?>><?php echo tsvp_nav_icon($key); ?><span><?php echo esc_html($label); ?></span></a>
        <?php endforeach; ?>
    </nav>
    <main class="tsvp-main">
        <?php
        tsvp_notice();
        switch ($bereich) {
            case 'abrechnungen':
                tsvp_view_abrechnungen($u);
                break;
            case 'auszahlungen':
                tsvp_view_auszahlungen($u);
                break;
            case 'dokumente':
                tsvp_view_dokumente($u);
                break;
            case 'verwaltung':
                tsvp_view_verwaltung($u);
                break;
            case 'konto':
                tsvp_view_konto($u);
                break;
            default:
                tsvp_view_start($u);
        }
        ?>
    </main>
    <footer class="tsvp-footer">Vereinsportal · Version <?php echo esc_html(TSVP_VERSION); ?> · <a href="<?php echo esc_url(home_url('/')); ?>">Zur Homepage</a></footer>
    <?php
}

/** Kleine Symbole für die App-Navigation (auf dem Handy unten). */
function tsvp_nav_icon($key)
{
    $paths = [
        'start'        => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'abrechnungen' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'auszahlungen' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/>',
        'dokumente'    => '<path d="M4 6a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/>',
        'verwaltung'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.5 3.3-5.5 6.5-5.5s5.9 2 6.5 5.5M16 4.5a3.5 3.5 0 0 1 0 7M18 14.8c2 .7 3.2 2.6 3.5 5.2"/>',
    ];
    if (!isset($paths[$key])) {
        return '';
    }
    return '<svg class="tsvp-nav__icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$key] . '</svg>';
}

/* ------------------------------------------------------------------ */
/* Start                                                               */
/* ------------------------------------------------------------------ */

function tsvp_view_start($u)
{
    global $wpdb;
    $abr = tsvp_t('abrechnungen');
    $ant = tsvp_t('antraege');
    $ben = tsvp_t('benutzer');

    $meine_offen = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $abr WHERE benutzer_id = %d AND status IN ('eingereicht','freigegeben')",
        $u->id
    ));
    $meine_summe = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(betrag),0) FROM $abr WHERE benutzer_id = %d AND status = 'ausgezahlt' AND LEFT(monat,4) = %s",
        $u->id,
        current_time('Y')
    ));

    // Zu prüfen
    $pruefen = [];
    if (tsvp_is_vorstand($u)) {
        $pruefen = $wpdb->get_results($wpdb->prepare(
            "SELECT x.*, b.name AS person FROM $abr x LEFT JOIN $ben b ON b.id = x.benutzer_id
             WHERE x.status = 'eingereicht' AND x.benutzer_id <> %d ORDER BY x.created_at ASC LIMIT 20",
            $u->id
        ));
    } elseif (tsvp_is_al($u) && $u->abteilung_id) {
        $pruefen = $wpdb->get_results($wpdb->prepare(
            "SELECT x.*, b.name AS person FROM $abr x LEFT JOIN $ben b ON b.id = x.benutzer_id
             WHERE x.status = 'eingereicht' AND x.abteilung_id = %d AND x.benutzer_id <> %d ORDER BY x.created_at ASC LIMIT 20",
            $u->abteilung_id,
            $u->id
        ));
    }
    $antraege_pruefen = [];
    $auszahlen_n = 0;
    $auszahlen_summe = 0.0;
    if (tsvp_is_vorstand($u)) {
        $antraege_pruefen = $wpdb->get_results($wpdb->prepare(
            "SELECT x.*, b.name AS person FROM $ant x LEFT JOIN $ben b ON b.id = x.benutzer_id
             WHERE x.status = 'eingereicht' AND x.benutzer_id <> %d ORDER BY x.created_at ASC LIMIT 20",
            $u->id
        ));
        $a = $wpdb->get_row("SELECT COUNT(*) n, COALESCE(SUM(betrag),0) s FROM $abr WHERE status = 'freigegeben'");
        $b = $wpdb->get_row("SELECT COUNT(*) n, COALESCE(SUM(betrag),0) s FROM $ant WHERE status = 'genehmigt'");
        $auszahlen_n     = (int) $a->n + (int) $b->n;
        $auszahlen_summe = (float) $a->s + (float) $b->s;
    }

    $doks = tsvp_darf_dokumente($u) ? $wpdb->get_results(
        'SELECT x.* FROM ' . tsvp_t('dokumente') . ' x WHERE ' . tsvp_dokument_scope_sql($u) . ' ORDER BY x.created_at DESC LIMIT 5'
    ) : [];
    ?>
    <div class="tsvp-pagehead">
        <div>
            <h2>Hallo <?php echo esc_html(strtok($u->name, ' ')); ?> 👋</h2>
            <p class="tsvp-muted">Schön, dass du da bist. Hier siehst du, was gerade ansteht.</p>
        </div>
        <div class="tsvp-pagehead__actions">
            <a class="tsvp-btn tsvp-btn--primary" href="<?php echo tsvp_link(['bereich' => 'abrechnungen', 'neu' => 1]); ?>">+ Abrechnung</a>
            <?php if (tsvp_darf_verwalten($u)) : ?>
                <a class="tsvp-btn" href="<?php echo tsvp_link(['bereich' => 'auszahlungen', 'neu' => 1]); ?>">+ Auszahlung</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="tsvp-stats">
        <a class="tsvp-stat" href="<?php echo tsvp_link(['bereich' => 'abrechnungen', 'filter' => 'meine']); ?>">
            <span>Meine offenen Abrechnungen</span><strong><?php echo (int) $meine_offen; ?></strong>
        </a>
        <div class="tsvp-stat">
            <span>Mir ausgezahlt <?php echo esc_html(current_time('Y')); ?></span><strong><?php echo esc_html(tsvp_money($meine_summe)); ?></strong>
        </div>
        <?php if (tsvp_darf_verwalten($u)) : ?>
            <div class="tsvp-stat<?php echo (count($pruefen) + count($antraege_pruefen)) ? ' tsvp-stat--hot' : ''; ?>">
                <span>Warten auf deine Prüfung</span><strong><?php echo count($pruefen) + count($antraege_pruefen); ?></strong>
            </div>
        <?php endif; ?>
        <?php if (tsvp_is_vorstand($u)) : ?>
            <div class="tsvp-stat">
                <span>Bereit zur Auszahlung (<?php echo (int) $auszahlen_n; ?>)</span><strong><?php echo esc_html(tsvp_money($auszahlen_summe)); ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <?php if (tsvp_darf_verwalten($u)) : ?>
        <section class="tsvp-section">
            <h3>Zu prüfen</h3>
            <?php if (!$pruefen && !$antraege_pruefen) : ?>
                <?php tsvp_empty('Alles erledigt', 'Gerade wartet nichts auf deine Freigabe.'); ?>
            <?php else : ?>
                <ul class="tsvp-list">
                    <?php foreach ($pruefen as $r) : ?>
                        <li><a href="<?php echo tsvp_link(['bereich' => 'abrechnungen', 'id' => $r->id]); ?>">
                            <span class="tsvp-tag">Abrechnung</span>
                            <span class="tsvp-list__main"><strong><?php echo esc_html($r->person); ?></strong>
                                <small><?php echo esc_html(tsvp_monat($r->monat) . ' · ' . tsvp_abteilung_name($r->abteilung_id) . ' · ' . tsvp_num($r->stunden) . ' Std.'); ?></small></span>
                            <span class="tsvp-list__end"><?php echo esc_html(tsvp_money($r->betrag)); ?></span>
                        </a></li>
                    <?php endforeach; ?>
                    <?php foreach ($antraege_pruefen as $r) : ?>
                        <li><a href="<?php echo tsvp_link(['bereich' => 'auszahlungen', 'id' => $r->id]); ?>">
                            <span class="tsvp-tag tsvp-tag--alt">Auszahlung</span>
                            <span class="tsvp-list__main"><strong><?php echo esc_html($r->zweck); ?></strong>
                                <small><?php echo esc_html($r->person . ' · ' . tsvp_abteilung_name($r->abteilung_id)); ?></small></span>
                            <span class="tsvp-list__end"><?php echo esc_html(tsvp_money($r->betrag)); ?></span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (tsvp_darf_dokumente($u)) : ?>
    <section class="tsvp-section">
        <div class="tsvp-section__head"><h3>Neue Protokolle</h3><a href="<?php echo tsvp_link(['bereich' => 'dokumente']); ?>">Alle anzeigen</a></div>
        <?php if (!$doks) : ?>
            <?php tsvp_empty('Noch keine Protokolle', 'Protokolle der Jahreshauptversammlung und der Vereinsratssitzungen erscheinen hier, sobald sie hochgeladen sind.'); ?>
        <?php else : ?>
            <ul class="tsvp-list"><?php foreach ($doks as $d) {
                tsvp_dokument_item($u, $d, false);
            } ?></ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php
}

/* ------------------------------------------------------------------ */
/* Abrechnungen                                                        */
/* ------------------------------------------------------------------ */

function tsvp_view_abrechnungen($u)
{
    if (!empty($_GET['neu'])) {
        tsvp_view_abrechnung_form($u);
        return;
    }
    if (!empty($_GET['id'])) {
        tsvp_view_abrechnung_detail($u, absint($_GET['id']));
        return;
    }

    global $wpdb;
    $filter = isset($_GET['filter']) ? sanitize_key($_GET['filter']) : '';
    $labels = tsvp_status_abrechnung();
    $where  = tsvp_scope_sql($u);
    if (isset($labels[$filter])) {
        $where .= $wpdb->prepare(' AND x.status = %s', $filter);
    } elseif ($filter === 'meine') {
        $where .= $wpdb->prepare(' AND x.benutzer_id = %d', $u->id);
    }
    $rows = $wpdb->get_results(
        'SELECT x.*, b.name AS person FROM ' . tsvp_t('abrechnungen') . ' x
         LEFT JOIN ' . tsvp_t('benutzer') . " b ON b.id = x.benutzer_id
         WHERE $where ORDER BY x.monat DESC, x.created_at DESC LIMIT 300"
    );
    $chips = ['' => 'Alle'];
    if (tsvp_darf_verwalten($u)) {
        $chips['meine'] = 'Meine';
    }
    $chips += $labels;
    ?>
    <div class="tsvp-pagehead">
        <div><h2>Abrechnungen</h2>
            <p class="tsvp-muted"><?php echo tsvp_darf_verwalten($u) ? 'Übungsleiterabrechnungen einreichen, prüfen und freigeben.' : 'Reiche deine geleisteten Stunden einmal im Monat ein.'; ?></p></div>
        <div class="tsvp-pagehead__actions"><a class="tsvp-btn tsvp-btn--primary" href="<?php echo tsvp_link(['bereich' => 'abrechnungen', 'neu' => 1]); ?>">+ Neue Abrechnung</a></div>
    </div>
    <div class="tsvp-chips">
        <?php foreach ($chips as $k => $l) : ?>
            <a class="<?php echo $k === $filter ? 'is-active' : ''; ?>" href="<?php echo tsvp_link(array_filter(['bereich' => 'abrechnungen', 'filter' => $k])); ?>"><?php echo esc_html($l); ?></a>
        <?php endforeach; ?>
    </div>
    <?php
    if (!$rows) {
        tsvp_empty('Keine Abrechnungen', 'Hier ist noch nichts – leg mit „Neue Abrechnung“ los.');
        return;
    }
    $mit_person = tsvp_darf_verwalten($u);
    ?>
    <div class="tsvp-tablewrap"><table class="tsvp-table">
        <thead><tr><th>Monat</th><?php if ($mit_person) : ?><th>Person</th><?php endif; ?><th class="tsvp-hide-sm">Abteilung</th><th class="num tsvp-hide-sm">Stunden</th><th class="num">Betrag</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r) : $link = tsvp_link(['bereich' => 'abrechnungen', 'id' => $r->id]); ?>
            <tr data-href="<?php echo $link; ?>">
                <td><a href="<?php echo $link; ?>"><?php echo esc_html(tsvp_monat($r->monat)); ?></a></td>
                <?php if ($mit_person) : ?><td><?php echo esc_html($r->person); ?></td><?php endif; ?>
                <td class="tsvp-hide-sm"><?php echo esc_html(tsvp_abteilung_name($r->abteilung_id)); ?></td>
                <td class="num tsvp-hide-sm"><?php echo esc_html(tsvp_num($r->stunden)); ?></td>
                <td class="num"><?php echo esc_html(tsvp_money($r->betrag)); ?></td>
                <td><?php echo tsvp_badge($r->status, $labels); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php
}

function tsvp_view_abrechnung_form($u)
{
    $satz  = (float) $u->stundensatz;
    $monat = current_time('Y-m');
    tsvp_back('Abrechnungen', ['bereich' => 'abrechnungen']);
    ?>
    <div class="tsvp-pagehead"><div><h2>Neue Abrechnung</h2><p class="tsvp-muted">Trag jede Trainingseinheit als eigene Zeile ein.</p></div></div>
    <?php if ($satz <= 0) : ?>
        <div class="tsvp-notice tsvp-notice--warn">Für dich ist noch kein Stundensatz hinterlegt. Du kannst die Abrechnung erst einreichen, wenn deine Abteilungsleitung oder der Vorstand ihn eingetragen hat.</div>
    <?php endif; ?>
    <form method="post" class="tsvp-card tsvp-form" data-tsvp-abrechnung data-satz="<?php echo esc_attr($satz); ?>">
        <?php tsvp_form_fields('abrechnung_speichern'); ?>
        <div class="tsvp-grid">
            <label>Monat<input type="month" name="monat" value="<?php echo esc_attr($monat); ?>" max="<?php echo esc_attr($monat); ?>" required></label>
            <?php if ($u->abteilung_id) : ?>
                <div class="tsvp-field"><span>Abteilung</span><strong><?php echo esc_html(tsvp_abteilung_name($u->abteilung_id)); ?></strong></div>
            <?php elseif (tsvp_abteilungen()) : ?>
                <label>Abteilung<select name="abteilung_id"><?php echo tsvp_abteilung_options(0); ?></select></label>
            <?php endif; ?>
            <div class="tsvp-field"><span>Dein Stundensatz</span><strong><?php echo esc_html(tsvp_money($satz)); ?></strong></div>
        </div>

        <div class="tsvp-rows__head"><span>Datum</span><span>Stunden</span><span>Gruppe / Tätigkeit</span><span></span></div>
        <div class="tsvp-rows" data-rows>
            <div class="tsvp-row" data-row>
                <input type="date" name="datum[]" required aria-label="Datum">
                <input type="number" name="stunden[]" step="0.25" min="0.25" max="24" inputmode="decimal" placeholder="1,5" required aria-label="Stunden">
                <input type="text" name="taetigkeit[]" maxlength="120" placeholder="z. B. Kinderturnen" aria-label="Gruppe oder Tätigkeit">
                <button type="button" class="tsvp-iconbtn" data-remove aria-label="Zeile entfernen"><svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg></button>
            </div>
        </div>
        <button type="button" class="tsvp-btn tsvp-btn--ghost" data-add>+ Einheit hinzufügen</button>

        <div class="tsvp-total">
            <span><strong data-sum-h>0</strong> Stunden × <?php echo esc_html(tsvp_money($satz)); ?></span>
            <strong class="tsvp-total__eur" data-sum-eur><?php echo esc_html(tsvp_money(0)); ?></strong>
        </div>

        <label>Bemerkung <small>optional</small><textarea name="bemerkung" rows="3" placeholder="z. B. Vertretung für …"></textarea></label>
        <label class="tsvp-check"><input type="checkbox" required> Ich bestätige, dass meine Angaben vollständig und richtig sind.</label>
        <div class="tsvp-actions"><button class="tsvp-btn tsvp-btn--primary"<?php disabled($satz <= 0); ?>>Abrechnung einreichen</button></div>
    </form>
    <?php
}

function tsvp_view_abrechnung_detail($u, $id)
{
    $row = tsvp_get_abrechnung($id);
    tsvp_back('Abrechnungen', ['bereich' => 'abrechnungen']);
    if (!tsvp_kann_sehen($u, $row)) {
        tsvp_empty('Nicht gefunden', 'Diese Abrechnung gibt es nicht oder du darfst sie nicht sehen.');
        return;
    }
    $labels = tsvp_status_abrechnung();
    $pos    = json_decode($row->positionen, true) ?: [];
    $bearb  = $row->bearbeitet_von ? tsvp_get_benutzer($row->bearbeitet_von) : null;
    ?>
    <div class="tsvp-pagehead">
        <div><h2>Abrechnung <?php echo esc_html(tsvp_monat($row->monat)); ?></h2>
            <p class="tsvp-muted"><?php echo esc_html($row->person . ' · ' . tsvp_abteilung_name($row->abteilung_id)); ?></p></div>
        <div class="tsvp-pagehead__actions"><?php echo tsvp_badge($row->status, $labels); ?>
            <button type="button" class="tsvp-btn tsvp-btn--ghost tsvp-btn--sm" data-print>Drucken</button></div>
    </div>

    <div class="tsvp-card">
        <dl class="tsvp-meta">
            <div><dt>Person</dt><dd><?php echo esc_html($row->person); ?></dd></div>
            <div><dt>Abteilung</dt><dd><?php echo esc_html(tsvp_abteilung_name($row->abteilung_id)); ?></dd></div>
            <div><dt>Eingereicht</dt><dd><?php echo esc_html(tsvp_datum_utc($row->created_at, 'd.m.Y, H:i')); ?> Uhr</dd></div>
            <div><dt>Stundensatz</dt><dd><?php echo esc_html(tsvp_money($row->stundensatz)); ?></dd></div>
            <?php if (tsvp_is_vorstand($u) && $row->person_iban) : ?>
                <div><dt>IBAN</dt><dd><?php echo esc_html(tsvp_iban_format($row->person_iban)); ?></dd></div>
            <?php endif; ?>
        </dl>

        <div class="tsvp-tablewrap"><table class="tsvp-table tsvp-table--plain">
            <thead><tr><th>Datum</th><th>Gruppe / Tätigkeit</th><th class="num">Stunden</th></tr></thead>
            <tbody>
            <?php foreach ($pos as $p) : ?>
                <tr><td><?php echo esc_html(tsvp_datum($p['datum'] ?? '')); ?></td><td><?php echo esc_html($p['taetigkeit'] ?? ''); ?></td><td class="num"><?php echo esc_html(tsvp_num($p['stunden'] ?? 0)); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="2">Summe</td><td class="num"><?php echo esc_html(tsvp_num($row->stunden)); ?> Std.</td></tr>
                <tr class="tsvp-table__total"><td colspan="2">Betrag</td><td class="num"><?php echo esc_html(tsvp_money($row->betrag)); ?></td></tr>
            </tfoot>
        </table></div>

        <?php if ($row->bemerkung !== '') : ?>
            <div class="tsvp-note"><strong>Bemerkung</strong><p><?php echo nl2br(esc_html($row->bemerkung)); ?></p></div>
        <?php endif; ?>
        <?php if ($row->kommentar !== '' || $bearb) : ?>
            <div class="tsvp-note tsvp-note--status"><strong><?php echo esc_html($labels[$row->status] ?? ''); ?><?php echo $bearb ? ' von ' . esc_html($bearb->name) . ' am ' . esc_html(tsvp_datum_utc($row->bearbeitet_am)) : ''; ?></strong>
                <?php if ($row->kommentar !== '') : ?><p><?php echo nl2br(esc_html($row->kommentar)); ?></p><?php endif; ?></div>
        <?php endif; ?>
    </div>

    <?php tsvp_status_box($u, $row, 'abrechnung'); ?>
    <?php
}

/** Entscheidungs-Box für Abrechnungen und Anträge. */
function tsvp_status_box($u, $row, $typ)
{
    if ($typ === 'abrechnung') {
        $ok_status = 'freigegeben';
        $ok_label  = 'Freigeben';
        $kann_ok   = tsvp_kann_abrechnung_status($u, $row, 'freigegeben');
        $kann_aus  = tsvp_kann_abrechnung_status($u, $row, 'ausgezahlt');
    } else {
        $ok_status = 'genehmigt';
        $ok_label  = 'Genehmigen';
        $kann_ok   = tsvp_kann_antrag_status($u, $row, 'genehmigt');
        $kann_aus  = tsvp_kann_antrag_status($u, $row, 'ausgezahlt');
    }
    $kann_del = tsvp_kann_loeschen($u, $row);
    if (!$kann_ok && !$kann_aus && !$kann_del) {
        return;
    }
    echo '<div class="tsvp-card tsvp-decide">';
    if ($kann_ok) { ?>
        <form method="post" class="tsvp-form">
            <?php tsvp_form_fields($typ . '_status'); ?>
            <input type="hidden" name="id" value="<?php echo (int) $row->id; ?>">
            <h3>Prüfen</h3>
            <label>Kommentar <small>bei Ablehnung Pflicht</small><textarea name="kommentar" rows="2"></textarea></label>
            <div class="tsvp-actions">
                <button class="tsvp-btn tsvp-btn--primary" name="status" value="<?php echo esc_attr($ok_status); ?>"><?php echo esc_html($ok_label); ?></button>
                <button class="tsvp-btn tsvp-btn--danger" name="status" value="abgelehnt">Ablehnen</button>
            </div>
        </form>
    <?php }
    if ($kann_aus) { ?>
        <form method="post" class="tsvp-form">
            <?php tsvp_form_fields($typ . '_status'); ?>
            <input type="hidden" name="id" value="<?php echo (int) $row->id; ?>">
            <h3>Auszahlung</h3>
            <p class="tsvp-muted">Markiere den Vorgang als erledigt, sobald das Geld überwiesen ist.</p>
            <div class="tsvp-actions"><button class="tsvp-btn tsvp-btn--primary" name="status" value="ausgezahlt">Als ausgezahlt markieren</button></div>
        </form>
    <?php }
    if ($kann_del) { ?>
        <form method="post" class="tsvp-form tsvp-form--inline">
            <?php tsvp_form_fields($typ === 'abrechnung' ? 'abrechnung_loeschen' : 'antrag_loeschen'); ?>
            <input type="hidden" name="id" value="<?php echo (int) $row->id; ?>">
            <button class="tsvp-btn tsvp-btn--ghost tsvp-btn--sm tsvp-btn--dangertext" data-confirm="Wirklich löschen? Das lässt sich nicht rückgängig machen.">
                <?php echo (int) $row->benutzer_id === (int) $u->id && $row->status === 'eingereicht' ? 'Zurückziehen & löschen' : 'Löschen'; ?></button>
        </form>
    <?php }
    echo '</div>';
}

/* ------------------------------------------------------------------ */
/* Auszahlungsanträge                                                  */
/* ------------------------------------------------------------------ */

function tsvp_view_auszahlungen($u)
{
    if (!empty($_GET['neu'])) {
        tsvp_view_antrag_form($u);
        return;
    }
    if (!empty($_GET['id'])) {
        tsvp_view_antrag_detail($u, absint($_GET['id']));
        return;
    }

    global $wpdb;
    $filter = isset($_GET['filter']) ? sanitize_key($_GET['filter']) : '';
    $labels = tsvp_status_antrag();
    $where  = tsvp_scope_sql($u);
    if (isset($labels[$filter])) {
        $where .= $wpdb->prepare(' AND x.status = %s', $filter);
    }
    $rows = $wpdb->get_results(
        'SELECT x.*, b.name AS person FROM ' . tsvp_t('antraege') . ' x
         LEFT JOIN ' . tsvp_t('benutzer') . " b ON b.id = x.benutzer_id
         WHERE $where ORDER BY x.created_at DESC LIMIT 300"
    );
    ?>
    <div class="tsvp-pagehead">
        <div><h2>Auszahlungen</h2><p class="tsvp-muted">Anträge für Anschaffungen, Fahrtkosten, Startgelder & Co. – der Vorstand entscheidet.</p></div>
        <div class="tsvp-pagehead__actions"><a class="tsvp-btn tsvp-btn--primary" href="<?php echo tsvp_link(['bereich' => 'auszahlungen', 'neu' => 1]); ?>">+ Neuer Antrag</a></div>
    </div>
    <div class="tsvp-chips">
        <?php foreach (['' => 'Alle'] + $labels as $k => $l) : ?>
            <a class="<?php echo $k === $filter ? 'is-active' : ''; ?>" href="<?php echo tsvp_link(array_filter(['bereich' => 'auszahlungen', 'filter' => $k])); ?>"><?php echo esc_html($l); ?></a>
        <?php endforeach; ?>
    </div>
    <?php
    if (!$rows) {
        tsvp_empty('Keine Anträge', 'Hier erscheinen Auszahlungsanträge, sobald jemand einen stellt.');
        return;
    }
    ?>
    <div class="tsvp-tablewrap"><table class="tsvp-table">
        <thead><tr><th class="tsvp-hide-sm">Datum</th><th>Zweck</th><th class="tsvp-hide-sm">Abteilung</th><th class="tsvp-hide-sm">Von</th><th class="num">Betrag</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r) : $link = tsvp_link(['bereich' => 'auszahlungen', 'id' => $r->id]); ?>
            <tr data-href="<?php echo $link; ?>">
                <td class="tsvp-hide-sm"><?php echo esc_html(tsvp_datum_utc($r->created_at)); ?></td>
                <td><a href="<?php echo $link; ?>"><?php echo esc_html($r->zweck); ?></a></td>
                <td class="tsvp-hide-sm"><?php echo esc_html(tsvp_abteilung_name($r->abteilung_id)); ?></td>
                <td class="tsvp-hide-sm"><?php echo esc_html($r->person); ?></td>
                <td class="num"><?php echo esc_html(tsvp_money($r->betrag)); ?></td>
                <td><?php echo tsvp_badge($r->status, $labels); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php
}

function tsvp_view_antrag_form($u)
{
    tsvp_back('Auszahlungen', ['bereich' => 'auszahlungen']);
    ?>
    <div class="tsvp-pagehead"><div><h2>Neuer Auszahlungsantrag</h2><p class="tsvp-muted">Der Vorstand wird automatisch per E-Mail informiert.</p></div></div>
    <form method="post" enctype="multipart/form-data" class="tsvp-card tsvp-form">
        <?php tsvp_form_fields('antrag_speichern'); ?>
        <label>Wofür? <input type="text" name="zweck" maxlength="255" required placeholder="z. B. Neue Bälle für die E-Jugend"></label>
        <div class="tsvp-grid">
            <label>Betrag (€)<input type="number" name="betrag" step="0.01" min="0.01" inputmode="decimal" required placeholder="0,00"></label>
            <?php if (tsvp_is_al($u)) : ?>
                <div class="tsvp-field"><span>Abteilung</span><strong><?php echo esc_html(tsvp_abteilung_name($u->abteilung_id)); ?></strong></div>
            <?php else : ?>
                <label>Abteilung<select name="abteilung_id"><?php echo tsvp_abteilung_options(0); ?></select></label>
            <?php endif; ?>
        </div>
        <label>Beschreibung <small>optional</small><textarea name="beschreibung" rows="3" placeholder="Hintergrund, Händler, Anzahl …"></textarea></label>
        <fieldset class="tsvp-fieldset">
            <legend>Auszahlung an</legend>
            <div class="tsvp-grid">
                <label>Empfänger*in<input type="text" name="empfaenger" maxlength="190" required value="<?php echo esc_attr($u->name); ?>"></label>
                <label>IBAN <small>optional</small><input type="text" name="iban" maxlength="42" autocomplete="off" value="<?php echo esc_attr(tsvp_iban_format($u->iban)); ?>" placeholder="DE00 0000 0000 0000 0000 00"></label>
            </div>
        </fieldset>
        <label>Beleg / Rechnung <small>PDF oder Foto, max. 20 MB</small><input type="file" name="beleg" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.odt,.ods"></label>
        <div class="tsvp-actions"><button class="tsvp-btn tsvp-btn--primary">Antrag stellen</button></div>
    </form>
    <?php
}

function tsvp_view_antrag_detail($u, $id)
{
    $row = tsvp_get_antrag($id);
    tsvp_back('Auszahlungen', ['bereich' => 'auszahlungen']);
    if (!tsvp_kann_sehen($u, $row)) {
        tsvp_empty('Nicht gefunden', 'Diesen Antrag gibt es nicht oder du darfst ihn nicht sehen.');
        return;
    }
    $labels = tsvp_status_antrag();
    $bearb  = $row->bearbeitet_von ? tsvp_get_benutzer($row->bearbeitet_von) : null;
    ?>
    <div class="tsvp-pagehead">
        <div><h2><?php echo esc_html($row->zweck); ?></h2><p class="tsvp-muted"><?php echo esc_html('Antrag von ' . $row->person . ' · ' . tsvp_abteilung_name($row->abteilung_id)); ?></p></div>
        <div class="tsvp-pagehead__actions"><?php echo tsvp_badge($row->status, $labels); ?>
            <button type="button" class="tsvp-btn tsvp-btn--ghost tsvp-btn--sm" data-print>Drucken</button></div>
    </div>
    <div class="tsvp-card">
        <div class="tsvp-amount"><?php echo esc_html(tsvp_money($row->betrag)); ?></div>
        <dl class="tsvp-meta">
            <div><dt>Beantragt von</dt><dd><?php echo esc_html($row->person); ?></dd></div>
            <div><dt>Abteilung</dt><dd><?php echo esc_html(tsvp_abteilung_name($row->abteilung_id)); ?></dd></div>
            <div><dt>Gestellt am</dt><dd><?php echo esc_html(tsvp_datum_utc($row->created_at, 'd.m.Y, H:i')); ?> Uhr</dd></div>
            <div><dt>Empfänger*in</dt><dd><?php echo esc_html($row->empfaenger); ?></dd></div>
            <?php if ($row->iban) : ?><div><dt>IBAN</dt><dd><?php echo esc_html(tsvp_iban_format($row->iban)); ?></dd></div><?php endif; ?>
            <?php if ($row->beleg_datei) : ?>
                <div><dt>Beleg</dt><dd><a href="<?php echo esc_url(add_query_arg(['tsvp_datei' => 'beleg', 'id' => $row->id], tsvp_page_url())); ?>" target="_blank" rel="noopener"><?php echo esc_html($row->beleg_name); ?></a></dd></div>
            <?php endif; ?>
        </dl>
        <?php if ($row->beschreibung !== '') : ?>
            <div class="tsvp-note"><strong>Beschreibung</strong><p><?php echo nl2br(esc_html($row->beschreibung)); ?></p></div>
        <?php endif; ?>
        <?php if ($row->kommentar !== '' || $bearb) : ?>
            <div class="tsvp-note tsvp-note--status"><strong><?php echo esc_html($labels[$row->status] ?? ''); ?><?php echo $bearb ? ' von ' . esc_html($bearb->name) . ' am ' . esc_html(tsvp_datum_utc($row->bearbeitet_am)) : ''; ?></strong>
                <?php if ($row->kommentar !== '') : ?><p><?php echo nl2br(esc_html($row->kommentar)); ?></p><?php endif; ?></div>
        <?php endif; ?>
    </div>
    <?php
    tsvp_status_box($u, $row, 'antrag');
}

/* ------------------------------------------------------------------ */
/* Dokumente                                                           */
/* ------------------------------------------------------------------ */

function tsvp_dokument_item($u, $d, $mit_loeschen = true)
{
    $url  = esc_url(add_query_arg(['tsvp_datei' => 'dok', 'id' => $d->id], tsvp_page_url()));
    $kat  = tsvp_kategorien()[$d->kategorie] ?? '';
    $meta = [$kat, tsvp_datum_utc($d->created_at), size_format((int) $d->groesse)];
    ?>
    <li class="tsvp-doc">
        <a href="<?php echo $url; ?>" target="_blank" rel="noopener">
            <span class="tsvp-filetype"><?php echo esc_html(tsvp_datei_typ($d->dateiname)); ?></span>
            <span class="tsvp-list__main"><strong><?php echo esc_html($d->titel); ?></strong>
                <?php if ($d->beschreibung !== '') : ?><span class="tsvp-doc__desc"><?php echo esc_html($d->beschreibung); ?></span><?php endif; ?>
                <small><?php echo esc_html(implode(' · ', array_filter($meta))); ?></small></span>
        </a>
        <?php if ($mit_loeschen && (tsvp_is_vorstand($u) || (int) $d->benutzer_id === (int) $u->id)) : ?>
            <form method="post" class="tsvp-doc__del">
                <?php tsvp_form_fields('dokument_loeschen'); ?>
                <input type="hidden" name="id" value="<?php echo (int) $d->id; ?>">
                <button class="tsvp-iconbtn" aria-label="Dokument löschen" data-confirm="„<?php echo esc_attr($d->titel); ?>“ wirklich löschen?"><svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg></button>
            </form>
        <?php endif; ?>
    </li>
    <?php
}

function tsvp_view_dokumente($u)
{
    global $wpdb;
    $kats  = tsvp_kategorien();
    $kat   = isset($_GET['kategorie']) ? sanitize_key($_GET['kategorie']) : '';
    $suche = isset($_GET['suche']) ? sanitize_text_field(wp_unslash($_GET['suche'])) : '';
    $where = tsvp_dokument_scope_sql($u);
    if (isset($kats[$kat])) {
        $where .= $wpdb->prepare(' AND x.kategorie = %s', $kat);
    }
    if ($suche !== '') {
        $like   = '%' . $wpdb->esc_like($suche) . '%';
        $where .= $wpdb->prepare(' AND (x.titel LIKE %s OR x.beschreibung LIKE %s)', $like, $like);
    }
    $docs = $wpdb->get_results('SELECT x.* FROM ' . tsvp_t('dokumente') . " x WHERE $where ORDER BY x.created_at DESC LIMIT 500");
    ?>
    <div class="tsvp-pagehead">
        <div><h2>Dokumente</h2><p class="tsvp-muted">Protokolle der Jahreshauptversammlung und der Vereinsratssitzungen – nur für Vorstand und Abteilungsleitungen.</p></div>
    </div>

    <?php if (tsvp_darf_dokumente($u)) : ?>
        <details class="tsvp-card tsvp-upload"<?php echo !$docs && !$kat && $suche === '' ? ' open' : ''; ?>>
            <summary>+ Protokoll hochladen</summary>
            <form method="post" enctype="multipart/form-data" class="tsvp-form">
                <?php tsvp_form_fields('dokument_hochladen'); ?>
                <div class="tsvp-grid">
                    <label>Titel<input type="text" name="titel" maxlength="190" required placeholder="z. B. Protokoll Jahreshauptversammlung 2026"></label>
                    <label>Kategorie<select name="kategorie">
                        <?php foreach ($kats as $k => $l) : ?><option value="<?php echo esc_attr($k); ?>"<?php selected($k, $kat ?: 'jahreshauptversammlung'); ?>><?php echo esc_html($l); ?></option><?php endforeach; ?>
                    </select></label>
                </div>
                <label>Kurzbeschreibung <small>optional</small><input type="text" name="beschreibung" maxlength="300"></label>
                <p class="tsvp-small tsvp-muted">Sichtbar für Vorstand und Abteilungsleitungen.</p>
                <label>Datei <small>PDF, Bilder, Office – max. 20 MB</small><input type="file" name="datei" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.odt,.ods,.txt"></label>
                <div class="tsvp-actions"><button class="tsvp-btn tsvp-btn--primary">Hochladen</button></div>
            </form>
        </details>
    <?php endif; ?>

    <div class="tsvp-toolbar">
        <div class="tsvp-chips">
            <a class="<?php echo $kat === '' ? 'is-active' : ''; ?>" href="<?php echo tsvp_link(['bereich' => 'dokumente']); ?>">Alle</a>
            <?php foreach ($kats as $k => $l) : ?>
                <a class="<?php echo $k === $kat ? 'is-active' : ''; ?>" href="<?php echo tsvp_link(['bereich' => 'dokumente', 'kategorie' => $k]); ?>"><?php echo esc_html($l); ?></a>
            <?php endforeach; ?>
        </div>
        <form method="get" action="<?php echo esc_url(tsvp_page_url()); ?>" class="tsvp-search">
            <?php if (!get_option('permalink_structure')) : ?><input type="hidden" name="page_id" value="<?php echo (int) get_option('tsvp_page_id'); ?>"><?php endif; ?>
            <input type="hidden" name="bereich" value="dokumente">
            <?php if ($kat) : ?><input type="hidden" name="kategorie" value="<?php echo esc_attr($kat); ?>"><?php endif; ?>
            <input type="search" name="suche" value="<?php echo esc_attr($suche); ?>" placeholder="Suchen …" aria-label="Dokumente durchsuchen">
        </form>
    </div>

    <?php
    if (!$docs) {
        tsvp_empty($suche !== '' ? 'Nichts gefunden' : 'Noch keine Protokolle', $suche !== '' ? 'Versuch es mit einem anderen Suchbegriff.' : '');
        return;
    }
    echo '<ul class="tsvp-list">';
    foreach ($docs as $d) {
        tsvp_dokument_item($u, $d);
    }
    echo '</ul>';
}

/* ------------------------------------------------------------------ */
/* Verwaltung                                                          */
/* ------------------------------------------------------------------ */

function tsvp_view_verwaltung($u)
{
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'personen';
    if ($tab === 'abteilungen' && !tsvp_is_vorstand($u)) {
        $tab = 'personen';
    }
    if ($tab === 'personen' && isset($_GET['person'])) {
        tsvp_view_person_form($u, $_GET['person'] === 'neu' ? 0 : absint($_GET['person']));
        return;
    }
    ?>
    <div class="tsvp-pagehead">
        <div><h2>Verwaltung</h2><p class="tsvp-muted"><?php echo tsvp_is_vorstand($u) ? 'Zugänge, Rollen und Abteilungen verwalten.' : 'Übungsleitungen deiner Abteilung verwalten.'; ?></p></div>
        <?php if ($tab === 'personen') : ?>
            <div class="tsvp-pagehead__actions"><a class="tsvp-btn tsvp-btn--primary" href="<?php echo tsvp_link(['bereich' => 'verwaltung', 'person' => 'neu']); ?>">+ Person anlegen</a></div>
        <?php endif; ?>
    </div>
    <?php if (tsvp_is_vorstand($u)) : ?>
        <div class="tsvp-chips">
            <a class="<?php echo $tab === 'personen' ? 'is-active' : ''; ?>" href="<?php echo tsvp_link(['bereich' => 'verwaltung']); ?>">Personen</a>
            <a class="<?php echo $tab === 'abteilungen' ? 'is-active' : ''; ?>" href="<?php echo tsvp_link(['bereich' => 'verwaltung', 'tab' => 'abteilungen']); ?>">Abteilungen</a>
        </div>
    <?php endif;

    if ($tab === 'abteilungen') {
        tsvp_view_abteilungen();
    } else {
        tsvp_view_personen($u);
    }
}

function tsvp_view_personen($u)
{
    global $wpdb;
    if (tsvp_is_vorstand($u) && !tsvp_abteilungen()) {
        echo '<div class="tsvp-notice tsvp-notice--warn">Tipp: Lege zuerst unter <a href="' . tsvp_link(['bereich' => 'verwaltung', 'tab' => 'abteilungen']) . '">Abteilungen</a> eure Abteilungen an – dann kannst du Personen zuordnen.</div>';
    }
    $tab = tsvp_t('benutzer');
    if (tsvp_is_vorstand($u)) {
        $rows = $wpdb->get_results("SELECT * FROM $tab ORDER BY aktiv DESC, FIELD(rolle,'vorstand','abteilungsleiter','uebungsleiter'), name");
    } else {
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tab WHERE abteilung_id = %d ORDER BY aktiv DESC, FIELD(rolle,'vorstand','abteilungsleiter','uebungsleiter'), name", (int) $u->abteilung_id));
    }
    if (!$rows) {
        tsvp_empty('Noch niemand angelegt');
        return;
    }
    $rollen = tsvp_rollen();
    ?>
    <div class="tsvp-tablewrap"><table class="tsvp-table">
        <thead><tr><th>Name</th><th>Rolle</th><th class="tsvp-hide-sm">Abteilung</th><th class="num tsvp-hide-sm">Stundensatz</th><th>Zugang</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r) :
            if (!(int) $r->aktiv) {
                $zugang = ['inaktiv', 'Deaktiviert'];
            } elseif ($r->pass_hash === '' && !$r->wp_user_id) {
                $zugang = ['eingereicht', 'Eingeladen'];
            } else {
                $zugang = ['freigegeben', $r->wp_user_id && $r->pass_hash === '' ? 'Über WordPress' : 'Aktiv'];
            }
            $edit = tsvp_kann_person_bearbeiten($u, $r) || (int) $r->id === (int) $u->id; ?>
            <tr>
                <td><strong><?php echo esc_html($r->name); ?></strong><br><small class="tsvp-muted"><?php echo esc_html($r->email); ?></small></td>
                <td><?php echo esc_html($rollen[$r->rolle] ?? $r->rolle); ?></td>
                <td class="tsvp-hide-sm"><?php echo $r->abteilung_id ? esc_html(tsvp_abteilung_name($r->abteilung_id)) : '–'; ?></td>
                <td class="num tsvp-hide-sm"><?php echo (float) $r->stundensatz > 0 ? esc_html(tsvp_money($r->stundensatz)) : '–'; ?></td>
                <td><span class="tsvp-badge tsvp-badge--<?php echo esc_attr($zugang[0]); ?>"><?php echo esc_html($zugang[1]); ?></span></td>
                <td class="tsvp-rowactions">
                    <?php if ($edit && (tsvp_kann_person_bearbeiten($u, $r))) : ?>
                        <a class="tsvp-btn tsvp-btn--ghost tsvp-btn--sm" href="<?php echo tsvp_link(['bereich' => 'verwaltung', 'person' => $r->id]); ?>">Bearbeiten</a>
                        <?php if ((int) $r->aktiv && (int) $r->id !== (int) $u->id) : ?>
                            <form method="post" class="tsvp-form--inline">
                                <?php tsvp_form_fields('benutzer_einladen'); ?>
                                <input type="hidden" name="id" value="<?php echo (int) $r->id; ?>">
                                <button class="tsvp-btn tsvp-btn--ghost tsvp-btn--sm" title="<?php echo $r->pass_hash === '' ? 'Einladung erneut senden' : 'Link zum Passwort-Zurücksetzen senden'; ?>"><?php echo $r->pass_hash === '' ? 'Einladen' : 'Passwort-Link'; ?></button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php
}

function tsvp_view_person_form($u, $id)
{
    $p = $id ? tsvp_get_benutzer($id) : null;
    tsvp_back('Verwaltung', ['bereich' => 'verwaltung']);
    if ($id && (!$p || !tsvp_kann_person_bearbeiten($u, $p))) {
        tsvp_empty('Nicht gefunden', 'Diese Person gibt es nicht oder du darfst sie nicht bearbeiten.');
        return;
    }
    $selbst = $p && (int) $p->id === (int) $u->id;
    ?>
    <div class="tsvp-pagehead"><div><h2><?php echo $p ? esc_html($p->name) : 'Person anlegen'; ?></h2>
        <p class="tsvp-muted"><?php echo $p ? 'Daten und Zugang bearbeiten.' : 'Die Person bekommt per E-Mail einen Link, um ihr Passwort festzulegen.'; ?></p></div></div>
    <form method="post" class="tsvp-card tsvp-form">
        <?php tsvp_form_fields('benutzer_speichern'); ?>
        <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
        <div class="tsvp-grid">
            <label>Name<input type="text" name="name" maxlength="120" required value="<?php echo esc_attr($p->name ?? ''); ?>"></label>
            <label>E-Mail <small>= Anmeldename</small><input type="email" name="email" required value="<?php echo esc_attr($p->email ?? ''); ?>"></label>
        </div>
        <div class="tsvp-grid">
            <?php if (tsvp_is_vorstand($u)) : ?>
                <label>Rolle<select name="rolle"<?php disabled($selbst); ?>>
                    <?php foreach (tsvp_rollen() as $k => $l) : ?><option value="<?php echo esc_attr($k); ?>"<?php selected($p->rolle ?? 'uebungsleiter', $k); ?>><?php echo esc_html($l); ?></option><?php endforeach; ?>
                </select></label>
                <?php if ($selbst) : ?><input type="hidden" name="rolle" value="<?php echo esc_attr($p->rolle); ?>"><?php endif; ?>
                <label>Abteilung<select name="abteilung_id"><?php echo tsvp_abteilung_options($p->abteilung_id ?? 0, '– keine –'); ?></select></label>
            <?php else : ?>
                <div class="tsvp-field"><span>Rolle</span><strong>Übungsleitung</strong></div>
                <div class="tsvp-field"><span>Abteilung</span><strong><?php echo esc_html(tsvp_abteilung_name($u->abteilung_id)); ?></strong></div>
            <?php endif; ?>
            <label>Stundensatz (€)<input type="number" name="stundensatz" step="0.01" min="0" inputmode="decimal" value="<?php echo esc_attr($p ? (float) $p->stundensatz : ''); ?>" placeholder="0,00"></label>
        </div>
        <?php if ($p) : ?>
            <label class="tsvp-check"><input type="checkbox" name="aktiv" value="1"<?php checked((int) $p->aktiv, 1); ?><?php disabled($selbst); ?>> Zugang aktiv</label>
            <?php if ($selbst) : ?><input type="hidden" name="aktiv" value="1"><?php endif; ?>
            <?php if ($p->pass_hash === '' && !$selbst) : ?>
                <label class="tsvp-check"><input type="checkbox" name="einladen" value="1"> Einladung (erneut) per E-Mail senden</label>
            <?php endif; ?>
        <?php else : ?>
            <label class="tsvp-check"><input type="checkbox" name="einladen" value="1" checked> Einladung jetzt per E-Mail senden</label>
        <?php endif; ?>
        <div class="tsvp-actions"><button class="tsvp-btn tsvp-btn--primary"><?php echo $p ? 'Speichern' : 'Anlegen'; ?></button></div>
    </form>
    <?php
}

function tsvp_view_abteilungen()
{
    global $wpdb;
    $counts = [];
    foreach ($wpdb->get_results('SELECT abteilung_id, COUNT(*) n FROM ' . tsvp_t('benutzer') . ' GROUP BY abteilung_id') as $r) {
        $counts[(int) $r->abteilung_id] = (int) $r->n;
    }
    $edit = isset($_GET['abteilung']) ? absint($_GET['abteilung']) : 0;
    ?>
    <div class="tsvp-card">
        <form method="post" class="tsvp-form tsvp-form--row">
            <?php tsvp_form_fields('abteilung_speichern'); ?>
            <label>Neue Abteilung<input type="text" name="name" maxlength="120" required placeholder="z. B. Turnen"></label>
            <button class="tsvp-btn tsvp-btn--primary">Hinzufügen</button>
        </form>
    </div>
    <?php if (!tsvp_abteilungen()) {
        tsvp_empty('Noch keine Abteilungen');
        return;
    } ?>
    <ul class="tsvp-list">
        <?php foreach (tsvp_abteilungen() as $id => $name) : ?>
            <li class="tsvp-list__row">
                <?php if ($edit === $id) : ?>
                    <form method="post" class="tsvp-form tsvp-form--row">
                        <?php tsvp_form_fields('abteilung_speichern'); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
                        <input type="text" name="name" value="<?php echo esc_attr($name); ?>" required aria-label="Name der Abteilung">
                        <button class="tsvp-btn tsvp-btn--primary tsvp-btn--sm">Speichern</button>
                        <a class="tsvp-btn tsvp-btn--ghost tsvp-btn--sm" href="<?php echo tsvp_link(['bereich' => 'verwaltung', 'tab' => 'abteilungen']); ?>">Abbrechen</a>
                    </form>
                <?php else : ?>
                    <span class="tsvp-list__main"><strong><?php echo esc_html($name); ?></strong><small><?php echo (int) ($counts[$id] ?? 0); ?> Personen</small></span>
                    <span class="tsvp-rowactions">
                        <a class="tsvp-btn tsvp-btn--ghost tsvp-btn--sm" href="<?php echo tsvp_link(['bereich' => 'verwaltung', 'tab' => 'abteilungen', 'abteilung' => $id]); ?>">Umbenennen</a>
                        <?php if (empty($counts[$id])) : ?>
                            <form method="post" class="tsvp-form--inline">
                                <?php tsvp_form_fields('abteilung_loeschen'); ?>
                                <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
                                <button class="tsvp-btn tsvp-btn--ghost tsvp-btn--sm tsvp-btn--dangertext" data-confirm="Abteilung „<?php echo esc_attr($name); ?>“ löschen?">Löschen</button>
                            </form>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
}

/* ------------------------------------------------------------------ */
/* Mein Konto                                                          */
/* ------------------------------------------------------------------ */

function tsvp_view_konto($u)
{
    ?>
    <div class="tsvp-pagehead"><div><h2>Mein Konto</h2><p class="tsvp-muted">Deine Daten für Abrechnungen und Auszahlungen.</p></div></div>
    <form method="post" class="tsvp-card tsvp-form">
        <?php tsvp_form_fields('konto_speichern'); ?>
        <div class="tsvp-grid">
            <label>Name<input type="text" name="name" maxlength="120" required value="<?php echo esc_attr($u->name); ?>"></label>
            <div class="tsvp-field"><span>E-Mail (Anmeldung)</span><strong><?php echo esc_html($u->email); ?></strong></div>
        </div>
        <div class="tsvp-grid">
            <label>IBAN für Auszahlungen<input type="text" name="iban" maxlength="42" autocomplete="off" value="<?php echo esc_attr(tsvp_iban_format($u->iban)); ?>" placeholder="DE00 0000 0000 0000 0000 00"></label>
            <div class="tsvp-field"><span>Stundensatz</span><strong><?php echo (float) $u->stundensatz > 0 ? esc_html(tsvp_money($u->stundensatz)) : 'noch nicht hinterlegt'; ?></strong></div>
        </div>
        <fieldset class="tsvp-fieldset">
            <legend><?php echo $u->pass_hash === '' ? 'Eigenes Portal-Passwort festlegen' : 'Passwort ändern'; ?> <small>optional</small></legend>
            <?php if ($u->pass_hash === '') : ?>
                <p class="tsvp-muted tsvp-small">Du bist gerade über deinen WordPress-Admin-Zugang angemeldet. Mit einem eigenen Passwort kannst du dich auch direkt im Portal anmelden.</p>
            <?php endif; ?>
            <div class="tsvp-grid">
                <?php if ($u->pass_hash !== '') : ?>
                    <label>Aktuelles Passwort<input type="password" name="passwort_alt" autocomplete="current-password"></label>
                <?php endif; ?>
                <label>Neues Passwort<input type="password" name="passwort_neu" minlength="8" autocomplete="new-password"></label>
                <label>Wiederholen<input type="password" name="passwort_neu2" minlength="8" autocomplete="new-password"></label>
            </div>
        </fieldset>
        <div class="tsvp-actions"><button class="tsvp-btn tsvp-btn--primary">Speichern</button></div>
    </form>
    <?php
}

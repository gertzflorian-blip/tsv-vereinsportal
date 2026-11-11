<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ------------------------------------------------------------------ */
/* Grundlagen                                                          */
/* ------------------------------------------------------------------ */

function tsvp_t($name)
{
    global $wpdb;
    return $wpdb->prefix . 'tsvp_' . $name;
}

function tsvp_now()
{
    return current_time('mysql', true); // UTC
}

function tsvp_rollen()
{
    return [
        'vorstand'         => 'Vorstand',
        'abteilungsleiter' => 'Abteilungsleitung',
        'uebungsleiter'    => 'Übungsleitung',
    ];
}

function tsvp_status_abrechnung()
{
    return [
        'eingereicht' => 'Eingereicht',
        'freigegeben' => 'Freigegeben',
        'abgelehnt'   => 'Abgelehnt',
        'ausgezahlt'  => 'Ausgezahlt',
    ];
}

function tsvp_status_antrag()
{
    return [
        'eingereicht' => 'Eingereicht',
        'genehmigt'   => 'Genehmigt',
        'abgelehnt'   => 'Abgelehnt',
        'ausgezahlt'  => 'Ausgezahlt',
    ];
}

function tsvp_kategorien()
{
    return [
        'jahreshauptversammlung' => 'Jahreshauptversammlung',
        'vereinsratssitzung'     => 'Vereinsratssitzung',
    ];
}

function tsvp_sichtbarkeiten()
{
    return [
        'alle'      => 'Alle im Portal',
        'abteilung' => 'Nur eine Abteilung',
        'vorstand'  => 'Nur Vorstand',
    ];
}

function tsvp_is_vorstand($u)
{
    return $u && $u->rolle === 'vorstand';
}

function tsvp_is_al($u)
{
    return $u && $u->rolle === 'abteilungsleiter';
}

function tsvp_darf_verwalten($u)
{
    return tsvp_is_vorstand($u) || tsvp_is_al($u);
}

/** Dokumente (Protokolle) sehen und hochladen: nur Vorstand und Abteilungsleitungen. */
function tsvp_darf_dokumente($u)
{
    return tsvp_darf_verwalten($u);
}

/* ------------------------------------------------------------------ */
/* URLs                                                                */
/* ------------------------------------------------------------------ */

function tsvp_page_url($args = [])
{
    $id  = (int) get_option('tsvp_page_id');
    $url = $id ? get_permalink($id) : home_url('/vereinsportal/');
    return $args ? add_query_arg($args, $url) : $url;
}

function tsvp_link($args = [])
{
    return esc_url(tsvp_page_url($args));
}

function tsvp_redirect($args = [])
{
    wp_safe_redirect(tsvp_page_url($args));
    exit;
}

function tsvp_is_portal_page()
{
    $id = (int) get_option('tsvp_page_id');
    if ($id && is_page($id)) {
        return true;
    }
    $post = get_post();
    return is_singular() && $post && has_shortcode($post->post_content, 'tsv_vereinsportal');
}

/* ------------------------------------------------------------------ */
/* Formatierung                                                        */
/* ------------------------------------------------------------------ */

function tsvp_money($v)
{
    return number_format((float) $v, 2, ',', '.') . ' €';
}

function tsvp_num($v)
{
    $s = number_format((float) $v, 2, ',', '.');
    return rtrim(rtrim($s, '0'), ',');
}

function tsvp_datum($ymd)
{
    $ts = strtotime($ymd);
    return $ts ? date_i18n('d.m.Y', $ts) : '';
}

/** Datum auf Deutsch, unabhängig von der WordPress-Sprache. Formate: 'lang', 'tag', 'monat_kurz', 'jahr'. */
function tsvp_datum_de($ymd, $format = 'lang')
{
    $ts = strtotime($ymd);
    if (!$ts) {
        return '';
    }
    $tage   = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
    $monate = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    $m      = $monate[(int) date('n', $ts) - 1];
    switch ($format) {
        case 'tag':
            return date('j', $ts);
        case 'monat_kurz':
            return mb_substr($m, 0, 3);
        case 'jahr':
            return date('Y', $ts);
        default:
            return $tage[(int) date('w', $ts)] . ', ' . date('j', $ts) . '. ' . $m . ' ' . date('Y', $ts);
    }
}

function tsvp_datum_utc($mysql, $format = 'd.m.Y')
{
    return $mysql ? get_date_from_gmt($mysql, $format) : '';
}

function tsvp_monat($ym)
{
    $ts = strtotime($ym . '-01 12:00:00');
    return $ts ? date_i18n('F Y', $ts) : $ym;
}

function tsvp_badge($status, $labels)
{
    $label = $labels[$status] ?? $status;
    return '<span class="tsvp-badge tsvp-badge--' . esc_attr($status) . '">' . esc_html($label) . '</span>';
}

function tsvp_parse_amount($raw)
{
    $raw = trim((string) $raw);
    if (strpos($raw, ',') !== false) {
        $raw = str_replace(['.', ' '], '', $raw);
        $raw = str_replace(',', '.', $raw);
    }
    return round((float) $raw, 2);
}

function tsvp_normalize_iban($iban)
{
    return strtoupper(preg_replace('/\s+/', '', (string) $iban));
}

function tsvp_iban_valid($iban)
{
    if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) {
        return false;
    }
    $s   = substr($iban, 4) . substr($iban, 0, 4);
    $num = '';
    foreach (str_split($s) as $ch) {
        $num .= ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch;
    }
    $mod = 0;
    foreach (str_split($num, 7) as $chunk) {
        $mod = (int) ($mod . $chunk) % 97;
    }
    return $mod === 1;
}

function tsvp_iban_format($iban)
{
    return trim(chunk_split($iban, 4, ' '));
}

/* ------------------------------------------------------------------ */
/* Daten                                                               */
/* ------------------------------------------------------------------ */

function tsvp_abteilungen($reset = false)
{
    static $cache = null;
    if ($cache === null || $reset) {
        global $wpdb;
        $cache = [];
        foreach ($wpdb->get_results('SELECT id, name FROM ' . tsvp_t('abteilungen') . ' ORDER BY name') as $r) {
            $cache[(int) $r->id] = $r->name;
        }
    }
    return $cache;
}

function tsvp_abteilung_name($id)
{
    $a = tsvp_abteilungen();
    return $a[(int) $id] ?? 'Gesamtverein';
}

function tsvp_get_benutzer($id)
{
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . tsvp_t('benutzer') . ' WHERE id = %d', $id));
}

function tsvp_get_abrechnung($id)
{
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        'SELECT x.*, b.name AS person, b.email AS person_email, b.iban AS person_iban
         FROM ' . tsvp_t('abrechnungen') . ' x
         LEFT JOIN ' . tsvp_t('benutzer') . ' b ON b.id = x.benutzer_id
         WHERE x.id = %d',
        $id
    ));
}

function tsvp_get_antrag($id)
{
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        'SELECT x.*, b.name AS person, b.email AS person_email
         FROM ' . tsvp_t('antraege') . ' x
         LEFT JOIN ' . tsvp_t('benutzer') . ' b ON b.id = x.benutzer_id
         WHERE x.id = %d',
        $id
    ));
}

/** WHERE-Teil: was darf die Person sehen (Tabellen-Alias x). */
function tsvp_scope_sql($u)
{
    global $wpdb;
    if (tsvp_is_vorstand($u)) {
        return '1=1';
    }
    if (tsvp_is_al($u) && $u->abteilung_id) {
        return $wpdb->prepare('(x.abteilung_id = %d OR x.benutzer_id = %d)', $u->abteilung_id, $u->id);
    }
    return $wpdb->prepare('x.benutzer_id = %d', $u->id);
}

function tsvp_dokument_scope_sql($u)
{
    return tsvp_darf_dokumente($u) ? '1=1' : '1=0';
}

/* ------------------------------------------------------------------ */
/* Rechte                                                              */
/* ------------------------------------------------------------------ */

function tsvp_kann_sehen($u, $row)
{
    if (!$u || !$row) {
        return false;
    }
    if (tsvp_is_vorstand($u) || (int) $row->benutzer_id === (int) $u->id) {
        return true;
    }
    return tsvp_is_al($u) && $u->abteilung_id && (int) $row->abteilung_id === (int) $u->abteilung_id;
}

function tsvp_kann_dokument_sehen($u, $row)
{
    if (!$u || !$row) {
        return false;
    }
    return tsvp_darf_dokumente($u);
}

/** Abrechnung: Freigabe durch Vorstand oder Abteilungsleitung (nie die eigene). */
function tsvp_kann_abrechnung_status($u, $row, $neu)
{
    if (!$u || !$row) {
        return false;
    }
    $eigene = (int) $row->benutzer_id === (int) $u->id;
    if (in_array($neu, ['freigegeben', 'abgelehnt'], true)) {
        if ($row->status !== 'eingereicht' || $eigene) {
            return false;
        }
        if (tsvp_is_vorstand($u)) {
            return true;
        }
        return tsvp_is_al($u) && $u->abteilung_id && (int) $row->abteilung_id === (int) $u->abteilung_id;
    }
    if ($neu === 'ausgezahlt') {
        return $row->status === 'freigegeben' && tsvp_is_vorstand($u);
    }
    return false;
}

/** Auszahlungsantrag: Entscheidung durch den Vorstand (nie der eigene). */
function tsvp_kann_antrag_status($u, $row, $neu)
{
    if (!$u || !$row || !tsvp_is_vorstand($u)) {
        return false;
    }
    if (in_array($neu, ['genehmigt', 'abgelehnt'], true)) {
        return $row->status === 'eingereicht' && (int) $row->benutzer_id !== (int) $u->id;
    }
    if ($neu === 'ausgezahlt') {
        return $row->status === 'genehmigt';
    }
    return false;
}

function tsvp_kann_loeschen($u, $row)
{
    if (!$u || !$row) {
        return false;
    }
    if (tsvp_is_vorstand($u)) {
        return true;
    }
    return (int) $row->benutzer_id === (int) $u->id && $row->status === 'eingereicht';
}

/* ------------------------------------------------------------------ */
/* Dateien (geschützter Ordner, Auslieferung nur über das Portal)      */
/* ------------------------------------------------------------------ */

function tsvp_private_dir()
{
    $up  = wp_upload_dir(null, false);
    $dir = trailingslashit($up['basedir']) . 'tsvp-privat';
    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
    }
    if (!file_exists($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    if (!file_exists($dir . '/index.php')) {
        @file_put_contents($dir . '/index.php', "<?php // Silence is golden.\n");
    }
    return $dir;
}

function tsvp_erlaubte_mimes()
{
    return [
        'pdf'  => 'application/pdf',
        'jpg|jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'odt'  => 'application/vnd.oasis.opendocument.text',
        'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        'txt'  => 'text/plain',
    ];
}

/**
 * Speichert eine hochgeladene Datei im geschützten Ordner.
 * Rückgabe: Array mit datei, name, mime, groesse – oder null bei Fehler.
 */
function tsvp_store_upload($file)
{
    if (empty($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($file['size'] <= 0 || $file['size'] > 20 * MB_IN_BYTES || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], tsvp_erlaubte_mimes());
    if (empty($check['ext']) || empty($check['type'])) {
        return null;
    }
    $ziel = bin2hex(random_bytes(16)) . '.' . $check['ext'];
    if (!@move_uploaded_file($file['tmp_name'], tsvp_private_dir() . '/' . $ziel)) {
        return null;
    }
    return [
        'datei'   => $ziel,
        'name'    => sanitize_file_name(wp_unslash($file['name'])),
        'mime'    => $check['type'],
        'groesse' => (int) $file['size'],
    ];
}

function tsvp_delete_file($datei)
{
    if (!$datei) {
        return;
    }
    $path = tsvp_private_dir() . '/' . basename($datei);
    if (is_file($path)) {
        @unlink($path);
    }
}

function tsvp_datei_typ($name)
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return $ext ? strtoupper($ext) : 'DATEI';
}

/* ------------------------------------------------------------------ */
/* E-Mails                                                             */
/* ------------------------------------------------------------------ */

function tsvp_mail($to, $subject, $body)
{
    $body .= "\n\n—\nVereinsportal · " . get_bloginfo('name') . "\n" . tsvp_page_url();
    return wp_mail($to, '[Vereinsportal] ' . $subject, $body, ['Content-Type: text/plain; charset=UTF-8']);
}

function tsvp_mail_rolle($rolle, $abteilung_id, $exclude_id, $subject, $body)
{
    global $wpdb;
    $sql = $wpdb->prepare('SELECT email FROM ' . tsvp_t('benutzer') . ' WHERE aktiv = 1 AND rolle = %s AND id <> %d', $rolle, $exclude_id);
    if ($abteilung_id !== null) {
        $sql .= $wpdb->prepare(' AND abteilung_id = %d', $abteilung_id);
    }
    $mails = $wpdb->get_col($sql);
    foreach ($mails as $m) {
        tsvp_mail($m, $subject, $body);
    }
    return count($mails);
}

/** Benachrichtigt die Abteilungsleitung – oder, falls keine da ist, den Vorstand. */
function tsvp_mail_pruefer($abteilung_id, $exclude_id, $subject, $body)
{
    $n = 0;
    if ($abteilung_id) {
        $n = tsvp_mail_rolle('abteilungsleiter', (int) $abteilung_id, $exclude_id, $subject, $body);
    }
    if (!$n) {
        tsvp_mail_rolle('vorstand', null, $exclude_id, $subject, $body);
    }
}

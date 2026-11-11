<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('template_redirect', 'tsvp_handle_requests');

function tsvp_handle_requests()
{
    if (isset($_GET['tsvp_datei'])) {
        tsvp_handle_download();
    }

    if (isset($_GET['tsvp_logout'])) {
        tsvp_current_user();
        if (tsvp_verify_nonce('logout', isset($_GET['n']) ? sanitize_text_field(wp_unslash($_GET['n'])) : '')) {
            tsvp_logout();
            tsvp_redirect(['tsvp_msg' => 'abgemeldet']);
        }
        tsvp_redirect();
    }

    if (tsvp_is_portal_page()) {
        nocache_headers();
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['tsvp_action'])) {
        return;
    }

    $action = sanitize_key(wp_unslash($_POST['tsvp_action']));
    $nonce  = isset($_POST['_tsvp']) ? sanitize_text_field(wp_unslash($_POST['_tsvp'])) : '';
    if (!tsvp_verify_nonce($action, $nonce)) {
        tsvp_redirect(['tsvp_msg' => 'sitzung']);
    }

    $oeffentlich = ['login', 'passwort_setzen', 'passwort_vergessen'];
    if (in_array($action, $oeffentlich, true)) {
        call_user_func('tsvp_action_' . $action);
        exit;
    }

    $u = tsvp_current_user();
    if (!$u) {
        tsvp_redirect(['tsvp_msg' => 'login']);
    }

    $erlaubt = [
        'konto_speichern',
        'abrechnung_speichern', 'abrechnung_status', 'abrechnung_loeschen',
        'antrag_speichern', 'antrag_status', 'antrag_loeschen',
        'dokument_hochladen', 'dokument_loeschen',
        'benutzer_speichern', 'benutzer_einladen',
        'abteilung_speichern', 'abteilung_loeschen',
    ];
    if (!in_array($action, $erlaubt, true)) {
        tsvp_redirect();
    }
    call_user_func('tsvp_action_' . $action, $u);
    exit;
}

function tsvp_post($key, $default = '')
{
    return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : $default;
}

/* ------------------------------------------------------------------ */
/* Anmeldung                                                           */
/* ------------------------------------------------------------------ */

function tsvp_action_login()
{
    $r = tsvp_login(sanitize_email(tsvp_post('email')), (string) tsvp_post('passwort'));
    if ($r === true) {
        tsvp_redirect();
    }
    tsvp_redirect(['tsvp_msg' => $r === 'gesperrt' ? 'gesperrt' : 'login_falsch']);
}

function tsvp_action_passwort_setzen()
{
    global $wpdb;
    $token = sanitize_text_field(tsvp_post('token'));
    $u     = tsvp_user_by_token($token);
    if (!$u) {
        tsvp_redirect(['ansicht' => 'vergessen', 'tsvp_msg' => 'token']);
    }
    $p1 = (string) tsvp_post('passwort');
    $p2 = (string) tsvp_post('passwort2');
    $zurueck = ['ansicht' => 'passwort', 'token' => $token];
    if (strlen($p1) < 8) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'pw_kurz']);
    }
    if ($p1 !== $p2) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'pw_ungleich']);
    }
    $wpdb->update(tsvp_t('benutzer'), [
        'pass_hash'     => password_hash($p1, PASSWORD_DEFAULT),
        'token_hash'    => '',
        'token_expires' => null,
    ], ['id' => $u->id]);
    tsvp_end_sessions($u->id);
    tsvp_start_session($u->id);
    tsvp_redirect(['tsvp_msg' => 'pw_gesetzt']);
}

function tsvp_action_passwort_vergessen()
{
    global $wpdb;
    $email = sanitize_email(tsvp_post('email'));
    $sperre = 'tsvp_reset_' . md5(strtolower($email));
    if ($email && !get_transient($sperre)) {
        $u = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . tsvp_t('benutzer') . ' WHERE email = %s AND aktiv = 1', $email));
        if ($u) {
            tsvp_send_link($u, 'reset');
            set_transient($sperre, 1, 2 * MINUTE_IN_SECONDS);
        }
    }
    tsvp_redirect(['tsvp_msg' => 'link_gesendet']);
}

/* ------------------------------------------------------------------ */
/* Mein Konto                                                          */
/* ------------------------------------------------------------------ */

function tsvp_action_konto_speichern($u)
{
    global $wpdb;
    $zurueck = ['bereich' => 'konto'];
    $name = sanitize_text_field(tsvp_post('name'));
    $iban = tsvp_normalize_iban(tsvp_post('iban'));
    if ($name === '') {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'pflicht']);
    }
    if ($iban !== '' && !tsvp_iban_valid($iban)) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'iban']);
    }
    $daten = ['name' => $name, 'iban' => $iban];

    $neu = (string) tsvp_post('passwort_neu');
    if ($neu !== '') {
        if ($u->pass_hash !== '' && !password_verify((string) tsvp_post('passwort_alt'), $u->pass_hash)) {
            tsvp_redirect($zurueck + ['tsvp_msg' => 'pw_falsch']);
        }
        if (strlen($neu) < 8) {
            tsvp_redirect($zurueck + ['tsvp_msg' => 'pw_kurz']);
        }
        if ($neu !== (string) tsvp_post('passwort_neu2')) {
            tsvp_redirect($zurueck + ['tsvp_msg' => 'pw_ungleich']);
        }
        $daten['pass_hash'] = password_hash($neu, PASSWORD_DEFAULT);
    }

    $wpdb->update(tsvp_t('benutzer'), $daten, ['id' => $u->id]);
    if (isset($daten['pass_hash'])) {
        tsvp_end_sessions($u->id);
        tsvp_start_session($u->id);
    }
    tsvp_redirect($zurueck + ['tsvp_msg' => 'gespeichert']);
}

/* ------------------------------------------------------------------ */
/* Abrechnungen                                                        */
/* ------------------------------------------------------------------ */

function tsvp_action_abrechnung_speichern($u)
{
    global $wpdb;
    $zurueck = ['bereich' => 'abrechnungen', 'neu' => 1];

    $monat = sanitize_text_field(tsvp_post('monat'));
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monat)) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'pflicht']);
    }

    $satz = (float) $u->stundensatz;
    if ($satz <= 0) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'kein_satz']);
    }

    $abteilung = (int) $u->abteilung_id;
    if (!$abteilung) {
        $abteilung = absint(tsvp_post('abteilung_id', 0));
        if ($abteilung && !isset(tsvp_abteilungen()[$abteilung])) {
            $abteilung = 0;
        }
    }

    $daten  = (array) tsvp_post('datum', []);
    $std    = (array) tsvp_post('stunden', []);
    $texte  = (array) tsvp_post('taetigkeit', []);
    $pos    = [];
    $summe  = 0.0;
    foreach ($daten as $i => $d) {
        $d   = sanitize_text_field($d);
        $h   = tsvp_parse_amount($std[$i] ?? '');
        $txt = sanitize_text_field($texte[$i] ?? '');
        if ($d === '' && $h <= 0 && $txt === '') {
            continue;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || $h <= 0 || $h > 24) {
            tsvp_redirect($zurueck + ['tsvp_msg' => 'positionen']);
        }
        if (substr($d, 0, 7) !== $monat) {
            tsvp_redirect($zurueck + ['tsvp_msg' => 'monat_passt']);
        }
        $pos[]  = ['datum' => $d, 'stunden' => $h, 'taetigkeit' => mb_substr($txt, 0, 120)];
        $summe += $h;
    }
    if (!$pos) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'positionen']);
    }
    usort($pos, function ($a, $b) {
        return strcmp($a['datum'], $b['datum']);
    });

    $doppelt = $wpdb->get_var($wpdb->prepare(
        'SELECT id FROM ' . tsvp_t('abrechnungen') . " WHERE benutzer_id = %d AND monat = %s AND abteilung_id = %d AND status <> 'abgelehnt'",
        $u->id,
        $monat,
        $abteilung
    ));
    if ($doppelt) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'doppelt']);
    }

    $wpdb->insert(tsvp_t('abrechnungen'), [
        'benutzer_id'  => $u->id,
        'abteilung_id' => $abteilung,
        'monat'        => $monat,
        'positionen'   => wp_json_encode($pos),
        'stunden'      => $summe,
        'stundensatz'  => $satz,
        'betrag'       => round($summe * $satz, 2),
        'bemerkung'    => sanitize_textarea_field(tsvp_post('bemerkung')),
        'status'       => 'eingereicht',
        'kommentar'    => '',
        'created_at'   => tsvp_now(),
    ]);
    $id = (int) $wpdb->insert_id;

    tsvp_mail_pruefer(
        $abteilung,
        $u->id,
        'Neue Abrechnung von ' . $u->name,
        $u->name . ' hat die Abrechnung für ' . tsvp_monat($monat) . ' eingereicht ('
            . tsvp_num($summe) . ' Std., ' . tsvp_money($summe * $satz) . ").\n\nZur Prüfung:\n"
            . tsvp_page_url(['bereich' => 'abrechnungen', 'id' => $id])
    );

    tsvp_redirect(['bereich' => 'abrechnungen', 'id' => $id, 'tsvp_msg' => 'eingereicht']);
}

function tsvp_action_abrechnung_status($u)
{
    global $wpdb;
    $id  = absint(tsvp_post('id'));
    $row = tsvp_get_abrechnung($id);
    $neu = sanitize_key(tsvp_post('status'));
    $zurueck = ['bereich' => 'abrechnungen', 'id' => $id];

    if (!tsvp_kann_abrechnung_status($u, $row, $neu)) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'keine_rechte']);
    }
    $kommentar = sanitize_textarea_field(tsvp_post('kommentar'));
    if ($neu === 'abgelehnt' && $kommentar === '') {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'kommentar']);
    }

    $wpdb->update(tsvp_t('abrechnungen'), [
        'status'         => $neu,
        'kommentar'      => $kommentar !== '' ? $kommentar : $row->kommentar,
        'bearbeitet_von' => $u->id,
        'bearbeitet_am'  => tsvp_now(),
    ], ['id' => $id]);

    $labels = tsvp_status_abrechnung();
    tsvp_mail(
        $row->person_email,
        'Deine Abrechnung ' . tsvp_monat($row->monat) . ': ' . $labels[$neu],
        "Hallo {$row->person},\n\ndeine Abrechnung für " . tsvp_monat($row->monat) . ' (' . tsvp_money($row->betrag) . ') hat jetzt den Status „'
            . $labels[$neu] . '“.' . ($kommentar !== '' ? "\n\nKommentar von {$u->name}:\n{$kommentar}" : '')
            . "\n\n" . tsvp_page_url($zurueck)
    );

    if ($neu === 'freigegeben' && !tsvp_is_vorstand($u)) {
        tsvp_mail_rolle('vorstand', null, $u->id, 'Abrechnung zur Auszahlung freigegeben',
            "{$u->name} hat die Abrechnung von {$row->person} (" . tsvp_monat($row->monat) . ', ' . tsvp_money($row->betrag) . ") freigegeben.\n\n"
            . tsvp_page_url($zurueck));
    }

    tsvp_redirect($zurueck + ['tsvp_msg' => 'status']);
}

function tsvp_action_abrechnung_loeschen($u)
{
    global $wpdb;
    $row = tsvp_get_abrechnung(absint(tsvp_post('id')));
    if (!tsvp_kann_loeschen($u, $row)) {
        tsvp_redirect(['bereich' => 'abrechnungen', 'tsvp_msg' => 'keine_rechte']);
    }
    $wpdb->delete(tsvp_t('abrechnungen'), ['id' => $row->id]);
    tsvp_redirect(['bereich' => 'abrechnungen', 'tsvp_msg' => 'geloescht']);
}

/* ------------------------------------------------------------------ */
/* Auszahlungsanträge                                                  */
/* ------------------------------------------------------------------ */

function tsvp_action_antrag_speichern($u)
{
    global $wpdb;
    $zurueck = ['bereich' => 'auszahlungen', 'neu' => 1];
    if (!tsvp_darf_verwalten($u)) {
        tsvp_redirect(['tsvp_msg' => 'keine_rechte']);
    }

    $zweck      = sanitize_text_field(tsvp_post('zweck'));
    $betrag     = tsvp_parse_amount(tsvp_post('betrag'));
    $empfaenger = sanitize_text_field(tsvp_post('empfaenger'));
    $iban       = tsvp_normalize_iban(tsvp_post('iban'));
    if ($zweck === '' || $betrag <= 0 || $empfaenger === '') {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'pflicht']);
    }
    if ($iban !== '' && !tsvp_iban_valid($iban)) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'iban']);
    }

    if (tsvp_is_al($u)) {
        $abteilung = (int) $u->abteilung_id;
    } else {
        $abteilung = absint(tsvp_post('abteilung_id', 0));
        if ($abteilung && !isset(tsvp_abteilungen()[$abteilung])) {
            $abteilung = 0;
        }
    }

    $beleg = null;
    if (!empty($_FILES['beleg']) && $_FILES['beleg']['error'] !== UPLOAD_ERR_NO_FILE) {
        $beleg = tsvp_store_upload($_FILES['beleg']);
        if (!$beleg) {
            tsvp_redirect($zurueck + ['tsvp_msg' => 'upload']);
        }
    }

    $wpdb->insert(tsvp_t('antraege'), [
        'benutzer_id'  => $u->id,
        'abteilung_id' => $abteilung,
        'zweck'        => mb_substr($zweck, 0, 255),
        'beschreibung' => sanitize_textarea_field(tsvp_post('beschreibung')),
        'betrag'       => $betrag,
        'empfaenger'   => mb_substr($empfaenger, 0, 190),
        'iban'         => $iban,
        'beleg_datei'  => $beleg ? $beleg['datei'] : '',
        'beleg_name'   => $beleg ? $beleg['name'] : '',
        'status'       => 'eingereicht',
        'kommentar'    => '',
        'created_at'   => tsvp_now(),
    ]);
    $id = (int) $wpdb->insert_id;

    tsvp_mail_rolle('vorstand', null, $u->id, 'Neuer Auszahlungsantrag',
        "{$u->name} beantragt eine Auszahlung: {$zweck} (" . tsvp_money($betrag) . ', ' . tsvp_abteilung_name($abteilung) . ").\n\n"
        . tsvp_page_url(['bereich' => 'auszahlungen', 'id' => $id]));

    tsvp_redirect(['bereich' => 'auszahlungen', 'id' => $id, 'tsvp_msg' => 'eingereicht']);
}

function tsvp_action_antrag_status($u)
{
    global $wpdb;
    $id  = absint(tsvp_post('id'));
    $row = tsvp_get_antrag($id);
    $neu = sanitize_key(tsvp_post('status'));
    $zurueck = ['bereich' => 'auszahlungen', 'id' => $id];

    if (!tsvp_kann_antrag_status($u, $row, $neu)) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'keine_rechte']);
    }
    $kommentar = sanitize_textarea_field(tsvp_post('kommentar'));
    if ($neu === 'abgelehnt' && $kommentar === '') {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'kommentar']);
    }

    $wpdb->update(tsvp_t('antraege'), [
        'status'         => $neu,
        'kommentar'      => $kommentar !== '' ? $kommentar : $row->kommentar,
        'bearbeitet_von' => $u->id,
        'bearbeitet_am'  => tsvp_now(),
    ], ['id' => $id]);

    $labels = tsvp_status_antrag();
    tsvp_mail(
        $row->person_email,
        'Dein Auszahlungsantrag: ' . $labels[$neu],
        "Hallo {$row->person},\n\ndein Antrag „{$row->zweck}“ (" . tsvp_money($row->betrag) . ') hat jetzt den Status „' . $labels[$neu] . '“.'
            . ($kommentar !== '' ? "\n\nKommentar von {$u->name}:\n{$kommentar}" : '') . "\n\n" . tsvp_page_url($zurueck)
    );

    tsvp_redirect($zurueck + ['tsvp_msg' => 'status']);
}

function tsvp_action_antrag_loeschen($u)
{
    global $wpdb;
    $row = tsvp_get_antrag(absint(tsvp_post('id')));
    if (!tsvp_kann_loeschen($u, $row)) {
        tsvp_redirect(['bereich' => 'auszahlungen', 'tsvp_msg' => 'keine_rechte']);
    }
    tsvp_delete_file($row->beleg_datei);
    $wpdb->delete(tsvp_t('antraege'), ['id' => $row->id]);
    tsvp_redirect(['bereich' => 'auszahlungen', 'tsvp_msg' => 'geloescht']);
}

/* ------------------------------------------------------------------ */
/* Dokumente                                                           */
/* ------------------------------------------------------------------ */

function tsvp_action_dokument_hochladen($u)
{
    global $wpdb;
    $zurueck = ['bereich' => 'dokumente'];
    if (!tsvp_darf_dokumente($u)) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'keine_rechte']);
    }

    $titel     = sanitize_text_field(tsvp_post('titel'));
    $kategorie = sanitize_key(tsvp_post('kategorie'));
    if ($titel === '' || !isset(tsvp_kategorien()[$kategorie])) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'pflicht']);
    }
    $sitzung = sanitize_text_field(tsvp_post('sitzung_datum'));
    $d       = DateTime::createFromFormat('!Y-m-d', $sitzung);
    if (!$d || $d->format('Y-m-d') !== $sitzung) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'sitzung_datum']);
    }
    // Protokolle sehen immer Vorstand und Abteilungsleitungen.
    $sicht     = 'leitung';
    $abteilung = 0;

    $datei = tsvp_store_upload($_FILES['datei'] ?? null);
    if (!$datei) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'upload']);
    }

    $wpdb->insert(tsvp_t('dokumente'), [
        'titel'        => mb_substr($titel, 0, 190),
        'beschreibung' => sanitize_textarea_field(tsvp_post('beschreibung')),
        'kategorie'    => $kategorie,
        'sichtbarkeit' => $sicht,
        'abteilung_id' => $abteilung,
        'sitzung_datum' => $sitzung,
        'datei'        => $datei['datei'],
        'dateiname'    => $datei['name'],
        'mime'         => $datei['mime'],
        'groesse'      => $datei['groesse'],
        'benutzer_id'  => $u->id,
        'created_at'   => tsvp_now(),
    ]);
    tsvp_redirect($zurueck + ['kategorie' => $kategorie, 'tsvp_msg' => 'hochgeladen']);
}

function tsvp_action_dokument_loeschen($u)
{
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . tsvp_t('dokumente') . ' WHERE id = %d', absint(tsvp_post('id'))));
    if (!$row || !tsvp_darf_dokumente($u) || !(tsvp_is_vorstand($u) || (int) $row->benutzer_id === (int) $u->id)) {
        tsvp_redirect(['bereich' => 'dokumente', 'tsvp_msg' => 'keine_rechte']);
    }
    tsvp_delete_file($row->datei);
    $wpdb->delete(tsvp_t('dokumente'), ['id' => $row->id]);
    tsvp_redirect(['bereich' => 'dokumente', 'tsvp_msg' => 'geloescht']);
}

/* ------------------------------------------------------------------ */
/* Verwaltung: Personen & Abteilungen                                  */
/* ------------------------------------------------------------------ */

/** Darf $u die Person $ziel bearbeiten? */
function tsvp_kann_person_bearbeiten($u, $ziel)
{
    if (tsvp_is_vorstand($u)) {
        return true;
    }
    return tsvp_is_al($u) && $ziel && $ziel->rolle === 'uebungsleiter'
        && $u->abteilung_id && (int) $ziel->abteilung_id === (int) $u->abteilung_id;
}

function tsvp_action_benutzer_speichern($u)
{
    global $wpdb;
    if (!tsvp_darf_verwalten($u)) {
        tsvp_redirect(['tsvp_msg' => 'keine_rechte']);
    }
    $tab = tsvp_t('benutzer');
    $id  = absint(tsvp_post('id'));
    $alt = $id ? tsvp_get_benutzer($id) : null;
    $von     = tsvp_post('von') === 'abteilungen' && tsvp_is_vorstand($u) ? 'abteilungen' : '';
    $zurueck = array_filter(['bereich' => 'verwaltung', 'person' => $id ?: 'neu', 'von' => $von]);
    if (!$id) {
        $zurueck += array_filter(['abteilung' => absint(tsvp_post('abteilung_id', 0)), 'rolle' => sanitize_key(tsvp_post('rolle'))]);
    }

    if ($id && (!$alt || !tsvp_kann_person_bearbeiten($u, $alt))) {
        tsvp_redirect(['bereich' => 'verwaltung', 'tsvp_msg' => 'keine_rechte']);
    }

    $name  = sanitize_text_field(tsvp_post('name'));
    $email = strtolower(sanitize_email(tsvp_post('email')));
    $satz  = max(0, tsvp_parse_amount(tsvp_post('stundensatz')));
    $aktiv = $id ? (tsvp_post('aktiv') ? 1 : 0) : 1;

    if ($name === '') {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'pflicht']);
    }
    if (!is_email($email)) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'email']);
    }
    $vergeben = $wpdb->get_var($wpdb->prepare("SELECT id FROM $tab WHERE email = %s AND id <> %d", $email, $id));
    if ($vergeben) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'email_vergeben']);
    }

    if (tsvp_is_vorstand($u)) {
        $rolle     = sanitize_key(tsvp_post('rolle'));
        $abteilung = absint(tsvp_post('abteilung_id', 0));
        if (!isset(tsvp_rollen()[$rolle])) {
            $rolle = 'uebungsleiter';
        }
        if ($abteilung && !isset(tsvp_abteilungen()[$abteilung])) {
            $abteilung = 0;
        }
        if ($rolle === 'abteilungsleiter' && !$abteilung) {
            tsvp_redirect($zurueck + ['tsvp_msg' => 'abteilung_fehlt']);
        }
    } else {
        $rolle     = 'uebungsleiter';
        $abteilung = (int) $u->abteilung_id;
    }

    if ($id && (int) $id === (int) $u->id && ($rolle !== $u->rolle || !$aktiv)) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'selbst']);
    }

    $daten = [
        'name'         => $name,
        'email'        => $email,
        'rolle'        => $rolle,
        'abteilung_id' => $abteilung,
        'stundensatz'  => $satz,
        'aktiv'        => $aktiv,
    ];

    if ($id) {
        $wpdb->update($tab, $daten, ['id' => $id]);
        if (!$aktiv) {
            tsvp_end_sessions($id);
        }
    } else {
        $daten['created_at'] = tsvp_now();
        $daten['pass_hash']  = '';
        $wpdb->insert($tab, $daten);
        $id = (int) $wpdb->insert_id;
    }

    $msg = 'gespeichert';
    if (tsvp_post('einladen') && $aktiv) {
        $msg = tsvp_send_link(tsvp_get_benutzer($id), 'einladung') ? 'eingeladen' : 'mail_fehler';
    }
    tsvp_redirect(['bereich' => $von ?: 'verwaltung', 'tsvp_msg' => $msg]);
}

function tsvp_action_benutzer_einladen($u)
{
    $ziel = tsvp_get_benutzer(absint(tsvp_post('id')));
    if (!$ziel || !tsvp_darf_verwalten($u) || !tsvp_kann_person_bearbeiten($u, $ziel) || !(int) $ziel->aktiv) {
        tsvp_redirect(['bereich' => 'verwaltung', 'tsvp_msg' => 'keine_rechte']);
    }
    $ok = tsvp_send_link($ziel, $ziel->pass_hash === '' ? 'einladung' : 'reset');
    tsvp_redirect(['bereich' => 'verwaltung', 'tsvp_msg' => $ok ? 'eingeladen' : 'mail_fehler']);
}

function tsvp_action_abteilung_speichern($u)
{
    global $wpdb;
    $zurueck = ['bereich' => 'verwaltung', 'tab' => 'abteilungen'];
    if (!tsvp_is_vorstand($u)) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'keine_rechte']);
    }
    $id   = absint(tsvp_post('id'));
    $name = sanitize_text_field(tsvp_post('name'));
    if ($name === '') {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'pflicht']);
    }
    if ($id) {
        $wpdb->update(tsvp_t('abteilungen'), ['name' => $name], ['id' => $id]);
    } else {
        $wpdb->insert(tsvp_t('abteilungen'), ['name' => $name, 'created_at' => tsvp_now()]);
    }
    tsvp_redirect($zurueck + ['tsvp_msg' => 'gespeichert']);
}

function tsvp_action_abteilung_loeschen($u)
{
    global $wpdb;
    $zurueck = ['bereich' => 'verwaltung', 'tab' => 'abteilungen'];
    if (!tsvp_is_vorstand($u)) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'keine_rechte']);
    }
    $id = absint(tsvp_post('id'));
    $belegt = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . tsvp_t('benutzer') . ' WHERE abteilung_id = %d', $id));
    if ($belegt) {
        tsvp_redirect($zurueck + ['tsvp_msg' => 'abteilung_belegt']);
    }
    $wpdb->delete(tsvp_t('abteilungen'), ['id' => $id]);
    tsvp_redirect($zurueck + ['tsvp_msg' => 'geloescht']);
}

/* ------------------------------------------------------------------ */
/* Geschützte Downloads                                                */
/* ------------------------------------------------------------------ */

function tsvp_handle_download()
{
    global $wpdb;
    $u = tsvp_current_user();
    if (!$u) {
        wp_die('Bitte melde dich im Vereinsportal an.', 'Vereinsportal', ['response' => 403, 'link_url' => tsvp_page_url(), 'link_text' => 'Zum Vereinsportal']);
    }
    $art = sanitize_key($_GET['tsvp_datei']);
    $id  = absint($_GET['id'] ?? 0);

    if ($art === 'dok') {
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . tsvp_t('dokumente') . ' WHERE id = %d', $id));
        if (!$row || !tsvp_kann_dokument_sehen($u, $row)) {
            wp_die('Datei nicht gefunden.', 'Vereinsportal', ['response' => 404]);
        }
        $datei = $row->datei;
        $name  = $row->dateiname;
        $mime  = $row->mime;
    } elseif ($art === 'beleg') {
        $row = tsvp_get_antrag($id);
        if (!$row || !$row->beleg_datei || !tsvp_kann_sehen($u, $row)) {
            wp_die('Datei nicht gefunden.', 'Vereinsportal', ['response' => 404]);
        }
        $datei = $row->beleg_datei;
        $name  = $row->beleg_name;
        $ft    = wp_check_filetype($name, tsvp_erlaubte_mimes());
        $mime  = $ft['type'] ?: 'application/octet-stream';
    } else {
        wp_die('Ungültige Anfrage.', 'Vereinsportal', ['response' => 400]);
    }

    $path = tsvp_private_dir() . '/' . basename($datei);
    if (!is_file($path)) {
        wp_die('Datei nicht gefunden.', 'Vereinsportal', ['response' => 404]);
    }

    while (ob_get_level()) {
        ob_end_clean();
    }
    nocache_headers();
    $inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true);
    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $name) . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

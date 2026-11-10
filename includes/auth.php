<?php
if (!defined('ABSPATH')) {
    exit;
}

define('TSVP_COOKIE', 'tsvp_session');

/**
 * Aktuell angemeldete Person im Portal.
 * WordPress-Administratoren werden automatisch als Vorstand verknüpft.
 */
function tsvp_current_user()
{
    global $wpdb, $tsvp_session_hash;
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;

    if (!empty($_COOKIE[TSVP_COOKIE])) {
        $hash = hash('sha256', sanitize_text_field(wp_unslash($_COOKIE[TSVP_COOKIE])));
        $row  = $wpdb->get_row($wpdb->prepare(
            'SELECT b.* FROM ' . tsvp_t('sessions') . ' s
             JOIN ' . tsvp_t('benutzer') . ' b ON b.id = s.benutzer_id
             WHERE s.token_hash = %s AND s.expires > %s AND b.aktiv = 1',
            $hash,
            tsvp_now()
        ));
        if ($row) {
            $user              = $row;
            $tsvp_session_hash = $hash;
        }
    }

    if (!$user && is_user_logged_in() && current_user_can('manage_options')) {
        $user = tsvp_wp_admin_user();
    }

    return $user;
}

function tsvp_wp_admin_user()
{
    global $wpdb;
    $wp  = wp_get_current_user();
    $tab = tsvp_t('benutzer');

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tab WHERE wp_user_id = %d", $wp->ID));
    if (!$row) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tab WHERE email = %s", $wp->user_email));
        if ($row) {
            $wpdb->update($tab, ['wp_user_id' => $wp->ID], ['id' => $row->id]);
        } else {
            $wpdb->insert($tab, [
                'wp_user_id' => $wp->ID,
                'name'       => $wp->display_name ?: $wp->user_login,
                'email'      => $wp->user_email,
                'rolle'      => 'vorstand',
                'aktiv'      => 1,
                'created_at' => tsvp_now(),
            ]);
        }
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tab WHERE wp_user_id = %d", $wp->ID));
    }
    return ($row && (int) $row->aktiv === 1) ? $row : null;
}

/** Ist die Person nur über den WordPress-Login drin (ohne eigene Portal-Sitzung)? */
function tsvp_via_wordpress()
{
    global $tsvp_session_hash;
    return empty($tsvp_session_hash);
}

/* ------------------------------------------------------------------ */
/* Eigene Formular-Tokens (CSRF), gebunden an die Portal-Sitzung       */
/* ------------------------------------------------------------------ */

function tsvp_session_key()
{
    global $tsvp_session_hash;
    if (!empty($tsvp_session_hash)) {
        return $tsvp_session_hash;
    }
    if (is_user_logged_in()) {
        return 'wp' . get_current_user_id() . '|' . wp_get_session_token();
    }
    return 'gast';
}

function tsvp_nonce($action)
{
    tsvp_current_user();
    return substr(hash_hmac('sha256', $action . '|' . tsvp_session_key(), wp_salt('nonce')), 0, 24);
}

function tsvp_verify_nonce($action, $nonce)
{
    return is_string($nonce) && $nonce !== '' && hash_equals(tsvp_nonce($action), $nonce);
}

/* ------------------------------------------------------------------ */
/* Anmeldung / Sitzungen                                               */
/* ------------------------------------------------------------------ */

function tsvp_login($email, $passwort)
{
    global $wpdb;
    $ip    = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $key   = 'tsvp_login_' . md5($ip);
    $tries = (int) get_transient($key);
    if ($tries >= 10) {
        return 'gesperrt';
    }

    $u = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . tsvp_t('benutzer') . ' WHERE email = %s AND aktiv = 1', $email));
    if (!$u || $u->pass_hash === '' || !password_verify($passwort, $u->pass_hash)) {
        set_transient($key, $tries + 1, 15 * MINUTE_IN_SECONDS);
        return 'falsch';
    }

    delete_transient($key);
    if (password_needs_rehash($u->pass_hash, PASSWORD_DEFAULT)) {
        $wpdb->update(tsvp_t('benutzer'), ['pass_hash' => password_hash($passwort, PASSWORD_DEFAULT)], ['id' => $u->id]);
    }
    tsvp_start_session($u->id);
    return true;
}

function tsvp_start_session($benutzer_id)
{
    global $wpdb;
    $token = bin2hex(random_bytes(32));
    $ttl   = 30 * DAY_IN_SECONDS;

    $wpdb->query($wpdb->prepare('DELETE FROM ' . tsvp_t('sessions') . ' WHERE expires < %s', tsvp_now()));
    $wpdb->insert(tsvp_t('sessions'), [
        'benutzer_id' => $benutzer_id,
        'token_hash'  => hash('sha256', $token),
        'expires'     => gmdate('Y-m-d H:i:s', time() + $ttl),
        'created_at'  => tsvp_now(),
    ]);
    $wpdb->update(tsvp_t('benutzer'), ['last_login' => tsvp_now()], ['id' => $benutzer_id]);

    tsvp_set_cookie($token, time() + $ttl);
}

function tsvp_set_cookie($value, $expires)
{
    setcookie(TSVP_COOKIE, $value, [
        'expires'  => $expires,
        'path'     => COOKIEPATH ?: '/',
        'domain'   => COOKIE_DOMAIN ?: '',
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function tsvp_logout()
{
    global $wpdb;
    if (!empty($_COOKIE[TSVP_COOKIE])) {
        $hash = hash('sha256', sanitize_text_field(wp_unslash($_COOKIE[TSVP_COOKIE])));
        $wpdb->delete(tsvp_t('sessions'), ['token_hash' => $hash]);
    }
    tsvp_set_cookie('', time() - YEAR_IN_SECONDS);
}

/** Beendet alle Sitzungen einer Person (z. B. nach Deaktivierung oder Passwortänderung). */
function tsvp_end_sessions($benutzer_id)
{
    global $wpdb;
    $wpdb->delete(tsvp_t('sessions'), ['benutzer_id' => $benutzer_id]);
}

/* ------------------------------------------------------------------ */
/* Einladungs- und Passwort-Links                                      */
/* ------------------------------------------------------------------ */

function tsvp_new_token($benutzer_id)
{
    global $wpdb;
    $token = bin2hex(random_bytes(24));
    $wpdb->update(tsvp_t('benutzer'), [
        'token_hash'    => hash('sha256', $token),
        'token_expires' => gmdate('Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS),
    ], ['id' => $benutzer_id]);
    return $token;
}

function tsvp_user_by_token($token)
{
    global $wpdb;
    if (!preg_match('/^[a-f0-9]{48}$/', (string) $token)) {
        return null;
    }
    return $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . tsvp_t('benutzer') . ' WHERE token_hash = %s AND token_expires > %s AND aktiv = 1',
        hash('sha256', $token),
        tsvp_now()
    ));
}

function tsvp_send_link($u, $art = 'einladung')
{
    $token = tsvp_new_token($u->id);
    $link  = tsvp_page_url(['ansicht' => 'passwort', 'token' => $token]);
    $verein = get_bloginfo('name');

    if ($art === 'einladung') {
        $subject = 'Einladung zum Vereinsportal';
        $text    = "Hallo {$u->name},\n\ndu hast einen Zugang zum Vereinsportal ({$verein}) bekommen. "
                 . "Dort reichst du z. B. deine Abrechnungen ein und findest Protokolle und Formulare.\n\n"
                 . "Lege über diesen Link dein Passwort fest:\n{$link}\n\n"
                 . "Deine Anmeldung: {$u->email}\nDer Link ist 7 Tage gültig.";
    } else {
        $subject = 'Neues Passwort festlegen';
        $text    = "Hallo {$u->name},\n\nüber diesen Link kannst du ein neues Passwort für das Vereinsportal festlegen:\n{$link}\n\n"
                 . "Der Link ist 7 Tage gültig. Falls du das nicht angefordert hast, kannst du diese E-Mail ignorieren.";
    }
    return tsvp_mail($u->email, $subject, $text);
}

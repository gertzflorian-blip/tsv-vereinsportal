<?php
if (!defined('ABSPATH')) {
    exit;
}

function tsvp_install()
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $c = $wpdb->get_charset_collate();

    dbDelta("CREATE TABLE " . tsvp_t('abteilungen') . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(120) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id)
    ) $c;");

    dbDelta("CREATE TABLE " . tsvp_t('benutzer') . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        name varchar(120) NOT NULL,
        email varchar(190) NOT NULL,
        pass_hash varchar(255) NOT NULL DEFAULT '',
        rolle varchar(20) NOT NULL DEFAULT 'uebungsleiter',
        abteilung_id bigint(20) unsigned NOT NULL DEFAULT 0,
        stundensatz decimal(8,2) NOT NULL DEFAULT 0,
        iban varchar(40) NOT NULL DEFAULT '',
        aktiv tinyint(1) NOT NULL DEFAULT 1,
        token_hash varchar(64) NOT NULL DEFAULT '',
        token_expires datetime NULL,
        last_login datetime NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY email (email),
        KEY wp_user_id (wp_user_id),
        KEY abteilung_id (abteilung_id)
    ) $c;");

    dbDelta("CREATE TABLE " . tsvp_t('sessions') . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        benutzer_id bigint(20) unsigned NOT NULL,
        token_hash varchar(64) NOT NULL,
        expires datetime NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY token_hash (token_hash)
    ) $c;");

    dbDelta("CREATE TABLE " . tsvp_t('abrechnungen') . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        benutzer_id bigint(20) unsigned NOT NULL,
        abteilung_id bigint(20) unsigned NOT NULL DEFAULT 0,
        monat char(7) NOT NULL,
        positionen longtext NOT NULL,
        stunden decimal(8,2) NOT NULL DEFAULT 0,
        stundensatz decimal(8,2) NOT NULL DEFAULT 0,
        betrag decimal(10,2) NOT NULL DEFAULT 0,
        bemerkung text NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'eingereicht',
        kommentar text NOT NULL,
        bearbeitet_von bigint(20) unsigned NOT NULL DEFAULT 0,
        bearbeitet_am datetime NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY benutzer_id (benutzer_id),
        KEY abteilung_id (abteilung_id),
        KEY status (status)
    ) $c;");

    dbDelta("CREATE TABLE " . tsvp_t('antraege') . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        benutzer_id bigint(20) unsigned NOT NULL,
        abteilung_id bigint(20) unsigned NOT NULL DEFAULT 0,
        zweck varchar(255) NOT NULL,
        beschreibung text NOT NULL,
        betrag decimal(10,2) NOT NULL DEFAULT 0,
        empfaenger varchar(190) NOT NULL DEFAULT '',
        iban varchar(40) NOT NULL DEFAULT '',
        beleg_datei varchar(255) NOT NULL DEFAULT '',
        beleg_name varchar(255) NOT NULL DEFAULT '',
        status varchar(20) NOT NULL DEFAULT 'eingereicht',
        kommentar text NOT NULL,
        bearbeitet_von bigint(20) unsigned NOT NULL DEFAULT 0,
        bearbeitet_am datetime NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY benutzer_id (benutzer_id),
        KEY abteilung_id (abteilung_id),
        KEY status (status)
    ) $c;");

    dbDelta("CREATE TABLE " . tsvp_t('dokumente') . " (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        titel varchar(190) NOT NULL,
        beschreibung text NOT NULL,
        kategorie varchar(30) NOT NULL DEFAULT 'sonstiges',
        sichtbarkeit varchar(20) NOT NULL DEFAULT 'alle',
        abteilung_id bigint(20) unsigned NOT NULL DEFAULT 0,
        datei varchar(255) NOT NULL,
        dateiname varchar(255) NOT NULL,
        mime varchar(100) NOT NULL DEFAULT '',
        groesse bigint(20) unsigned NOT NULL DEFAULT 0,
        benutzer_id bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY kategorie (kategorie)
    ) $c;");

    tsvp_private_dir();

    // Portal-Seite anlegen (bestehende Seiten werden nicht überschrieben).
    $page_id = (int) get_option('tsvp_page_id');
    if (!$page_id || !get_post($page_id) || get_post_status($page_id) === 'trash') {
        $page_id = 0;
        $existing = get_page_by_path('vereinsportal');
        if ($existing && has_shortcode($existing->post_content, 'tsv_vereinsportal')) {
            $page_id = $existing->ID;
        } else {
            $page_id = wp_insert_post([
                'post_title'     => 'Vereinsportal',
                'post_name'      => 'vereinsportal',
                'post_content'   => '[tsv_vereinsportal]',
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed',
            ]);
        }
        update_option('tsvp_page_id', (int) $page_id);
    }

    update_option('tsvp_db_version', TSVP_VERSION);
}

# TSV Vereinsportal

Interner Bereich für Vorstand, Abteilungsleitungen und Übungsleiter – als WordPress-Plugin.

## Installation
1. WordPress → Plugins → Installieren → Plugin hochladen → `tsv-vereinsportal.zip` → Aktivieren.
2. Das Plugin legt automatisch die Seite **Vereinsportal** an (Shortcode `[tsv_vereinsportal]`).
   Gibt es unter `/vereinsportal` schon eine andere Seite, bekommt die neue Seite eine eigene Adresse (z. B. `/vereinsportal-2`) – dann einfach umbenennen.
3. Als WordPress-Admin die Portal-Seite öffnen: Du bist automatisch als **Vorstand** drin.

## Als App nutzen
Die Portal-Seite erscheint ohne Kopfzeile, Menü und Fußzeile der Homepage – bildschirmfüllend wie eine eigene App.
Auf dem Handy lässt sie sich auf den Startbildschirm legen:
- **iPhone (Safari):** Teilen-Symbol → „Zum Home-Bildschirm“
- **Android (Chrome):** Menü ⋮ → „App installieren“ bzw. „Zum Startbildschirm hinzufügen“

## Erste Schritte
1. **Verwaltung → Abteilungen**: Abteilungen anlegen.
2. **Verwaltung → Personen**: Vorstand, Abteilungsleitungen und Übungsleiter anlegen (mit Stundensatz).
   Sie bekommen eine E-Mail mit einem Link, um ihr Passwort festzulegen (7 Tage gültig).
3. Abteilungsleitungen können selbst Übungsleiter für ihre Abteilung anlegen.

## Rollen
| | Übungsleitung | Abteilungsleitung | Vorstand |
|---|---|---|---|
| Eigene Abrechnungen einreichen | ✓ | ✓ | ✓ |
| Abrechnungen freigeben | – | eigene Abteilung (nicht die eigene) | alle (nicht die eigene) |
| Als ausgezahlt markieren | – | – | ✓ |
| Auszahlungsanträge stellen | – | ✓ | ✓ |
| Auszahlungsanträge genehmigen | – | – | ✓ |
| Protokolle sehen & hochladen | – | ✓ | ✓ |
| Personen verwalten | – | Übungsleiter der Abteilung | alle |

## Dokumente
Nur Protokolle der **Jahreshauptversammlung** und der **Vereinsratssitzungen** (das sind die beiden Kategorien). Sichtbar ausschließlich für Vorstand und Abteilungsleitungen – Übungsleiter sehen den Bereich nicht.

## Ablauf
- **Abrechnung**: Eingereicht → Freigegeben (Abteilungsleitung/Vorstand) → Ausgezahlt (Vorstand). Ablehnung nur mit Begründung.
- **Auszahlungsantrag**: Eingereicht → Genehmigt (Vorstand) → Ausgezahlt.
- Bei jedem Schritt gehen E-Mails an die zuständigen Personen.

## Wichtig
- **E-Mail-Versand**: Einladungen laufen über `wp_mail`. Kommen keine Mails an, ein SMTP-Plugin einrichten (z. B. mit dem Vereinspostfach).
- **Caching**: Falls ein Cache-Plugin läuft, die Seite `/vereinsportal` vom Caching ausnehmen.
- **Dateien** liegen in `wp-content/uploads/tsvp-privat/` mit zufälligen Namen und werden nur über das Portal (mit Rechteprüfung) ausgeliefert. Auf Apache sperrt eine `.htaccess` den Direktzugriff; auf nginx sollte der Ordner zusätzlich gesperrt werden.
- **Farben** anpassen: oben in `assets/portal.css` die Variablen `--tsvp-accent` usw. ändern.

## Updates über GitHub
1. Den **Inhalt** dieses Ordners in ein GitHub-Repository legen (die Datei `tsv-vereinsportal.php` muss direkt im Hauptverzeichnis liegen), Branch `main`.
2. WordPress → Einstellungen → Vereinsportal: Repository-URL eintragen (bei privatem Repository zusätzlich einen Token mit „Contents: Read-only“).
3. Für ein Update: Versionsnummer in `tsv-vereinsportal.php` erhöhen (Kopfzeile `Version:` und `TSVP_VERSION`), pushen – WordPress zeigt das Update dann unter *Plugins* an.

Der Updater nutzt die Bibliothek [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) (MIT-Lizenz) in `lib/`.

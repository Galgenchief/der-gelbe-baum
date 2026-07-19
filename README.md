# Der Gelbe Baum

Community-Karte für Hofläden, Direktvermarkter, Selbsternte und öffentliche Naturquellen.

## Aufbau

- `index.html` – die App (Karte, Formulare, alles was im Browser läuft)
- `api.php` – Backend-API (verarbeitet Anfragen, spricht mit der Datenbank)
- `db.php` – Datenbankverbindung
- `config.php` – echte Zugangsdaten (wird lokal erstellt, **nicht** in Git)
- `config.example.php` – Vorlage für `config.php`
- `schema.sql` – Datenbankstruktur, einmalig in phpMyAdmin ausführen
- `uploads/` – hochgeladene Fotos

## Deployment (Strato)

1. MySQL-Datenbank im Strato-Kundenpanel anlegen.
2. `schema.sql` in phpMyAdmin ausführen.
3. `config.example.php` kopieren, in `config.php` umbenennen, mit echten Zugangsdaten füllen.
4. Alle Dateien (inkl. `config.php` und `uploads/`) per FTP auf den Webspace laden.
5. App unter `https://deine-domain.de/` aufrufen.

Details siehe Chat-Verlauf mit Claude – die Schritte werden dort einzeln durchgegangen.

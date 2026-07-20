<?php
// Vorlage für config.php.
// Kopie dieser Datei als "config.php" anlegen und mit den echten Strato-Zugangsdaten
// füllen. config.php wird NICHT in Git eingecheckt (steht in .gitignore).

define('DB_HOST', 'localhost');
define('DB_NAME', 'DEIN_DATENBANKNAME');
define('DB_USER', 'DEIN_DB_USER');
define('DB_PASS', 'DEIN_DB_PASSWORT');

// Absender-Adresse für Bestell-Benachrichtigungen per E-Mail
define('MAIL_FROM', 'noreply@deine-domain.de');

// Empfänger-Adresse für Feedback aus der App
define('FEEDBACK_TO', 'business@winkler-online.com');

// Geheimer Schlüssel für backup.php – nur wer diesen Key in der URL mitschickt
// (?key=...), kann ein Backup auslösen. Langer Zufallswert, nirgendwo veröffentlichen.
define('BACKUP_KEY', 'DEIN_ZUFAELLIGER_GEHEIMER_SCHLUESSEL');

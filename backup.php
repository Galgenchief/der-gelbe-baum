<?php
// Automatisches Datenbank-Backup: per Cronjob (Strato-Kundenmenü) periodisch aufrufen,
// z. B. https://kartenfaktur.de/backup.php?key=DEIN_BACKUP_KEY
// Verschickt einen vollständigen SQL-Dump per Mail an FEEDBACK_TO und behält zusätzlich
// die letzten 8 Kopien lokal im Ordner backups/ (per .htaccess von außen nicht erreichbar).
require_once __DIR__ . '/db.php';

if (!defined('BACKUP_KEY') || ($_GET['key'] ?? '') !== BACKUP_KEY) {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

$sql = "-- Der Gelbe Baum – Datenbank-Backup\n-- Erstellt: " . date('Y-m-d H:i:s') . "\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
    $sql .= "DROP TABLE IF EXISTS `$table`;\n" . $create['Create Table'] . ";\n\n";

    $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll();
    foreach ($rows as $row) {
        $cols = array_map(fn($c) => "`$c`", array_keys($row));
        $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), array_values($row));
        $sql .= "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
    }
    $sql .= "\n";
}
$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

$filename = 'gelbebaum_backup_' . date('Y-m-d_His') . '.sql';

// Lokale Kopie als zweite Absicherung, nur die letzten 8 behalten
$dir = __DIR__ . '/backups';
if (!is_dir($dir)) mkdir($dir, 0755, true);
file_put_contents($dir . '/' . $filename, $sql);
$existing = glob($dir . '/gelbebaum_backup_*.sql');
sort($existing);
while (count($existing) > 8) {
    unlink(array_shift($existing));
}

// Per Mail verschicken, damit die Sicherung auch außerhalb des Servers liegt
$boundary = md5(uniqid());
$headers = "From: " . MAIL_FROM . "\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

$body = "--$boundary\r\n";
$body .= "Content-Type: text/plain; charset=utf-8\r\n\r\n";
$body .= "Automatisches Datenbank-Backup von Der Gelbe Baum (" . count($tables) . " Tabellen).\r\n\r\n";
$body .= "--$boundary\r\n";
$body .= "Content-Type: application/sql; name=\"$filename\"\r\n";
$body .= "Content-Transfer-Encoding: base64\r\n";
$body .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
$body .= chunk_split(base64_encode($sql));
$body .= "--$boundary--";

$mailOk = @mail(FEEDBACK_TO, 'Backup: Der Gelbe Baum – ' . date('Y-m-d'), $body, $headers);

header('Content-Type: text/plain; charset=utf-8');
echo $mailOk
    ? "Backup erstellt (" . count($tables) . " Tabellen) und per Mail verschickt.\n"
    : "Backup lokal erstellt, Mail-Versand ist fehlgeschlagen.\n";

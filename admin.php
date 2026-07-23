<?php
// Betreiber-Übersicht: Anzahl Nutzer & neue Meldungen auf einen Blick.
// Aufruf: https://kartenfaktur.de/admin.php?key=DEIN_BACKUP_KEY
// Nutzt denselben Schlüssel wie backup.php, kein eigenes Login nötig.
require_once __DIR__ . '/db.php';

if (!defined('BACKUP_KEY') || ($_GET['key'] ?? '') !== BACKUP_KEY) {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();

function countSince(PDO $pdo, string $table, string $interval): int {
    $stmt = $pdo->query("SELECT COUNT(*) FROM `$table` WHERE created_at >= NOW() - INTERVAL $interval");
    return (int) $stmt->fetchColumn();
}

$totalPlaces  = (int) $pdo->query('SELECT COUNT(*) FROM places')->fetchColumn();
$totalPublic  = (int) $pdo->query("SELECT COUNT(*) FROM places WHERE access = 'public'")->fetchColumn();
$totalPrivate = $totalPlaces - $totalPublic;
$totalPlayers = (int) $pdo->query('SELECT COUNT(*) FROM players')->fetchColumn();

$placesToday  = countSince($pdo, 'places', '1 DAY');
$places7d     = countSince($pdo, 'places', '7 DAY');
$places30d    = countSince($pdo, 'places', '30 DAY');
$players7d    = countSince($pdo, 'players', '7 DAY');
$players30d   = countSince($pdo, 'players', '30 DAY');

$recent = $pdo->query(
    'SELECT name, category, access, created_at FROM places ORDER BY created_at DESC LIMIT 25'
)->fetchAll();

$CAT_LABELS = [
    'fruittrees' => 'Obstbäume', 'nuts' => 'Nüsse', 'herbs' => 'Wildkräuter',
    'berries' => 'Beerensträucher', 'mushroom' => 'Pilzfund', 'flowerfield' => 'Blumenfeld',
    'eggs' => 'Eier', 'honey' => 'Honig', 'fish' => 'Fisch', 'meat' => 'Fleisch',
    'drinks' => 'Getränke', 'farmshop' => 'Hofladen', 'market' => 'Marktstand',
    'homerestaurant' => 'Home Restaurant', 'other' => 'Sonstiges',
];

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Übersicht – Der Gelbe Baum</title>
<style>
  body { font-family: -apple-system, sans-serif; background: #3a5a40; color: #222; margin: 0; padding: 16px; }
  h1 { color: white; font-size: 20px; margin: 4px 0 16px; }
  .cards { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
  .card { background: white; border-radius: 10px; padding: 14px 18px; min-width: 140px; flex: 1; }
  .card .num { font-size: 28px; font-weight: 700; color: #3a5a40; }
  .card .label { font-size: 13px; color: #666; margin-top: 2px; }
  table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; }
  th, td { text-align: left; padding: 8px 10px; font-size: 14px; border-bottom: 1px solid #eee; }
  th { background: #f2f2f2; }
  .badge { display: inline-block; font-size: 11px; padding: 2px 6px; border-radius: 999px; }
  .badge.public { background: #d7ecd9; color: #2f6b34; }
  .badge.private { background: #f0e2c8; color: #8a6a1f; }
  .section-title { color: white; font-size: 15px; margin: 20px 0 8px; }
</style>
</head>
<body>
  <h1>🌳 Betreiber-Übersicht</h1>

  <div class="cards">
    <div class="card"><div class="num"><?= $totalPlaces ?></div><div class="label">Orte gesamt (<?= $totalPublic ?> öffentlich, <?= $totalPrivate ?> privat)</div></div>
    <div class="card"><div class="num"><?= $totalPlayers ?></div><div class="label">Nutzer gesamt (anonyme Geräte-Codes)</div></div>
  </div>

  <div class="cards">
    <div class="card"><div class="num"><?= $placesToday ?></div><div class="label">Neue Meldungen heute</div></div>
    <div class="card"><div class="num"><?= $places7d ?></div><div class="label">Neue Meldungen (7 Tage)</div></div>
    <div class="card"><div class="num"><?= $places30d ?></div><div class="label">Neue Meldungen (30 Tage)</div></div>
  </div>

  <div class="cards">
    <div class="card"><div class="num"><?= $players7d ?></div><div class="label">Neue Nutzer (7 Tage)</div></div>
    <div class="card"><div class="num"><?= $players30d ?></div><div class="label">Neue Nutzer (30 Tage)</div></div>
  </div>

  <div class="section-title">Letzte 25 Meldungen</div>
  <table>
    <tr><th>Name</th><th>Kategorie</th><th>Status</th><th>Wann</th></tr>
    <?php foreach ($recent as $r): ?>
    <tr>
      <td><?= h($r['name']) ?></td>
      <td><?= h($CAT_LABELS[$r['category']] ?? $r['category']) ?></td>
      <td><span class="badge <?= h($r['access']) ?>"><?= $r['access'] === 'public' ? 'Öffentlich' : 'Privat' ?></span></td>
      <td><?= h($r['created_at']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>

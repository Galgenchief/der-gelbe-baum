<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

const CATEGORIES = [
    'fruittrees', 'nuts', 'herbs', 'berries', 'mushroom', 'flowerfield',
    'eggs', 'honey', 'fish', 'meat', 'farmshop', 'market',
    'homerestaurant', 'other',
];

const SUBCATEGORIES = [
    'fruittrees' => ['apple', 'pear', 'cherry', 'plum', 'mirabelle', 'quince', 'apricot', 'peach', 'other'],
    'berries' => ['raspberry', 'currant', 'gooseberry', 'blackberry', 'elderberry', 'blueberry', 'seabuckthorn', 'other'],
    'nuts' => ['walnut', 'hazelnut', 'chestnut', 'other'],
    'mushroom' => ['porcini', 'chanterelle', 'baybolete', 'champignon', 'parasol', 'other'],
    'farmshop' => ['eggs', 'milk', 'yogurt', 'cheese', 'butter', 'cream', 'quark', 'icecream', 'honey', 'beef', 'pork', 'lamb', 'game', 'poultry', 'meat', 'jam', 'juice', 'vegetables', 'other'],
    'market' => ['eggs', 'dairy', 'fruit', 'vegetables', 'honey', 'baked', 'flowers', 'meat', 'cheese', 'jam', 'other'],
    'flowerfield' => ['sunflower', 'tulip', 'dahlia', 'lavender', 'rose', 'peony', 'marigold', 'other'],
    'herbs' => ['wildgarlic', 'nettle', 'dandelion', 'groundelder', 'yarrow', 'plantain', 'sorrel', 'other'],
    'eggs' => ['chicken', 'quail', 'duck', 'goose', 'other'],
    'honey' => ['blossom', 'forest', 'acacia', 'linden', 'other'],
    'fish' => ['trout', 'carp', 'smoked', 'other'],
    'meat' => ['beef', 'pork', 'lamb', 'game', 'poultry', 'other'],
    'homerestaurant' => ['international', 'italian', 'asian', 'german', 'vegetarian', 'other'],
];

function validSubcategories(string $category, string $json): string {
    if (!isset(SUBCATEGORIES[$category])) return '[]';
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) return '[]';
    $valid = array_values(array_intersect($decoded, SUBCATEGORIES[$category]));
    return json_encode($valid);
}

function validHours(string $json): string {
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) return '{"always":false,"days":{}}';
    return json_encode($decoded);
}

function respond($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function fail(string $message, int $code = 400): void {
    respond(['error' => $message], $code);
}

function jsonInput(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function ownerToken(): string {
    return bin2hex(random_bytes(16));
}

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'list':
        $stmt = db()->query('
            SELECT p.*,
                   COALESCE(AVG(r.stars), 0) AS avg_rating,
                   COUNT(DISTINCT r.id) AS rating_count
            FROM places p
            LEFT JOIN ratings r ON r.place_id = p.id
            GROUP BY p.id
            ORDER BY p.created_at DESC
        ');
        $places = $stmt->fetchAll();
        foreach ($places as &$p) {
            unset($p['owner_token']);
            $p['avg_rating'] = round((float)$p['avg_rating'], 1);
            $p['lat'] = (float)$p['lat'];
            $p['lng'] = (float)$p['lng'];
        }
        respond($places);
        break;

    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) fail('id fehlt');

        $stmt = db()->prepare('SELECT * FROM places WHERE id = ?');
        $stmt->execute([$id]);
        $place = $stmt->fetch();
        if (!$place) fail('Ort nicht gefunden', 404);
        unset($place['owner_token']);
        $place['lat'] = (float)$place['lat'];
        $place['lng'] = (float)$place['lng'];

        $stmt = db()->prepare('SELECT stars FROM ratings WHERE place_id = ?');
        $stmt->execute([$id]);
        $stars = array_column($stmt->fetchAll(), 'stars');
        $place['avg_rating'] = count($stars) ? round(array_sum($stars) / count($stars), 1) : 0;
        $place['rating_count'] = count($stars);

        $stmt = db()->prepare('SELECT id, author, text, created_at FROM comments WHERE place_id = ? ORDER BY created_at DESC');
        $stmt->execute([$id]);
        $place['comments'] = $stmt->fetchAll();

        $stmt = db()->prepare('SELECT id, name, price FROM products WHERE place_id = ?');
        $stmt->execute([$id]);
        $place['products'] = $stmt->fetchAll();

        respond($place);
        break;

    case 'create':
        $in = jsonInput();
        $name = trim((string)($in['name'] ?? ''));
        $category = (string)($in['category'] ?? '');
        $lat = $in['lat'] ?? null;
        $lng = $in['lng'] ?? null;

        if ($name === '') fail('Name fehlt');
        if (!in_array($category, CATEGORIES, true)) fail('Ungültige Kategorie');
        if (!is_numeric($lat) || !is_numeric($lng)) fail('Koordinaten fehlen');

        $access = ($in['access'] ?? 'private') === 'public' ? 'public' : 'private';
        $orderable = !empty($in['orderable']) ? 1 : 0;
        if ($orderable && trim((string)($in['contact_email'] ?? '')) === '') {
            fail('Für Bestellfunktion wird eine Kontakt-E-Mail benötigt');
        }

        $token = ownerToken();
        $subcategory = validSubcategories($category, (string)($in['subcategory'] ?? ''));
        $hours = validHours((string)($in['hours'] ?? ''));

        $stmt = db()->prepare('
            INSERT INTO places
                (name, category, subcategory, access, description, season, hours, price, free_harvest, honesty_box,
                 orderable, contact_email, contact_phone, lat, lng, photo, owner_token)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $name,
            $category,
            $subcategory,
            $access,
            (string)($in['description'] ?? ''),
            (string)($in['season'] ?? ''),
            $hours,
            (string)($in['price'] ?? ''),
            !empty($in['free_harvest']) ? 1 : 0,
            !empty($in['honesty_box']) ? 1 : 0,
            $orderable,
            (string)($in['contact_email'] ?? ''),
            (string)($in['contact_phone'] ?? ''),
            (float)$lat,
            (float)$lng,
            (string)($in['photo'] ?? ''),
            $token,
        ]);

        $id = (int)db()->lastInsertId();

        if (!empty($in['products']) && is_array($in['products'])) {
            $ins = db()->prepare('INSERT INTO products (place_id, name, price) VALUES (?, ?, ?)');
            foreach ($in['products'] as $prod) {
                $pname = trim((string)($prod['name'] ?? ''));
                if ($pname === '') continue;
                $ins->execute([$id, $pname, (string)($prod['price'] ?? '')]);
            }
        }

        respond(['id' => $id, 'owner_token' => $token]);
        break;

    case 'update':
        $in = jsonInput();
        $id = (int)($in['id'] ?? 0);
        if (!$id) fail('id fehlt');

        $stmt = db()->prepare('SELECT id FROM places WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) fail('Ort nicht gefunden', 404);

        $name = trim((string)($in['name'] ?? ''));
        $category = (string)($in['category'] ?? '');
        if ($name === '') fail('Name fehlt');
        if (!in_array($category, CATEGORIES, true)) fail('Ungültige Kategorie');

        $access = ($in['access'] ?? 'private') === 'public' ? 'public' : 'private';
        $orderable = !empty($in['orderable']) ? 1 : 0;
        if ($orderable && trim((string)($in['contact_email'] ?? '')) === '') {
            fail('Für Bestellfunktion wird eine Kontakt-E-Mail benötigt');
        }
        $subcategory = validSubcategories($category, (string)($in['subcategory'] ?? ''));
        $hours = validHours((string)($in['hours'] ?? ''));

        $stmt = db()->prepare('
            UPDATE places SET
                name = ?, category = ?, subcategory = ?, access = ?, description = ?, season = ?, hours = ?, price = ?,
                free_harvest = ?, honesty_box = ?, orderable = ?, contact_email = ?, contact_phone = ?,
                photo = COALESCE(NULLIF(?, \'\'), photo)
            WHERE id = ?
        ');
        $stmt->execute([
            $name,
            $category,
            $subcategory,
            $access,
            (string)($in['description'] ?? ''),
            (string)($in['season'] ?? ''),
            $hours,
            (string)($in['price'] ?? ''),
            !empty($in['free_harvest']) ? 1 : 0,
            !empty($in['honesty_box']) ? 1 : 0,
            $orderable,
            (string)($in['contact_email'] ?? ''),
            (string)($in['contact_phone'] ?? ''),
            (string)($in['photo'] ?? ''),
            $id,
        ]);

        if (isset($in['products']) && is_array($in['products'])) {
            db()->prepare('DELETE FROM products WHERE place_id = ?')->execute([$id]);
            $ins = db()->prepare('INSERT INTO products (place_id, name, price) VALUES (?, ?, ?)');
            foreach ($in['products'] as $prod) {
                $pname = trim((string)($prod['name'] ?? ''));
                if ($pname === '') continue;
                $ins->execute([$id, $pname, (string)($prod['price'] ?? '')]);
            }
        }

        respond(['ok' => true]);
        break;

    case 'delete':
        $in = jsonInput();
        $id = (int)($in['id'] ?? 0);
        $token = (string)($in['owner_token'] ?? '');
        if (!$id || $token === '') fail('id oder owner_token fehlt');

        $stmt = db()->prepare('SELECT owner_token, photo FROM places WHERE id = ?');
        $stmt->execute([$id]);
        $place = $stmt->fetch();
        if (!$place) fail('Ort nicht gefunden', 404);
        if (!hash_equals($place['owner_token'], $token)) fail('Nicht berechtigt', 403);

        db()->prepare('DELETE FROM places WHERE id = ?')->execute([$id]);

        if ($place['photo'] !== '' && is_file(__DIR__ . '/' . $place['photo'])) {
            @unlink(__DIR__ . '/' . $place['photo']);
        }

        respond(['ok' => true]);
        break;

    case 'rate':
        $in = jsonInput();
        $placeId = (int)($in['place_id'] ?? 0);
        $stars = (int)($in['stars'] ?? 0);
        if (!$placeId || $stars < 1 || $stars > 5) fail('Ungültige Bewertung');

        $stmt = db()->prepare('SELECT id FROM places WHERE id = ?');
        $stmt->execute([$placeId]);
        if (!$stmt->fetch()) fail('Ort nicht gefunden', 404);

        db()->prepare('INSERT INTO ratings (place_id, stars) VALUES (?, ?)')->execute([$placeId, $stars]);
        respond(['ok' => true]);
        break;

    case 'comment_add':
        $in = jsonInput();
        $placeId = (int)($in['place_id'] ?? 0);
        $text = trim((string)($in['text'] ?? ''));
        $author = trim((string)($in['author'] ?? '')) ?: 'Anonym';
        if (!$placeId || $text === '') fail('Text fehlt');

        $stmt = db()->prepare('SELECT id FROM places WHERE id = ?');
        $stmt->execute([$placeId]);
        if (!$stmt->fetch()) fail('Ort nicht gefunden', 404);

        db()->prepare('INSERT INTO comments (place_id, author, text) VALUES (?, ?, ?)')
            ->execute([$placeId, mb_substr($author, 0, 100), mb_substr($text, 0, 2000)]);
        respond(['ok' => true]);
        break;

    case 'upload_photo':
        if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            fail('Kein Foto empfangen');
        }
        $file = $_FILES['photo'];
        if ($file['size'] > 5 * 1024 * 1024) fail('Foto zu groß (max. 5 MB)');

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $extByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extByMime[$mime])) fail('Nur JPG, PNG oder WEBP erlaubt');

        $filename = bin2hex(random_bytes(16)) . '.' . $extByMime[$mime];
        $dest = __DIR__ . '/uploads/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) fail('Speichern fehlgeschlagen', 500);

        respond(['photo' => 'uploads/' . $filename]);
        break;

    case 'order_create':
        $in = jsonInput();
        $placeId = (int)($in['place_id'] ?? 0);
        $customerName = trim((string)($in['customer_name'] ?? ''));
        $customerContact = trim((string)($in['customer_contact'] ?? ''));
        $items = $in['items'] ?? [];
        $note = trim((string)($in['note'] ?? ''));

        if (!$placeId || $customerName === '' || $customerContact === '' || !is_array($items) || !count($items)) {
            fail('Bitte Name, Kontakt und mindestens ein Produkt angeben');
        }

        $stmt = db()->prepare('SELECT name, contact_email, orderable FROM places WHERE id = ?');
        $stmt->execute([$placeId]);
        $place = $stmt->fetch();
        if (!$place) fail('Ort nicht gefunden', 404);
        if (!$place['orderable']) fail('Dieser Ort bietet keine Bestellfunktion an');

        $itemsJson = json_encode(array_map(fn($it) => [
            'name' => (string)($it['name'] ?? ''),
            'qty' => (string)($it['qty'] ?? ''),
        ], $items));

        db()->prepare('
            INSERT INTO orders (place_id, customer_name, customer_contact, items, note)
            VALUES (?, ?, ?, ?, ?)
        ')->execute([$placeId, mb_substr($customerName, 0, 255), mb_substr($customerContact, 0, 255), $itemsJson, $note]);

        if ($place['contact_email'] !== '' && filter_var($place['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $lines = array_map(fn($it) => '- ' . $it['name'] . ' (' . $it['qty'] . ')', json_decode($itemsJson, true));
            $body = "Neue Bestellanfrage über Der Gelbe Baum für \"{$place['name']}\":\n\n"
                . "Von: {$customerName}\n"
                . "Kontakt: {$customerContact}\n\n"
                . "Produkte:\n" . implode("\n", $lines) . "\n\n"
                . ($note !== '' ? "Notiz: {$note}\n\n" : '')
                . "Bitte direkt beim Kunden melden, um Zahlung/Abholung vor Ort abzustimmen.";
            @mail(
                $place['contact_email'],
                'Neue Bestellanfrage: ' . $place['name'],
                $body,
                'From: ' . MAIL_FROM
            );
        }

        respond(['ok' => true]);
        break;

    case 'orders_list':
        $in = jsonInput();
        $placeId = (int)($in['place_id'] ?? 0);
        $token = (string)($in['owner_token'] ?? '');
        if (!$placeId || $token === '') fail('place_id oder owner_token fehlt');

        $stmt = db()->prepare('SELECT owner_token FROM places WHERE id = ?');
        $stmt->execute([$placeId]);
        $place = $stmt->fetch();
        if (!$place) fail('Ort nicht gefunden', 404);
        if (!hash_equals($place['owner_token'], $token)) fail('Nicht berechtigt', 403);

        $stmt = db()->prepare('SELECT * FROM orders WHERE place_id = ? ORDER BY created_at DESC');
        $stmt->execute([$placeId]);
        $orders = $stmt->fetchAll();
        foreach ($orders as &$o) {
            $o['items'] = json_decode($o['items'], true);
        }
        respond($orders);
        break;

    case 'feedback_send':
        $in = jsonInput();
        $message = trim((string)($in['message'] ?? ''));
        $contact = trim((string)($in['contact'] ?? ''));
        $type = ($in['type'] ?? 'feedback') === 'feature_request' ? 'feature_request' : 'feedback';
        if ($message === '') fail('Bitte eine Nachricht eingeben');

        db()->prepare('INSERT INTO feedback (message, contact, type) VALUES (?, ?, ?)')
            ->execute([mb_substr($message, 0, 5000), mb_substr($contact, 0, 255), $type]);

        $subject = $type === 'feature_request' ? 'Funktionswunsch: Der Gelbe Baum' : 'Neues Feedback: Der Gelbe Baum';
        $body = "Neue Nachricht über Der Gelbe Baum (" . ($type === 'feature_request' ? 'Funktionswunsch' : 'Feedback') . "):\n\n{$message}\n\n"
            . ($contact !== '' ? "Kontakt: {$contact}\n" : "Kontakt: (nicht angegeben)\n");
        @mail(FEEDBACK_TO, $subject, $body, 'From: ' . MAIL_FROM);

        respond(['ok' => true]);
        break;

    default:
        fail('Unbekannte Aktion', 404);
}

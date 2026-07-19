# Der Gelbe Baum – Projektstand

Stand: 19.07.2026

## Was die App ist

Community-Karte für Direktvermarkter, Hofläden, Wochenmärkte und öffentliche Naturquellen
(Streuobstwiesen, Pilzstellen, Beerensträucher etc.) – aufgebaut wie Park4Night, nur für
regionale Lebensmittel statt Stellplätze. Jeder kann Orte eintragen und Beschreibungen von
anderen bearbeiten lassen (Wiki-Prinzip). Dazu ein Rezept-Planer, der Zutaten mit
nahegelegenen Orten abgleicht und eine Google-Maps-Route vorschlägt.

Aktuell: **Testphase**, live unter `kartenfaktur.de` (Übergangsdomain – die eigentliche
Domain `dergelbebaum.de` kommt, sobald verfügbar).

## Tech-Stack

- **Frontend:** eine einzelne `index.html` (Vanilla JS, kein Framework, kein Build-Schritt), Karte via Leaflet/OpenStreetMap
- **Backend:** PHP + MySQL (`api.php`, `db.php`) – gewählt, weil Strato-Hosting kein Node unterstützt
- **Hosting:** Strato Webhosting, Domain `kartenfaktur.de` zeigt auf den Ordner `/gelbebaum/`
- **Versionierung:** Git, Remote auf GitHub (`github.com/Galgenchief/der-gelbe-baum`)
- **Kein Login/Accounts** (bewusste Entscheidung für Phase 1) – Löschen eines Eintrags ist nur
  im Browser möglich, der ihn angelegt hat (owner_token in localStorage), Bearbeiten ist für alle offen

## Wichtige Dateien

| Datei | Zweck |
|---|---|
| `index.html` | Komplettes Frontend (Karte, Formulare, Rezept-Planer, Feedback) |
| `api.php` | Backend-API (alle Datenbankzugriffe, Aktionen über `?action=...`) |
| `db.php` | Datenbankverbindung (liest `config.php`) |
| `config.php` | Echte Zugangsdaten – **lokal vorhanden, nicht in Git** (steht in `.gitignore`) |
| `config.example.php` | Vorlage für `config.php` |
| `schema.sql` | Datenbankstruktur (für Neuinstallationen; auf dem Server schon mehrfach per ALTER TABLE erweitert) |
| `manifest.json`, `icon-*.png`, `apple-touch-icon.png` | App-Icon fürs Homescreen (PWA) |
| `README.md` | Kurzanleitung Deployment |

## Fertig / implementiert

**Karte & Einträge**
- Interaktive Karte, Platzierungsmodus ("Bodenschatz melden"), 15 Kategorien
  (Obstbäume, Nüsse, Wildkräuter, Beerensträucher, Pilzfund, Blumenfeld, Eier, Honig,
  Fisch, Fleisch, Hofladen, Marktstand, Home Restaurant, Sonstiges – "Milch" wurde als
  eigene Kategorie wieder entfernt, da bei Hofladen/Marktstand als Unterkategorie
  abgedeckt). "Fleisch" mit Unterkategorien Rind/Schwein/Lamm/Wild/Geflügel/Sonstiges,
  "Fisch" mit Forelle/Karpfen/Räucherfisch/Sonstiger Fisch – eigenständige Kategorien
  zusätzlich zu den bestehenden Fleisch-Unterkategorien bei Hofladen/Marktstand (für
  spezialisierte Anbieter, die primär Fleisch oder Fisch verkaufen)
- Mehrfachauswahl bei Unterkategorien (z. B. ein Hofladen kann gleichzeitig Milch, Eier
  und Honig anbieten). Bei Hofladen inzwischen auch spezifische Fleisch-Unterkategorien
  (Rind, Schwein, Lamm, Wild, Geflügel, plus generisches "Wurst & Fleisch" als Fallback)
- Wiki-Bearbeitung offen für alle, Löschen nur mit Besitzer-Token
- Öffnungszeiten (Wochentag-genau oder "immer geöffnet"), nur relevant bei Hofladen/Marktstand
- Bewertungen (Sterne) & Kommentare
- Fotos (client-seitig verkleinert vor Upload)
- Bestellfunktion pro Ort (Produktliste + Bestellanfrage per Formular, **keine Online-Zahlung**,
  Anbieter bekommt E-Mail-Benachrichtigung, Zahlung/Abholung vor Ort)
- Kontakt-E-Mail-Feld erscheint automatisch bei "Privat"-Einträgen oder wenn Bestellfunktion aktiv ist

**Rezept-Planer**
- 450 fest hinterlegte Rezepte (je 100 Fleisch/Vegetarisch/Vegan/Süß, 50 Fisch), keine externe
  Rezept-API (Chefkoch o. ä. hat keine offene API). Filter-Chips für alle 5 Kategorien
  (`RECIPE_TYPES` in index.html), Zutaten-Vokabular um ~40 neue Einträge erweitert (pflanzliche
  Proteine, Gemüsesorten, Gewürze) für vegane/vegetarische/Fisch-Rezepte
- Jedes Rezept hat jetzt auch eine kurze Zubereitung (`steps`-Feld), wird in der Detailansicht
  unter "Zubereitung" angezeigt. Für die 450 Rezepte automatisiert per Node-Skript aus
  Gericht-Typ (Name) + den bereits hinterlegten Zutaten erzeugt, nicht händisch getippt
- Toleranz-Auswahl (0–3 Zutaten dürfen separat im Supermarkt gekauft werden)
- Google-Maps-Route mit allen Stopps, `optimize:true` für beste Reihenfolge, **kein**
  fester Startpunkt mehr – Google Maps nutzt automatisch den aktuellen Live-Standort
- Öffnet in neuem Tab/der Maps-App (nicht die eigene Seite ersetzend)

**Einkaufskorb** (neu)
- Orte können per Button in der Detailansicht ("🧺 In den Einkaufskorb") gesammelt werden,
  unabhängig vom Rezept-Planer. Persistiert in `localStorage` (`gb_basket`), übersteht Reload
- Eigener Header-Button mit Live-Zähler, öffnet Liste der gesammelten Orte (Entfernen einzeln
  oder "Korb leeren") mit Entfernungsangabe
- "Route öffnen" nutzt dieselbe Google-Maps-Routing-Logik wie der Rezept-Planer (gemeinsame
  Funktion `openMapsRoute()`, kein Code-Duplikat), `optimize:true`, kein fester Startpunkt

**Gamification / Rangliste** (neu)
- Kein Login nötig: pro Browser wird einmalig ein anonymer Geräte-Code (`player_id`,
  `crypto.randomUUID()`) in `localStorage` (`gb_player_id`) erzeugt und bei jeder neuen Meldung
  serverseitig an den Ort gehängt (`places.creator_player_id`, neue Spalte). **Achtung:**
  bindet Fortschritt ans Gerät/den Browser – Cache löschen oder neues Handy = Fortschritt weg
  (gleiche Einschränkung wie beim bestehenden Lösch-Mechanismus über `owner_token`)
- Punkte: 10 pro öffentlicher Meldung, 5 pro privater – serverseitig berechnet (`?action=ranking`,
  `?action=player_stats`), nicht vom Client vorgegeben
- 7 Abzeichen nach Anzahl Meldungen, heimische Früchte/Gemüse von klein nach groß (bewusst
  keine exotischen Früchte wie im ursprünglichen Vorschlag): 🫐 Heidelbeere (1), 🍒 Kirsche (5),
  🍑 Pfirsich (10), 🍎 Apfel (20), 🍐 Birne (50), 🌽 Mais (100), 🎃 Kürbis (200)
- Frei wählbarer Anzeigename (`players`-Tabelle, verknüpft mit `player_id`), Top-50-Rangliste,
  eigener Header-Button "🏆 Rangliste" mit eigenem Stand + Fortschritt bis zum nächsten Abzeichen
- Kleine Erfolgs-Meldung (Alert) beim Freischalten eines neuen Abzeichens direkt nach dem Melden
- **Bekannte Einschränkung, bewusst in Kauf genommen:** ohne echten Login ließe sich `player_id`
  theoretisch fälschen/wiederverwenden – für ein spaßiges, unverbindliches Ranking (kein Geld/
  keine echten Preise) akzeptabel, gleiches Vertrauensmodell wie beim Rest der Owner-Token-Logik

**Sonstiges**
- Info-Button (i) im Header erklärt Zweck der App, poppt beim ersten Besuch automatisch auf,
  weist auf Testphase hin
- Ein Feedback-Button ("Was fehlt dir?") – separate "Feedback"-Button wieder entfernt
- App-Icon (gelber Baum auf grünem Hintergrund) fürs Homescreen, Safe-Area-Fix für die Notch
- Menü (mobile Sidebar) schließt jetzt auch bei Klick außerhalb

**Datenbestand**
Insgesamt **~250 reale Direktvermarkter/Wochenmärkte** importiert (drei Massen-Importe aus
selbst besorgten PDF-Verzeichnissen/Listen, jeweils per OpenStreetMap/Nominatim geokodiert
und gegen Dubletten geprüft):
1. Landkreis Weißenburg-Gunzenhausen (~150 Orte)
2. Landkreis Fürth / Stadt Fürth / Stadt Nürnberg (Knoblauchsland) / Nürnberger Land (73 Orte)
3. Nachträge Weißenburg / Heideck / Eichstätt (21 Orte)

## Wichtige Entscheidungen

- **Kein Google-Maps-Scraping**: Verstößt gegen deren Nutzungsbedingungen und Google Maps
  listet bei kleinen Höfen ohnehin keine Produktdaten. Stattdessen: echte PDF-Verzeichnisse,
  die der Nutzer selbst besorgt hat, dienen als Datenquelle.
- **Geocoding über Nominatim (OpenStreetMap)**, nicht Google – kostenlos, ToS-konform bei
  moderatem Volumen mit korrektem User-Agent und Rate-Limit (1 Anfrage/Sekunde).
- **PWA statt native App** (iOS/Android) – spart Aufwand, für Phase 1 ausreichend. Native
  Apps sind eine mögliche spätere Ausbaustufe.
- **Bezahlung nur vor Ort**, keine Online-Zahlung – reduziert Komplexität (kein
  Zahlungsanbieter, keine Widerrufsrecht-Pflichten) für den ersten Wurf erheblich.
- **Monetarisierung (Gebühr für Direktvermarkter, Kommunen-Pauschale) bewusst verschoben**,
  bis genug Nutzer da sind.

## Bekannte Bugs, die schon gefixt wurden (zur Erinnerung, falls ähnliches nochmal auftaucht)

- `lat`/`lng` kommen aus MySQL/PDO als String, nicht als Zahl – das hat an einer Stelle
  (`.toFixed()` in der Routen-Logik) eine stille JS-Exception ausgelöst. Behoben, indem
  `api.php` lat/lng jetzt explizit zu `float` castet, bevor sie als JSON rausgehen.
- `window.open()` vs. `window.location.href` bei Routen-Links: `window.open` ist richtig
  (neuer Tab, App bleibt offen) – nicht nochmal auf `location.href` umstellen.

## Offen / mögliche nächste Schritte

- **Domain-Umzug**: sobald `dergelbebaum.de` verfügbar ist, DNS umstellen
  (aktuell `kartenfaktur.de` als Übergangslösung, alte WordPress-Seite von Kartenfaktur
  liegt noch unberührt auf dem Server, nur die Verzeichniszuordnung der Domain zeigt jetzt
  auf `/gelbebaum/`)
- **Accounts/Login** – bewusst nicht in Phase 1, müsste bei Bedarf sauber auf das bestehende
  Owner-Token-System aufgesetzt werden
- **Saison-Benachrichtigungen** (z. B. "Blumenfeld X ist jetzt reif") – noch nicht gebaut
- **Moderationsfunktion** für Kommunen (falsche/veraltete Einträge melden) – noch nicht gebaut
- **Monetarisierung** (Gebühr für Freischaltung, Kommunen-Pauschale) – bewusst verschoben
- **Native Android/iOS-Apps** – bisher nur PWA, native Version könnte später sinnvoll sein
- Weitere Landkreise/Regionen mit echten Direktvermarkter-Daten befüllen, sobald der Nutzer
  entsprechende Quellen (PDF-Verzeichnisse, Broschüren) findet
- Rezept-Zutaten-Matching berücksichtigt bei Hofladen/Marktstand nur die gesetzten
  Unterkategorien, nicht die freie Produktliste – könnte man später verfeinern

## Deployment-Ablauf (zur Erinnerung)

1. Code-Änderung lokal machen, testen, `git commit` + `git push`
2. Betroffene Datei(en) per FileZilla in den Ordner `gelbebaum` auf dem Server hochladen
   (**wichtig:** erst rechts in den `gelbebaum`-Ordner reinklicken, sonst landet die Datei
   im Wurzelverzeichnis, wo auch andere Seiten des Nutzers liegen)
3. Bei Datenbank-Änderungen: SQL-Snippet in phpMyAdmin (Reiter "SQL" oder "Importieren")
   ausführen, **bevor** oder **nachdem** die zugehörige `api.php` hochgeladen wird
4. Bei Unklarheit, ob ein Upload wirklich ankam: `curl -s https://kartenfaktur.de/api.php?action=list`
   prüfen bzw. Datei-Zeitstempel in FileZilla vergleichen (lokal ggf. F5 für aktuelle Anzeige)

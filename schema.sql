-- Der Gelbe Baum – Datenbankschema
-- Einmalig in phpMyAdmin ausführen (Reiter "SQL", einfügen, "OK")

CREATE TABLE IF NOT EXISTS places (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(30) NOT NULL,
  subcategory TEXT NOT NULL,
  access ENUM('public','private') NOT NULL DEFAULT 'private',
  description TEXT,
  season VARCHAR(255),
  hours TEXT,
  price VARCHAR(255),
  free_harvest TINYINT(1) NOT NULL DEFAULT 0,
  honesty_box TINYINT(1) NOT NULL DEFAULT 0,
  orderable TINYINT(1) NOT NULL DEFAULT 0,
  contact_email VARCHAR(255),
  contact_phone VARCHAR(255),
  lat DECIMAL(10,7) NOT NULL,
  lng DECIMAL(10,7) NOT NULL,
  photo VARCHAR(255),
  owner_token CHAR(32) NOT NULL,
  creator_player_id CHAR(36),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ratings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  place_id INT NOT NULL,
  stars TINYINT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  place_id INT NOT NULL,
  author VARCHAR(100) NOT NULL DEFAULT 'Anonym',
  text TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  place_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  price VARCHAR(50),
  FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  place_id INT NOT NULL,
  customer_name VARCHAR(255) NOT NULL,
  customer_contact VARCHAR(255) NOT NULL,
  items TEXT NOT NULL,
  note TEXT,
  status ENUM('neu','bestaetigt','abgeholt','storniert') NOT NULL DEFAULT 'neu',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message TEXT NOT NULL,
  contact VARCHAR(255),
  type VARCHAR(20) NOT NULL DEFAULT 'feedback',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Gamification: anonymer Geräte-Code (kein Login) mit frei wählbarem Anzeigenamen,
-- Punkte/Rang werden aus places.creator_player_id berechnet (5 Pkt privat, 10 Pkt öffentlich)
CREATE TABLE IF NOT EXISTS players (
  id CHAR(36) PRIMARY KEY,
  display_name VARCHAR(30) NOT NULL DEFAULT 'Anonym',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Einfacher IP-basierter Spamschutz (25 Schreibaktionen/Stunde) für Ort melden,
-- Kommentar, Bewertung, Bestellanfrage
CREATE TABLE IF NOT EXISTS rate_limits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_rate_limits_ip_time ON rate_limits (ip_address, created_at);

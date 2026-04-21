CREATE DATABASE IF NOT EXISTS hoshin_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hoshin_app;

CREATE TABLE IF NOT EXISTS boards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS columns_def (
  id INT AUTO_INCREMENT PRIMARY KEY,
  board_id INT NOT NULL,
  title VARCHAR(120) NOT NULL,
  position INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_columns_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
  UNIQUE KEY uq_board_column_position (board_id, position)
);

CREATE TABLE IF NOT EXISTS objectives (
  id INT AUTO_INCREMENT PRIMARY KEY,
  board_id INT NOT NULL,
  parent_id INT NULL,
  title VARCHAR(160) NOT NULL,
  position INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_objectives_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
  CONSTRAINT fk_objectives_parent FOREIGN KEY (parent_id) REFERENCES objectives(id) ON DELETE CASCADE,
  UNIQUE KEY uq_objective_position (board_id, parent_id, position)
);

CREATE TABLE IF NOT EXISTS cards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  board_id INT NOT NULL,
  objective_id INT NOT NULL,
  column_id INT NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NULL,
  position INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cards_board FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
  CONSTRAINT fk_cards_objective FOREIGN KEY (objective_id) REFERENCES objectives(id) ON DELETE CASCADE,
  CONSTRAINT fk_cards_column FOREIGN KEY (column_id) REFERENCES columns_def(id) ON DELETE CASCADE,
  UNIQUE KEY uq_card_position (objective_id, column_id, position)
);

INSERT INTO boards (id, name)
VALUES (1, 'Hoshin Board')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO columns_def (board_id, title, position)
VALUES
  (1, 'Yearly goals', 1),
  (1, 'Understanding the gap', 2),
  (1, 'Improvement A3', 3),
  (1, 'Measure monthly', 4),
  (1, 'Adjust / Problem solving', 5)
ON DUPLICATE KEY UPDATE title = VALUES(title);

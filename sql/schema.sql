CREATE DATABASE IF NOT EXISTS hoshin_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hoshin_app;

CREATE TABLE IF NOT EXISTS boards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL
);

CREATE TABLE IF NOT EXISTS columns_def (
  id INT AUTO_INCREMENT PRIMARY KEY,
  board_id INT NOT NULL,
  title VARCHAR(120) NOT NULL,
  position INT NOT NULL,
  FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
  UNIQUE KEY uq_columns_pos (board_id, position)
);

CREATE TABLE IF NOT EXISTS objectives (
  id INT AUTO_INCREMENT PRIMARY KEY,
  board_id INT NOT NULL,
  parent_id INT NULL,
  title VARCHAR(160) NOT NULL,
  position INT NOT NULL,
  FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES objectives(id) ON DELETE CASCADE,
  UNIQUE KEY uq_objectives_pos (board_id, parent_id, position)
);

CREATE TABLE IF NOT EXISTS cards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  board_id INT NOT NULL,
  objective_id INT NOT NULL,
  column_id INT NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NULL,
  code VARCHAR(50) NULL,
  linked_yearly_code VARCHAR(50) NULL,
  position INT NOT NULL,
  FOREIGN KEY (board_id) REFERENCES boards(id) ON DELETE CASCADE,
  FOREIGN KEY (objective_id) REFERENCES objectives(id) ON DELETE CASCADE,
  FOREIGN KEY (column_id) REFERENCES columns_def(id) ON DELETE CASCADE,
  UNIQUE KEY uq_cards_pos (objective_id, column_id, position),
  UNIQUE KEY uq_yearly_code (board_id, code)
);

INSERT INTO boards (id, name)
VALUES (1, 'Hoshin Board')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO columns_def (board_id, title, position)
VALUES
  (1, 'Yearly goals', 1),
  (1, 'Understanding the gap', 2),
  (1, 'Improvement A3', 3),
  (1, 'Do & Check', 4),
  (1, 'Adjust', 5)
ON DUPLICATE KEY UPDATE title = VALUES(title);

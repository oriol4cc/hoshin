<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function inputJson(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function yearlyColumnId(int $boardId): int
{
    $stmt = db()->prepare('SELECT id FROM columns_def WHERE board_id = :boardId ORDER BY position ASC LIMIT 1');
    $stmt->execute(['boardId' => $boardId]);
    $row = $stmt->fetch();
    if (!$row) throw new RuntimeException('No columns configured');
    return (int)$row['id'];
}

function fetchBoard(int $boardId = 1): array
{
    $pdo = db();

    $cols = $pdo->prepare('SELECT id, title, position FROM columns_def WHERE board_id = :boardId ORDER BY position');
    $cols->execute(['boardId' => $boardId]);

    $objectives = $pdo->prepare('SELECT id, parent_id, title, position FROM objectives WHERE board_id = :boardId ORDER BY parent_id, position, id');
    $objectives->execute(['boardId' => $boardId]);

    $cards = $pdo->prepare('SELECT id, objective_id, column_id, title, description, code, linked_yearly_code, position FROM cards WHERE board_id = :boardId ORDER BY objective_id, column_id, position, id');
    $cards->execute(['boardId' => $boardId]);

    $ycol = yearlyColumnId($boardId);
    $codes = $pdo->prepare('SELECT code FROM cards WHERE board_id = :boardId AND column_id = :columnId AND code IS NOT NULL AND code <> "" ORDER BY code');
    $codes->execute(['boardId' => $boardId, 'columnId' => $ycol]);

    return [
        'columns' => $cols->fetchAll(),
        'objectives' => $objectives->fetchAll(),
        'cards' => $cards->fetchAll(),
        'yearly_codes' => array_map(fn($r) => (string)$r['code'], $codes->fetchAll()),
        'yearly_column_id' => $ycol,
    ];
}

function createObjective(int $boardId, ?int $parentId, string $title): int
{
    $posStmt = db()->prepare('SELECT COALESCE(MAX(position), 0) + 1 n FROM objectives WHERE board_id = :b AND (parent_id <=> :p)');
    $posStmt->execute(['b' => $boardId, 'p' => $parentId]);
    $pos = (int)$posStmt->fetch()['n'];

    $stmt = db()->prepare('INSERT INTO objectives (board_id, parent_id, title, position) VALUES (:b, :p, :t, :pos)');
    $stmt->execute(['b' => $boardId, 'p' => $parentId, 't' => $title, 'pos' => $pos]);
    return (int)db()->lastInsertId();
}

function moveObjective(int $objectiveId, string $direction): void
{
    $pdo = db();
    $curStmt = $pdo->prepare('SELECT id, board_id, parent_id, position FROM objectives WHERE id = :id');
    $curStmt->execute(['id' => $objectiveId]);
    $cur = $curStmt->fetch();
    if (!$cur) return;

    $op = $direction === 'up' ? '<' : '>';
    $sort = $direction === 'up' ? 'DESC' : 'ASC';

    $nbStmt = $pdo->prepare("SELECT id, position FROM objectives WHERE board_id = :b AND (parent_id <=> :p) AND position {$op} :pos ORDER BY position {$sort} LIMIT 1");
    $nbStmt->execute(['b' => $cur['board_id'], 'p' => $cur['parent_id'], 'pos' => $cur['position']]);
    $nb = $nbStmt->fetch();
    if (!$nb) return;

    $swap = $pdo->prepare('UPDATE objectives SET position = :pos WHERE id = :id');
    $pdo->beginTransaction();
    $swap->execute(['pos' => -999999, 'id' => $cur['id']]);
    $swap->execute(['pos' => $cur['position'], 'id' => $nb['id']]);
    $swap->execute(['pos' => $nb['position'], 'id' => $cur['id']]);
    $pdo->commit();
}

function deleteObjective(int $id): void
{
    $stmt = db()->prepare('DELETE FROM objectives WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

function ensureYearlyCodeExists(int $boardId, string $code): void
{
    $stmt = db()->prepare('SELECT id FROM cards WHERE board_id = :b AND column_id = :c AND code = :code LIMIT 1');
    $stmt->execute(['b' => $boardId, 'c' => yearlyColumnId($boardId), 'code' => $code]);
    if (!$stmt->fetch()) throw new InvalidArgumentException('El codi yearly seleccionat no existeix');
}

function validateCard(int $boardId, int $columnId, ?string $code, ?string $linked, ?int $excludeId = null): void
{
    $ycol = yearlyColumnId($boardId);
    if ($columnId === $ycol) {
        if (!$code) throw new InvalidArgumentException('El codi és obligatori per cards yearly');

        $sql = 'SELECT id FROM cards WHERE board_id = :b AND column_id = :c AND code = :code';
        $params = ['b' => $boardId, 'c' => $columnId, 'code' => $code];
        if ($excludeId) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) throw new InvalidArgumentException('Aquest codi yearly ja existeix');
    } else {
        if (!$linked) throw new InvalidArgumentException('Aquesta card ha d\'estar vinculada a un codi yearly');
        ensureYearlyCodeExists($boardId, $linked);
    }
}

function createCard(int $boardId, int $objectiveId, int $columnId, string $title, ?string $description, ?string $code, ?string $linked): int
{
    $description = trim((string)$description);
    $code = trim((string)$code);
    $linked = trim((string)$linked);

    validateCard($boardId, $columnId, $code ?: null, $linked ?: null);

    $posStmt = db()->prepare('SELECT COALESCE(MAX(position), 0) + 1 n FROM cards WHERE objective_id = :o AND column_id = :c');
    $posStmt->execute(['o' => $objectiveId, 'c' => $columnId]);
    $pos = (int)$posStmt->fetch()['n'];

    $stmt = db()->prepare('INSERT INTO cards (board_id, objective_id, column_id, title, description, code, linked_yearly_code, position) VALUES (:b, :o, :c, :t, :d, :code, :linked, :p)');
    $stmt->execute([
        'b' => $boardId,
        'o' => $objectiveId,
        'c' => $columnId,
        't' => $title,
        'd' => $description !== '' ? $description : null,
        'code' => $code !== '' ? $code : null,
        'linked' => $linked !== '' ? $linked : null,
        'p' => $pos,
    ]);

    return (int)db()->lastInsertId();
}

function updateCard(int $id, string $title, ?string $description, ?string $code, ?string $linked): void
{
    $cardStmt = db()->prepare('SELECT id, board_id, column_id FROM cards WHERE id = :id');
    $cardStmt->execute(['id' => $id]);
    $card = $cardStmt->fetch();
    if (!$card) throw new InvalidArgumentException('Card no trobada');

    $description = trim((string)$description);
    $code = trim((string)$code);
    $linked = trim((string)$linked);

    validateCard((int)$card['board_id'], (int)$card['column_id'], $code ?: null, $linked ?: null, $id);

    $stmt = db()->prepare('UPDATE cards SET title = :t, description = :d, code = :code, linked_yearly_code = :linked WHERE id = :id');
    $stmt->execute([
        'id' => $id,
        't' => $title,
        'd' => $description !== '' ? $description : null,
        'code' => $code !== '' ? $code : null,
        'linked' => $linked !== '' ? $linked : null,
    ]);
}

function deleteCard(int $id): void
{
    $stmt = db()->prepare('DELETE FROM cards WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

function moveCard(int $id, int $objectiveId, int $columnId, int $position): void
{
    $pdo = db();

    $cardStmt = $pdo->prepare('SELECT id, board_id, objective_id, column_id, position, code, linked_yearly_code FROM cards WHERE id = :id');
    $cardStmt->execute(['id' => $id]);
    $card = $cardStmt->fetch();
    if (!$card) return;

    validateCard((int)$card['board_id'], $columnId, $card['code'], $card['linked_yearly_code'], $id);

    $pdo->beginTransaction();

    $close = $pdo->prepare('UPDATE cards SET position = position - 1 WHERE objective_id = :o AND column_id = :c AND position > :p');
    $close->execute(['o' => $card['objective_id'], 'c' => $card['column_id'], 'p' => $card['position']]);

    $open = $pdo->prepare('UPDATE cards SET position = position + 1 WHERE objective_id = :o AND column_id = :c AND position >= :p');
    $open->execute(['o' => $objectiveId, 'c' => $columnId, 'p' => $position]);

    $update = $pdo->prepare('UPDATE cards SET objective_id = :o, column_id = :c, position = :p WHERE id = :id');
    $update->execute(['o' => $objectiveId, 'c' => $columnId, 'p' => max(1, $position), 'id' => $id]);

    $pdo->commit();
}

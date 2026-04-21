<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function jsonInput(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function getYearlyColumnId(int $boardId): int
{
    $stmt = db()->prepare('SELECT id FROM columns_def WHERE board_id = :boardId ORDER BY position ASC LIMIT 1');
    $stmt->execute(['boardId' => $boardId]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new RuntimeException('No columns configured for board');
    }

    return (int)$row['id'];
}

function getBoardData(int $boardId = 1): array
{
    $pdo = db();

    $columns = $pdo->prepare('SELECT id, title, position FROM columns_def WHERE board_id = :boardId ORDER BY position ASC');
    $columns->execute(['boardId' => $boardId]);

    $objectivesStmt = $pdo->prepare('SELECT id, parent_id, title, position FROM objectives WHERE board_id = :boardId ORDER BY parent_id ASC, position ASC, id ASC');
    $objectivesStmt->execute(['boardId' => $boardId]);

    $cardsStmt = $pdo->prepare('SELECT id, objective_id, column_id, title, description, code, linked_yearly_code, position FROM cards WHERE board_id = :boardId ORDER BY objective_id ASC, column_id ASC, position ASC');
    $cardsStmt->execute(['boardId' => $boardId]);

    $yearlyColumnId = getYearlyColumnId($boardId);
    $codesStmt = $pdo->prepare('SELECT DISTINCT code FROM cards WHERE board_id = :boardId AND column_id = :columnId AND code IS NOT NULL AND code <> "" ORDER BY code ASC');
    $codesStmt->execute(['boardId' => $boardId, 'columnId' => $yearlyColumnId]);

    return [
        'columns' => $columns->fetchAll(),
        'objectives' => $objectivesStmt->fetchAll(),
        'cards' => $cardsStmt->fetchAll(),
        'yearly_codes' => array_map(static fn(array $r): string => (string)$r['code'], $codesStmt->fetchAll()),
    ];
}

function createObjective(int $boardId, ?int $parentId, string $title): int
{
    $pdo = db();

    $positionStmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_pos FROM objectives WHERE board_id = :boardId AND (parent_id <=> :parentId)');
    $positionStmt->execute(['boardId' => $boardId, 'parentId' => $parentId]);
    $position = (int)$positionStmt->fetch()['next_pos'];

    $insert = $pdo->prepare('INSERT INTO objectives (board_id, parent_id, title, position) VALUES (:boardId, :parentId, :title, :position)');
    $insert->execute([
        'boardId' => $boardId,
        'parentId' => $parentId,
        'title' => $title,
        'position' => $position,
    ]);

    return (int)$pdo->lastInsertId();
}

function deleteObjective(int $objectiveId): void
{
    $stmt = db()->prepare('DELETE FROM objectives WHERE id = :id');
    $stmt->execute(['id' => $objectiveId]);
}

function moveObjective(int $objectiveId, string $direction): void
{
    $pdo = db();
    $currentStmt = $pdo->prepare('SELECT id, board_id, parent_id, position FROM objectives WHERE id = :id');
    $currentStmt->execute(['id' => $objectiveId]);
    $current = $currentStmt->fetch();

    if (!$current) {
        return;
    }

    $operator = $direction === 'up' ? '<' : '>';
    $sort = $direction === 'up' ? 'DESC' : 'ASC';

    $neighborStmt = $pdo->prepare(
        "SELECT id, position FROM objectives
         WHERE board_id = :boardId
           AND (parent_id <=> :parentId)
           AND position {$operator} :position
         ORDER BY position {$sort}
         LIMIT 1"
    );
    $neighborStmt->execute([
        'boardId' => $current['board_id'],
        'parentId' => $current['parent_id'],
        'position' => $current['position'],
    ]);
    $neighbor = $neighborStmt->fetch();

    if (!$neighbor) {
        return;
    }

    $swap = $pdo->prepare('UPDATE objectives SET position = :position WHERE id = :id');
    $pdo->beginTransaction();
    $swap->execute(['position' => -1, 'id' => $current['id']]);
    $swap->execute(['position' => $current['position'], 'id' => $neighbor['id']]);
    $swap->execute(['position' => $neighbor['position'], 'id' => $current['id']]);
    $pdo->commit();
}

function assertYearlyCodeExists(int $boardId, string $code): void
{
    $yearlyColumnId = getYearlyColumnId($boardId);
    $stmt = db()->prepare('SELECT id FROM cards WHERE board_id = :boardId AND column_id = :columnId AND code = :code LIMIT 1');
    $stmt->execute(['boardId' => $boardId, 'columnId' => $yearlyColumnId, 'code' => $code]);

    if (!$stmt->fetch()) {
        throw new InvalidArgumentException('El codi seleccionat no existeix en cap yearly goal');
    }
}

function validateCardData(int $boardId, int $columnId, ?string $code, ?string $linkedYearlyCode, ?int $cardId = null): void
{
    $yearlyColumnId = getYearlyColumnId($boardId);

    if ($columnId === $yearlyColumnId) {
        if (!$code) {
            throw new InvalidArgumentException('Les cards de Yearly goals requereixen codi únic');
        }

        $existsSql = 'SELECT id FROM cards WHERE board_id = :boardId AND column_id = :columnId AND code = :code';
        $params = ['boardId' => $boardId, 'columnId' => $columnId, 'code' => $code];
        if ($cardId) {
            $existsSql .= ' AND id <> :cardId';
            $params['cardId'] = $cardId;
        }
        $existsSql .= ' LIMIT 1';

        $exists = db()->prepare($existsSql);
        $exists->execute($params);
        if ($exists->fetch()) {
            throw new InvalidArgumentException('Aquest codi yearly ja existeix');
        }
    } else {
        if (!$linkedYearlyCode) {
            throw new InvalidArgumentException('Les cards no-yearly han d\'estar vinculades a un codi yearly');
        }
        assertYearlyCodeExists($boardId, $linkedYearlyCode);
    }
}

function createCard(int $boardId, int $objectiveId, int $columnId, string $title, ?string $description, ?string $code, ?string $linkedYearlyCode): int
{
    $pdo = db();

    $code = $code !== null ? trim($code) : null;
    $linkedYearlyCode = $linkedYearlyCode !== null ? trim($linkedYearlyCode) : null;
    $description = $description !== null ? trim($description) : null;

    validateCardData($boardId, $columnId, $code ?: null, $linkedYearlyCode ?: null);

    $nextStmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_pos FROM cards WHERE objective_id = :objectiveId AND column_id = :columnId');
    $nextStmt->execute(['objectiveId' => $objectiveId, 'columnId' => $columnId]);
    $position = (int)$nextStmt->fetch()['next_pos'];

    $stmt = $pdo->prepare('INSERT INTO cards (board_id, objective_id, column_id, title, description, code, linked_yearly_code, position) VALUES (:boardId, :objectiveId, :columnId, :title, :description, :code, :linkedYearlyCode, :position)');
    $stmt->execute([
        'boardId' => $boardId,
        'objectiveId' => $objectiveId,
        'columnId' => $columnId,
        'title' => $title,
        'description' => $description,
        'code' => $code ?: null,
        'linkedYearlyCode' => $linkedYearlyCode ?: null,
        'position' => $position,
    ]);

    return (int)$pdo->lastInsertId();
}

function updateCard(int $cardId, string $title, ?string $description, ?string $code, ?string $linkedYearlyCode): void
{
    $pdo = db();
    $get = $pdo->prepare('SELECT id, board_id, column_id FROM cards WHERE id = :id');
    $get->execute(['id' => $cardId]);
    $card = $get->fetch();

    if (!$card) {
        throw new InvalidArgumentException('Card no trobada');
    }

    $code = $code !== null ? trim($code) : null;
    $linkedYearlyCode = $linkedYearlyCode !== null ? trim($linkedYearlyCode) : null;
    $description = $description !== null ? trim($description) : null;

    validateCardData((int)$card['board_id'], (int)$card['column_id'], $code ?: null, $linkedYearlyCode ?: null, $cardId);

    $stmt = $pdo->prepare('UPDATE cards SET title = :title, description = :description, code = :code, linked_yearly_code = :linkedYearlyCode WHERE id = :id');
    $stmt->execute([
        'id' => $cardId,
        'title' => $title,
        'description' => $description,
        'code' => $code ?: null,
        'linkedYearlyCode' => $linkedYearlyCode ?: null,
    ]);
}

function deleteCard(int $cardId): void
{
    $stmt = db()->prepare('DELETE FROM cards WHERE id = :id');
    $stmt->execute(['id' => $cardId]);
}

function moveCard(int $cardId, int $toObjectiveId, int $toColumnId, int $toPosition): void
{
    $pdo = db();
    $pdo->beginTransaction();

    $cardStmt = $pdo->prepare('SELECT id, board_id, objective_id, column_id, position, code, linked_yearly_code FROM cards WHERE id = :id');
    $cardStmt->execute(['id' => $cardId]);
    $card = $cardStmt->fetch();

    if (!$card) {
        $pdo->rollBack();
        return;
    }

    validateCardData(
        (int)$card['board_id'],
        $toColumnId,
        $card['code'] !== null ? (string)$card['code'] : null,
        $card['linked_yearly_code'] !== null ? (string)$card['linked_yearly_code'] : null,
        $cardId
    );

    $closeGap = $pdo->prepare('UPDATE cards SET position = position - 1 WHERE objective_id = :objectiveId AND column_id = :columnId AND position > :position');
    $closeGap->execute([
        'objectiveId' => $card['objective_id'],
        'columnId' => $card['column_id'],
        'position' => $card['position'],
    ]);

    $openGap = $pdo->prepare('UPDATE cards SET position = position + 1 WHERE objective_id = :objectiveId AND column_id = :columnId AND position >= :position');
    $openGap->execute([
        'objectiveId' => $toObjectiveId,
        'columnId' => $toColumnId,
        'position' => $toPosition,
    ]);

    $yearlyColumnId = getYearlyColumnId((int)$card['board_id']);
    $code = $card['code'];
    $linked = $card['linked_yearly_code'];
    if ($toColumnId === $yearlyColumnId) {
        $linked = null;
    } else {
        $code = null;
        if (!$linked) {
            $pdo->rollBack();
            throw new InvalidArgumentException('Per moure aquesta card, primer selecciona un codi yearly');
        }
    }

    $move = $pdo->prepare('UPDATE cards SET objective_id = :objectiveId, column_id = :columnId, position = :position, code = :code, linked_yearly_code = :linkedYearlyCode WHERE id = :id');
    $move->execute([
        'objectiveId' => $toObjectiveId,
        'columnId' => $toColumnId,
        'position' => $toPosition,
        'code' => $code,
        'linkedYearlyCode' => $linked,
        'id' => $cardId,
    ]);

    $pdo->commit();
}

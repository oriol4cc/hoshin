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

function getBoardData(int $boardId = 1): array
{
    $pdo = db();

    $columns = $pdo->prepare('SELECT id, title, position FROM columns_def WHERE board_id = :boardId ORDER BY position ASC');
    $columns->execute(['boardId' => $boardId]);

    $objectivesStmt = $pdo->prepare('SELECT id, parent_id, title, position FROM objectives WHERE board_id = :boardId ORDER BY parent_id ASC, position ASC, id ASC');
    $objectivesStmt->execute(['boardId' => $boardId]);
    $objectives = $objectivesStmt->fetchAll();

    $cardsStmt = $pdo->prepare('SELECT id, objective_id, column_id, title, description, position FROM cards WHERE board_id = :boardId ORDER BY objective_id ASC, column_id ASC, position ASC');
    $cardsStmt->execute(['boardId' => $boardId]);
    $cards = $cardsStmt->fetchAll();

    return [
        'columns' => $columns->fetchAll(),
        'objectives' => $objectives,
        'cards' => $cards,
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

function createCard(int $boardId, int $objectiveId, int $columnId, string $title): int
{
    $pdo = db();
    $nextStmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_pos FROM cards WHERE objective_id = :objectiveId AND column_id = :columnId');
    $nextStmt->execute(['objectiveId' => $objectiveId, 'columnId' => $columnId]);
    $position = (int)$nextStmt->fetch()['next_pos'];

    $stmt = $pdo->prepare('INSERT INTO cards (board_id, objective_id, column_id, title, position) VALUES (:boardId, :objectiveId, :columnId, :title, :position)');
    $stmt->execute([
        'boardId' => $boardId,
        'objectiveId' => $objectiveId,
        'columnId' => $columnId,
        'title' => $title,
        'position' => $position,
    ]);

    return (int)$pdo->lastInsertId();
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

    $cardStmt = $pdo->prepare('SELECT id, objective_id, column_id, position FROM cards WHERE id = :id');
    $cardStmt->execute(['id' => $cardId]);
    $card = $cardStmt->fetch();

    if (!$card) {
        $pdo->rollBack();
        return;
    }

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

    $move = $pdo->prepare('UPDATE cards SET objective_id = :objectiveId, column_id = :columnId, position = :position WHERE id = :id');
    $move->execute([
        'objectiveId' => $toObjectiveId,
        'columnId' => $toColumnId,
        'position' => $toPosition,
        'id' => $cardId,
    ]);

    $pdo->commit();
}

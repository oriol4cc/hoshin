<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/repository.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'board';

try {
    $payload = inputJson();

    if ($method === 'GET' && $action === 'board') {
        echo json_encode(['ok' => true, 'data' => fetchBoard(1)]);
        exit;
    }

    if ($method === 'POST' && $action === 'objective') {
        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') throw new InvalidArgumentException('Títol obligatori');
        $id = createObjective(1, isset($payload['parent_id']) ? (int)$payload['parent_id'] : null, $title);
        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    if ($method === 'PUT' && $action === 'objective-move') {
        moveObjective((int)$payload['id'], (string)$payload['direction']);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($method === 'DELETE' && $action === 'objective') {
        deleteObjective((int)$payload['id']);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($method === 'POST' && $action === 'card') {
        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') throw new InvalidArgumentException('Títol de card obligatori');

        $id = createCard(
            1,
            (int)$payload['objective_id'],
            (int)$payload['column_id'],
            $title,
            isset($payload['description']) ? (string)$payload['description'] : null,
            isset($payload['code']) ? (string)$payload['code'] : null,
            isset($payload['linked_yearly_code']) ? (string)$payload['linked_yearly_code'] : null
        );

        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    if ($method === 'PUT' && $action === 'card') {
        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') throw new InvalidArgumentException('Títol de card obligatori');

        updateCard(
            (int)$payload['id'],
            $title,
            isset($payload['description']) ? (string)$payload['description'] : null,
            isset($payload['code']) ? (string)$payload['code'] : null,
            isset($payload['linked_yearly_code']) ? (string)$payload['linked_yearly_code'] : null
        );

        echo json_encode(['ok' => true]);
        exit;
    }

    if ($method === 'PUT' && $action === 'card-move') {
        moveCard((int)$payload['id'], (int)$payload['objective_id'], (int)$payload['column_id'], (int)$payload['position']);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($method === 'DELETE' && $action === 'card') {
        deleteCard((int)$payload['id']);
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Ruta no trobada']);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/repository.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'board';

try {
    if ($method === 'GET' && $action === 'board') {
        echo json_encode(['ok' => true, 'data' => getBoardData(1)]);
        exit;
    }

    $input = jsonInput();

    if ($method === 'POST' && $action === 'objective') {
        $title = trim((string)($input['title'] ?? ''));
        $parentId = isset($input['parent_id']) ? (int)$input['parent_id'] : null;
        if ($title === '') {
            throw new InvalidArgumentException('Objective title is required');
        }
        $id = createObjective(1, $parentId ?: null, $title);
        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    if ($method === 'PUT' && $action === 'objective-move') {
        moveObjective((int)$input['id'], (string)$input['direction']);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($method === 'DELETE' && $action === 'objective') {
        deleteObjective((int)$input['id']);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($method === 'POST' && $action === 'card') {
        $title = trim((string)($input['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Card title is required');
        }
        $id = createCard(1, (int)$input['objective_id'], (int)$input['column_id'], $title);
        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    if ($method === 'PUT' && $action === 'card-move') {
        moveCard((int)$input['id'], (int)$input['objective_id'], (int)$input['column_id'], max(1, (int)$input['position']));
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($method === 'DELETE' && $action === 'card') {
        deleteCard((int)$input['id']);
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Endpoint not found']);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

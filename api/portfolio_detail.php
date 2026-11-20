<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    respond(400, ['status' => 'error', 'message' => 'Invalid portfolio ID']);
}

try {
    $stmt = db()->prepare('SELECT * FROM portfolios WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        respond(404, ['status' => 'error', 'message' => 'Portfolio not found']);
    }

    $detailStmt = db()->prepare(
        'SELECT id, file_path, media_type, caption FROM portfolio_details
         WHERE portfolio_id = ? ORDER BY sort_order ASC, id ASC'
    );
    $detailStmt->execute([$id]);
    $details = array_map(fn($d) => [
        'id' => (int) $d['id'],
        'file_path' => $d['file_path'],
        'media_type' => $d['media_type'],
        'caption' => $d['caption'],
    ], $detailStmt->fetchAll());

    respond(200, [
        'status' => 'ok',
        'data' => [
            'id' => (int) $row['id'],
            'category' => $row['category'],
            'title' => $row['title'],
            'media_mode' => $row['media_mode'],
            'main_image' => $row['main_image'],
            'main_video' => $row['main_video'],
            'aspect_ratio' => $row['aspect_ratio'],
            'description' => $row['description'],
            'tech_tags' => splitTags($row['tech_tags']),
            'demo_url' => $row['demo_url'],
            'github_url' => $row['github_url'],
            'created_at' => substr((string) $row['created_at'], 0, 10),
            'details' => $details,
        ],
    ]);
} catch (PDOException $e) {
    respond(500, ['status' => 'error', 'message' => 'Database unavailable']);
}

<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/config.php';

try {
    if ($appConfig['dsn'] === '' || $appConfig['dbUser'] === '') throw new RuntimeException('Database configuration missing.');
    $pdo = new PDO($appConfig['dsn'], $appConfig['dbUser'], $appConfig['dbPassword'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("CREATE TABLE IF NOT EXISTS counter_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page VARCHAR(50) UNIQUE,
        count INT DEFAULT 0
    )");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $event = $data['event'] ?? '';
        $key = '';

        if ($event === 'visit') {
            $page = strtolower((string)($data['page'] ?? ''));
            if (!preg_match('/^[a-z0-9-]{1,40}$/', $page)) {
                http_response_code(400);
                echo json_encode(['error' => 'Página no válida']);
                exit;
            }
            $key = 'visit:' . $page;
            $pdo->prepare("INSERT INTO counter_stats (page, count) VALUES ('total_visits', 1) ON DUPLICATE KEY UPDATE count = count + 1")->execute();
        } elseif ($event === 'request') {
            $development = strtolower((string)($data['development'] ?? ''));
            if (!preg_match('/^[a-z0-9-]{1,40}$/', $development)) {
                http_response_code(400);
                echo json_encode(['error' => 'Desarrollo no válido']);
                exit;
            }
            $key = 'request:' . $development;
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Evento no válido']);
            exit;
        }

        $pdo->prepare("INSERT INTO counter_stats (page, count) VALUES (?, 1) ON DUPLICATE KEY UPDATE count = count + 1")->execute([$key]);
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
        exit;
    }

    $stats = $pdo->query('SELECT page, count FROM counter_stats')->fetchAll(PDO::FETCH_KEY_PAIR);
    $pageVisits = [];
    $requests = [];
    foreach ($stats as $key => $count) {
        if (strpos($key, 'visit:') === 0) {
            $pageVisits[substr($key, 6)] = (int)$count;
        } elseif (strpos($key, 'request:') === 0) {
            $requests[substr($key, 8)] = (int)$count;
        } elseif ($key !== 'total_visits') {
            // Incluir solicitudes históricas guardadas antes de agregar el prefijo request:.
            $requests[$key] = (int)$count;
        }
    }
    arsort($requests);
    $topRequest = $requests ? ['development' => (string)array_key_first($requests), 'count' => (int)reset($requests)] : null;

    $developmentPages = ['solha', 'nexum82', 'lakuun', 'martell', 'marela-life', 'hacienda-ticopo-gutierrez', 'suno', 'monarca', 'anthia', 'marela-celestun', 'velamar'];
    $developmentVisits = array_intersect_key($pageVisits, array_flip($developmentPages));
    arsort($developmentVisits);
    $topVisited = $developmentVisits
        ? ['development' => (string)array_key_first($developmentVisits), 'count' => (int)reset($developmentVisits)]
        : null;

    echo json_encode([
        'visits' => (int)($stats['total_visits'] ?? 0),
        'page_visits' => $pageVisits,
        'requests' => $requests,
        'most_requested_development' => $topRequest,
        'most_visited_development' => $topVisited,
        'note' => 'Las solicitudes miden interés; para identificar ventas se deben registrar las operaciones cerradas.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No fue posible obtener las estadísticas.']);
}
?>

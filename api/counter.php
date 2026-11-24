<?php
header('Content-Type: application/json');

$host = 'localhost';
$db   = 'u194586287_tierrayuc';
$user = 'u194586287_tierrayuc';
$pass = 'Mjsh1965$';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Crear tabla para visitas y clics si no existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS counter_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page VARCHAR(50) UNIQUE,
        count INT DEFAULT 0
    )");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $event = $data['event'] ?? '';
        $development = $data['development'] ?? 'general';

        if ($event === 'visit') {
            // Incrementar visita total
            $pdo->prepare("INSERT INTO counter_stats (page, count) VALUES ('total_visits', 1) ON DUPLICATE KEY UPDATE count = count + 1")->execute();
        } elseif ($event === 'request') {
            // Incrementar contador por desarrollo
            $pdo->prepare("INSERT INTO counter_stats (page, count) VALUES (?, 1) ON DUPLICATE KEY UPDATE count = count + 1")->execute([$development]);
        }
    }

    // Obtener datos actuales
    $stats = $pdo->query("SELECT page, count FROM counter_stats")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Preparar salida para counter.js
    $output = [
        'visits' => (int)($stats['total_visits'] ?? 0),
        'requests' => array_filter($stats, fn($key) => $key !== 'total_visits', ARRAY_FILTER_USE_KEY)
    ];

    echo json_encode($output);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>


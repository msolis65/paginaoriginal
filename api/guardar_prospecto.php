<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

try {
    if ($appConfig['dsn'] === '' || $appConfig['dbUser'] === '') throw new RuntimeException('Configura DB_NAME y DB_USER en api/config.local.php o en el entorno del servidor.');
    $pdo = new PDO($appConfig['dsn'], $appConfig['dbUser'], $appConfig['dbPassword'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    // Crear tabla si no existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS prospectos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cliente VARCHAR(255),
        telefono VARCHAR(50),
        desarrollo VARCHAR(255),
        inicio_proyecto VARCHAR(100),
        enganche VARCHAR(100),
        descripcion TEXT,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS counter_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page VARCHAR(50) UNIQUE,
        count INT DEFAULT 0
    )");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            throw new Exception("Datos no recibidos correctamente.");
        }

        // Si es una visita (event=visit) o un registro de prospecto
        if (isset($input['event']) && $input['event'] === 'visit') {
            $pdo->prepare("INSERT INTO counter_stats (page, count) VALUES ('total_visits', 1) ON DUPLICATE KEY UPDATE count = count + 1")->execute();
        echo json_encode(['status' => 'success']);
            exit;
    }

        $stmt = $pdo->prepare("INSERT INTO prospectos (cliente, telefono, desarrollo, inicio_proyecto, enganche, descripcion) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $input['name'] ?? 'Sin nombre',
            $input['phone'] ?? 'Sin teléfono',
            $input['development'] ?? 'General',
            $input['time'] ?? 'No especificado',
            $input['payment'] ?? 'No especificado',
            $input['message'] ?? ''
        ]);

        // Contar solicitudes por desarrollo con la misma clave que usa counter.php.
        if (!empty($input['development'])) {
            $developmentKey = strtolower(trim((string)$input['development']));
            $asciiDevelopment = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $developmentKey);
            $developmentKey = preg_replace('/[^a-z0-9]+/', '-', $asciiDevelopment ?: $developmentKey);
            $developmentKey = trim(substr($developmentKey, 0, 40), '-');
            if ($developmentKey !== '') {
                $pdo->prepare("INSERT INTO counter_stats (page, count) VALUES (?, 1) ON DUPLICATE KEY UPDATE count = count + 1")
                    ->execute(['request:' . $developmentKey]);
            }
        }

        echo json_encode(['status' => 'success']);
    } else {
        // GET request para obtener las stats actuales
        $stats = $pdo->query("SELECT page, count FROM counter_stats")->fetchAll(PDO::FETCH_KEY_PAIR);
        $output = [
            'visits' => (int)($stats['total_visits'] ?? 0),
            'requests' => array_filter($stats, fn($key) => $key !== 'total_visits', ARRAY_FILTER_USE_KEY)
        ];
        echo json_encode($output);
    }
} catch (Throwable $e) {
    error_log('Prospect save error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar la solicitud. Revisa la configuración de PHP y MySQL.'], JSON_UNESCAPED_UNICODE);
}
?>


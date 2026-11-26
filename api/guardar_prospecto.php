<?php
header('Content-Type: application/json');

$host = 'localhost';
$db   = 'u194586287_tierrayuc';
$user = 'u194586287_tierrayuc';
$pass = 'Mjsh1965$';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
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

        // Incrementar contador de solicitud si viene un desarrollo
        if (!empty($input['development'])) {
             $pdo->prepare("INSERT INTO counter_stats (page, count) VALUES (?, 1) ON DUPLICATE KEY UPDATE count = count + 1")->execute([$input['development']]);
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
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>


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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            throw new Exception("Datos no recibidos correctamente.");
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

        echo json_encode(['status' => 'success']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>


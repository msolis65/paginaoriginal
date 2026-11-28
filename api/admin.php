<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

const ADMIN_PAGES = [
    'index' => 'Página principal', 'anthia' => 'Anthia', 'gracias' => 'Gracias',
    'hacienda-ticopo-gutierrez' => 'Hacienda Ticopó Gutiérrez', 'lakuun' => 'Lakuun',
    'lote-telchac' => 'Lote Telchac', 'marela-celestun' => 'Marela Celestún',
    'marela-life' => 'Marela Life', 'martell' => 'Martell', 'monarca' => 'Monarca',
    'nexum82' => 'Nexum82', 'solha' => 'Solhá', 'suno' => 'SUNO',
    'terreno-dzoyola' => 'Terreno Dzoyolá', 'lote-220-ciudad-central-progreso' => 'Lote 220 · Ciudad Central Progreso', 'velamar' => 'Velamar',
];
const ADMIN_DEVELOPMENTS = [
    'solha' => 'Solhá', 'nexum82' => 'Nexum82', 'lakuun' => 'Lakuun', 'martell' => 'Martell',
    'marela-life' => 'Marela Life', 'hacienda-ticopo-gutierrez' => 'Hacienda Ticopó Gutiérrez',
    'suno' => 'SUNO', 'monarca' => 'Monarca', 'anthia' => 'Anthia',
    'marela-celestun' => 'Marela Celestún', 'velamar' => 'Velamar',
    'lote-telchac' => 'Lote Telchac', 'terreno-dzoyola' => 'Terreno Dzoyolá',
    'lote-220-ciudad-central-progreso' => 'Lote 220 · Ciudad Central Progreso',
];
const ADMIN_AUTH_VERSION = 4;
require_once __DIR__ . '/config.php';

function adminRespond(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function adminPdo(): PDO {
    global $appConfig;
    $host = $appConfig['dbHost'];
    $database = $appConfig['dbName'];
    $user = $appConfig['dbUser'];
    $password = $appConfig['dbPassword'];
    if ($database === '' || $user === '') throw new RuntimeException('Configura DB_NAME y DB_USER en api/config.local.php o en el entorno del servidor.');
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $server = new PDO("mysql:host=$host;charset=utf8mb4", $user, $password, $options);
    try {
        $server->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (PDOException $error) {
        // Algunos hostings no dan permiso CREATE DATABASE, aunque la base ya exista.
    }
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password, $options);
    $pdo->exec("CREATE TABLE IF NOT EXISTS counter_stats (id INT AUTO_INCREMENT PRIMARY KEY, page VARCHAR(50) UNIQUE, count INT DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_admins (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(80) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        must_change_password TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $adminColumns = $pdo->query("SHOW COLUMNS FROM site_admins LIKE 'must_change_password'")->fetch();
    if (!$adminColumns) $pdo->exec('ALTER TABLE site_admins ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER active');
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_sales (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        development VARCHAR(40) NOT NULL,
        units INT UNSIGNED NOT NULL DEFAULT 1,
        sold_on DATE NOT NULL,
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_admin_sales_development (development),
        KEY idx_admin_sales_sold_on (sold_on),
        CONSTRAINT fk_admin_sales_admin FOREIGN KEY (created_by) REFERENCES site_admins(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if ((int)$pdo->query('SELECT COUNT(*) FROM site_admins')->fetchColumn() === 0) {
        $stmt = $pdo->prepare('INSERT INTO site_admins (username, password_hash, active, must_change_password) VALUES (?, ?, 1, 1)');
        $stmt->execute(['admin', password_hash('admin', PASSWORD_DEFAULT)]);
    }
    return $pdo;
}
function adminIsHttps(): bool {
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}
function adminCsrf(): string {
    if (!isset($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    return (string) $_SESSION['admin_csrf'];
}
function requireAdminCsrf(array $input): void {
    $provided = $input['csrf'] ?? '';
    if (!is_string($provided) || !hash_equals(adminCsrf(), $provided)) {
        adminRespond(403, ['error' => 'La sesión expiró. Recarga la página e inténtalo de nuevo.']);
    }
}

try {
    session_name('TierraYucAdmin');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>adminIsHttps(), 'httponly'=>true, 'samesite'=>'Strict']);
    if (!session_start()) adminRespond(500, ['error'=>'No se pudo iniciar la sesión.']);
    if ((int)($_SESSION['admin_auth_version'] ?? 0) !== ADMIN_AUTH_VERSION) {
        unset(
            $_SESSION['admin_id'],
            $_SESSION['admin_must_change_password'],
            $_SESSION['admin_login_attempts'],
            $_SESSION['admin_login_last_attempt']
        );
        $_SESSION['admin_auth_version'] = ADMIN_AUTH_VERSION;
        session_regenerate_id(true);
    }
    $pdo = adminPdo();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        $authenticated = isset($_SESSION['admin_id']);
        $out = ['authenticated'=>$authenticated, 'csrf'=>adminCsrf()];
        if ($authenticated) {
            $out['must_change_password'] = !empty($_SESSION['admin_must_change_password']);
            $visits = array_fill_keys(array_keys(ADMIN_PAGES), 0);
            foreach ($pdo->query("SELECT page, count FROM counter_stats WHERE page LIKE 'visit:%'") as $row) {
                $slug = substr((string)$row['page'], 6);
                $visits[$slug] = (int)$row['count'];
            }
            arsort($visits);
            $out['page_visits'] = array_map(static function (string $slug, int $n): array {
                return ['slug'=>$slug, 'name'=>ADMIN_PAGES[$slug] ?? ucwords(str_replace('-', ' ', $slug)), 'count'=>$n];
            }, array_keys($visits), array_values($visits));
            $sales = [];
            foreach ($pdo->query('SELECT development, SUM(units) AS units FROM admin_sales GROUP BY development ORDER BY units DESC, development ASC') as $row) {
                $slug = (string)$row['development'];
                $sales[] = ['slug'=>$slug, 'name'=>ADMIN_DEVELOPMENTS[$slug] ?? ucwords(str_replace('-', ' ', $slug)), 'units'=>(int)$row['units']];
            }
            $out['sales'] = $sales;
            $out['total_visits'] = (int)$pdo->query("SELECT COALESCE((SELECT count FROM counter_stats WHERE page = 'total_visits'), 0)")->fetchColumn();
        }
        adminRespond(200, $out);
    }
    if ($method !== 'POST') { header('Allow: GET, POST'); adminRespond(405, ['error'=>'Método no permitido.']); }
    $input = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($input)) adminRespond(400, ['error'=>'Solicitud inválida.']);
    requireAdminCsrf($input);
    $action = (string)($input['action'] ?? '');
    if ($action === 'login') {
        $host = strtolower((string)parse_url('http://' . (string)($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
        if (!adminIsHttps() && !in_array($host, ['localhost', '127.0.0.1'], true)) adminRespond(503, ['error'=>'El acceso de administración requiere HTTPS.']);
        $attempts = (int)($_SESSION['admin_login_attempts'] ?? 0);
        $last = (int)($_SESSION['admin_login_last_attempt'] ?? 0);
        if ($attempts >= 8 && time() - $last < 900) adminRespond(429, ['error'=>'Demasiados intentos. Espera 15 minutos antes de volver a intentar.']);
        if (time() - $last >= 900) $attempts = 0;
        $username = trim((string)($input['username'] ?? ''));
        $stmt = $pdo->prepare('SELECT id, password_hash, must_change_password FROM site_admins WHERE username = ? AND active = 1 LIMIT 1');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify((string)($input['password'] ?? ''), (string)$admin['password_hash'])) {
            $_SESSION['admin_login_attempts'] = $attempts + 1;
            $_SESSION['admin_login_last_attempt'] = time();
            adminRespond(401, ['error'=>'Usuario o contraseña incorrectos.']);
        }
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_must_change_password'] = (bool)$admin['must_change_password'];
        $_SESSION['admin_login_attempts'] = 0;
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
        adminRespond(200, ['authenticated'=>true, 'csrf'=>$_SESSION['admin_csrf']]);
    }
    if ($action === 'logout') {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
        adminRespond(200, ['authenticated'=>false, 'csrf'=>$_SESSION['admin_csrf']]);
    }
    if ($action === 'record_sale') {
        if (!isset($_SESSION['admin_id'])) adminRespond(401, ['error'=>'Inicia sesión para continuar.']);
        if (!empty($_SESSION['admin_must_change_password'])) adminRespond(403, ['error'=>'Cambia la contraseña inicial antes de continuar.']);
        $development = (string)($input['development'] ?? '');
        $units = filter_var($input['units'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>100000]]);
        $soldOn = (string)($input['sold_on'] ?? '');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $soldOn);
        if (!isset(ADMIN_DEVELOPMENTS[$development]) || $units === false || !$date || $date->format('Y-m-d') !== $soldOn) adminRespond(400, ['error'=>'Revisa el desarrollo, la cantidad y la fecha de la venta.']);
        $stmt = $pdo->prepare('INSERT INTO admin_sales (development, units, sold_on, created_by) VALUES (?, ?, ?, ?)');
        $stmt->execute([$development, $units, $soldOn, (int)$_SESSION['admin_id']]);
        adminRespond(200, ['saved'=>true, 'csrf'=>adminCsrf()]);
    }
    if ($action === 'reset_visits') {
        if (!isset($_SESSION['admin_id'])) adminRespond(401, ['error'=>'Inicia sesión para continuar.']);
        if (!empty($_SESSION['admin_must_change_password'])) adminRespond(403, ['error'=>'Cambia la contraseña inicial antes de continuar.']);
        $pdo->exec("UPDATE counter_stats SET count = 0 WHERE page = 'total_visits' OR page LIKE 'visit:%'");
        adminRespond(200, ['reset'=>true, 'csrf'=>adminCsrf()]);
    }
    if ($action === 'change_password') {
        if (!isset($_SESSION['admin_id'])) adminRespond(401, ['error'=>'Inicia sesión para continuar.']);
        $newPassword = (string)($input['new_password'] ?? '');
        $stmt = $pdo->prepare('SELECT password_hash FROM site_admins WHERE id = ? AND active = 1');
        $stmt->execute([(int)$_SESSION['admin_id']]);
        $currentHash = $stmt->fetchColumn();
        if (!$currentHash || !password_verify((string)($input['current_password'] ?? ''), (string)$currentHash)) adminRespond(400, ['error'=>'La contraseña actual no es correcta.']);
        if (preg_match('/\A(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s]).{8,13}\z/D', $newPassword) !== 1) {
            adminRespond(400, ['error'=>'Usa de 8 a 13 caracteres con al menos una mayúscula, un número y un símbolo.']);
        }
        $stmt = $pdo->prepare('UPDATE site_admins SET password_hash = ?, must_change_password = 0 WHERE id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int)$_SESSION['admin_id']]);
        $_SESSION['admin_must_change_password'] = false;
        adminRespond(200, ['saved'=>true, 'csrf'=>adminCsrf()]);
    }
    adminRespond(400, ['error'=>'Acción no reconocida.']);
} catch (Throwable $error) {
    error_log('Admin dashboard error: '.$error->getMessage());
    adminRespond(500, ['error'=>'No se pudo completar la operación. Revisa la configuración de la base de datos.']);
}

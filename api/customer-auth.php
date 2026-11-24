<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

const CUSTOMER_SESSION_TTL = 2592000;

final class AuthProblem extends RuntimeException
{
    public $status;
    public $errorCode;

    public function __construct(int $status, string $errorCode, string $message)
    {
        parent::__construct($message);
        $this->status = $status;
        $this->errorCode = $errorCode;
    }
}

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function envValue(string $name): string
{
    $value = getenv($name);
    return $value === false ? '' : trim($value);
}

function loadConfig(): array
{
    $config = [
        'dsn' => envValue('DB_DSN'),
        'dbUser' => envValue('DB_USER'),
        'dbPassword' => envValue('DB_PASSWORD'),
        'otpPepper' => envValue('APP_OTP_PEPPER'),
        'graphVersion' => envValue('WHATSAPP_GRAPH_API_VERSION'),
        'phoneNumberId' => envValue('WHATSAPP_PHONE_NUMBER_ID'),
        'accessToken' => envValue('WHATSAPP_ACCESS_TOKEN'),
        'templateName' => envValue('WHATSAPP_OTP_TEMPLATE_NAME'),
        'templateLanguage' => envValue('WHATSAPP_OTP_TEMPLATE_LANGUAGE'),
        'allowedOrigins' => envValue('APP_ALLOWED_ORIGINS'),
        'environment' => envValue('APP_ENV'),
    ];

    $config['ready'] =
        strpos($config['dsn'], 'mysql:') === 0
        && $config['dbUser'] !== ''
        && $config['dbPassword'] !== ''
        && strlen($config['otpPepper']) >= 32
        && preg_match('/^v[0-9]+\.[0-9]+$/D', $config['graphVersion']) === 1
        && preg_match('/^[0-9]+$/D', $config['phoneNumberId']) === 1
        && $config['accessToken'] !== ''
        && preg_match('/^[a-z0-9_]+$/D', $config['templateName']) === 1
        && preg_match('/^[a-z]{2}_[A-Z]{2}$/D', $config['templateLanguage']) === 1;

    return $config;
}

function isHttpsRequest(): bool
{
    return isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off' && $_SERVER['HTTPS'] !== '';
}

function isLocalDevelopment(array $config): bool
{
    $host = strtolower((string) parse_url('http://' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
    $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return $config['environment'] === 'development'
        && in_array($host, ['localhost', '127.0.0.1'], true)
        && in_array($remoteAddress, ['127.0.0.1', '::1'], true);
}

function startCustomerSession(array $config): void
{
    $secure = isHttpsRequest();
    session_name('TierraYucCustomer');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string) CUSTOMER_SESSION_TTL);
    session_set_cookie_params([
        'lifetime' => CUSTOMER_SESSION_TTL,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!session_start()) {
        respond(500, ['error' => 'No se pudo iniciar la sesión del cliente.', 'code' => 'SESSION_ERROR']);
    }

    if (!isset($_SESSION['customer_auth_csrf']) || !is_string($_SESSION['customer_auth_csrf'])) {
        $_SESSION['customer_auth_csrf'] = bin2hex(random_bytes(32));
    }
}

function createPdo(array $config): PDO
{
    return new PDO($config['dsn'], $config['dbUser'], $config['dbPassword'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function requireSecureTransport(array $config): void
{
    if (!isHttpsRequest() && !isLocalDevelopment($config)) {
        respond(503, [
            'error' => 'La verificación de teléfonos requiere HTTPS.',
            'code' => 'HTTPS_REQUIRED',
        ]);
    }
}

function requireAllowedOrigin(array $config): void
{
    $origin = rtrim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
    $allowed = $config['allowedOrigins'] !== ''
        ? array_map(static function (string $allowedOrigin): string {
            return rtrim(trim($allowedOrigin), '/');
        }, explode(',', $config['allowedOrigins']))
        : ['https://www.tierrayuc.mx', 'https://tierrayuc.mx'];

    if (isLocalDevelopment($config)) {
        $allowed[] = 'http://localhost:8000';
        $allowed[] = 'http://127.0.0.1:8000';
    }

    if ($origin === '' || !in_array($origin, $allowed, true)) {
        respond(403, ['error' => 'Origen no permitido.', 'code' => 'ORIGIN_NOT_ALLOWED']);
    }
}

function requireCsrf(array $payload): void
{
    $submittedToken = $payload['csrf'] ?? null;
    $sessionToken = $_SESSION['customer_auth_csrf'] ?? null;
    if (!is_string($submittedToken) || !is_string($sessionToken) || !hash_equals($sessionToken, $submittedToken)) {
        respond(403, ['error' => 'La sesión expiró. Recarga la página e inténtalo de nuevo.', 'code' => 'CSRF_INVALID']);
    }
}

function readJsonPayload(): array
{
    $body = file_get_contents('php://input');
    if ($body === false || strlen($body) > 4096) {
        respond(400, ['error' => 'La solicitud no es válida.', 'code' => 'INVALID_REQUEST']);
    }

    try {
        $payload = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        respond(400, ['error' => 'El contenido JSON no es válido.', 'code' => 'INVALID_JSON']);
    }

    if (!is_array($payload)) {
        respond(400, ['error' => 'La solicitud debe ser un objeto JSON.', 'code' => 'INVALID_JSON']);
    }

    return $payload;
}

function normalizePhone($value): string
{
    if (!is_string($value) || strlen($value) > 32) {
        throw new AuthProblem(400, 'PHONE_INVALID', 'Ingresa un número de WhatsApp válido.');
    }

    $trimmed = trim($value);
    $digits = preg_replace('/\D+/', '', $trimmed);
    if (!is_string($digits) || $digits === '') {
        throw new AuthProblem(400, 'PHONE_INVALID', 'Ingresa un número de WhatsApp válido.');
    }

    if (strlen($digits) === 10) {
        $digits = '52' . $digits;
    }

    $normalized = '+' . $digits;
    if (preg_match('/^\+[1-9][0-9]{7,14}$/D', $normalized) !== 1) {
        throw new AuthProblem(400, 'PHONE_INVALID', 'Usa el código de país; por ejemplo, +52 seguido de los 10 dígitos.');
    }

    return $normalized;
}

function makeOtpHash(string $phone, string $code, string $pepper): string
{
    return hash_hmac('sha256', $phone . ':' . $code, $pepper);
}

function reserveCodeRequest(PDO $db, string $phone, string $codeHash, string $ipHash, int $now): void
{
    $db->beginTransaction();
    try {
        $insertPhone = $db->prepare(
            'INSERT IGNORE INTO customer_phone_codes
             (phone_e164, code_hash, expires_at, sent_at, attempts, request_count, request_window_started_at, blocked_until, consented_at)
             VALUES (:phone, NULL, 0, 0, 0, 0, 0, 0, 0)'
        );
        $insertPhone->execute(['phone' => $phone]);

        $phoneQuery = $db->prepare('SELECT * FROM customer_phone_codes WHERE phone_e164 = :phone FOR UPDATE');
        $phoneQuery->execute(['phone' => $phone]);
        $phoneRecord = $phoneQuery->fetch();
        if (!is_array($phoneRecord)) {
            throw new RuntimeException('Could not lock phone verification row.');
        }

        if ((int) $phoneRecord['blocked_until'] > $now) {
            throw new AuthProblem(429, 'PHONE_BLOCKED', 'Demasiados intentos. Espera antes de solicitar otro código.');
        }
        if ((int) $phoneRecord['sent_at'] > $now - 60) {
            throw new AuthProblem(429, 'PHONE_COOLDOWN', 'Espera un minuto antes de solicitar otro código.');
        }

        $windowStartedAt = (int) $phoneRecord['request_window_started_at'];
        $requestCount = (int) $phoneRecord['request_count'];
        if ($windowStartedAt === 0 || $windowStartedAt < $now - 86400) {
            $windowStartedAt = $now;
            $requestCount = 0;
        }
        if ($requestCount >= 5) {
            throw new AuthProblem(429, 'PHONE_DAILY_LIMIT', 'Alcanzaste el límite diario de códigos para este número.');
        }

        $insertIp = $db->prepare(
            'INSERT IGNORE INTO customer_auth_ip_limits (ip_hash, window_started_at, request_count)
             VALUES (:ip_hash, :window_started_at, 0)'
        );
        $insertIp->execute(['ip_hash' => $ipHash, 'window_started_at' => $now]);
        $ipQuery = $db->prepare('SELECT * FROM customer_auth_ip_limits WHERE ip_hash = :ip_hash FOR UPDATE');
        $ipQuery->execute(['ip_hash' => $ipHash]);
        $ipRecord = $ipQuery->fetch();
        if (!is_array($ipRecord)) {
            throw new RuntimeException('Could not lock IP rate-limit row.');
        }

        $ipWindowStartedAt = (int) $ipRecord['window_started_at'];
        $ipRequestCount = (int) $ipRecord['request_count'];
        if ($ipWindowStartedAt < $now - 3600) {
            $ipWindowStartedAt = $now;
            $ipRequestCount = 0;
        }
        if ($ipRequestCount >= 10) {
            throw new AuthProblem(429, 'IP_RATE_LIMIT', 'Se alcanzó el límite temporal de solicitudes. Intenta más tarde.');
        }

        $updatePhone = $db->prepare(
            'UPDATE customer_phone_codes
             SET code_hash = :code_hash,
                 expires_at = :expires_at,
                 sent_at = :sent_at,
                 attempts = 0,
                 request_count = :request_count,
                 request_window_started_at = :window_started_at,
                 blocked_until = 0,
                 consented_at = :consented_at
             WHERE phone_e164 = :phone'
        );
        $updatePhone->execute([
            'code_hash' => $codeHash,
            'expires_at' => $now + 600,
            'sent_at' => $now,
            'request_count' => $requestCount + 1,
            'window_started_at' => $windowStartedAt,
            'consented_at' => $now,
            'phone' => $phone,
        ]);

        $updateIp = $db->prepare(
            'UPDATE customer_auth_ip_limits
             SET window_started_at = :window_started_at, request_count = :request_count
             WHERE ip_hash = :ip_hash'
        );
        $updateIp->execute([
            'window_started_at' => $ipWindowStartedAt,
            'request_count' => $ipRequestCount + 1,
            'ip_hash' => $ipHash,
        ]);

        $db->prepare('DELETE FROM customer_auth_ip_limits WHERE window_started_at < :cutoff')
            ->execute(['cutoff' => $now - 86400]);
        $db->prepare('DELETE FROM customer_phone_codes WHERE sent_at < :cutoff AND phone_e164 <> :phone')
            ->execute(['cutoff' => $now - 2592000, 'phone' => $phone]);
        $db->commit();
    } catch (AuthProblem $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    } catch (PDOException $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    } catch (RuntimeException $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function sendWhatsAppCode(array $config, string $phone, string $code): bool
{
    if (!function_exists('curl_init')) {
        error_log('Customer phone verification requires the PHP cURL extension.');
        return false;
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => substr($phone, 1),
        'type' => 'template',
        'template' => [
            'name' => $config['templateName'],
            'language' => ['code' => $config['templateLanguage']],
            'components' => [[
                'type' => 'body',
                'parameters' => [[
                    'type' => 'text',
                    'text' => $code,
                ]],
            ]],
        ],
    ];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        error_log('Could not encode the WhatsApp authentication template request.');
        return false;
    }

    $url = 'https://graph.facebook.com/' . $config['graphVersion'] . '/'
        . $config['phoneNumberId'] . '/messages';
    $handle = curl_init($url);
    if ($handle === false) {
        error_log('Could not initialize the WhatsApp Cloud API request.');
        return false;
    }

    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['accessToken'],
            'Content-Type: application/json',
        ],
    ]);
    $response = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);

    if ($response === false || $status < 200 || $status >= 300) {
        error_log('WhatsApp Cloud API could not send an authentication template. HTTP status: ' . $status . '. ' . $curlError);
        return false;
    }

    return true;
}

function verifyOtp(PDO $db, array $config, string $phone, string $code): void
{
    $now = time();
    $db->beginTransaction();
    try {
        $query = $db->prepare('SELECT * FROM customer_phone_codes WHERE phone_e164 = :phone FOR UPDATE');
        $query->execute(['phone' => $phone]);
        $record = $query->fetch();
        if (!is_array($record) || !is_string($record['code_hash']) || $record['code_hash'] === '') {
            throw new AuthProblem(400, 'CODE_NOT_FOUND', 'Solicita un código nuevo para verificar tu teléfono.');
        }

        if ((int) $record['blocked_until'] > $now) {
            throw new AuthProblem(429, 'CODE_BLOCKED', 'Demasiados códigos incorrectos. Solicita otro más tarde.');
        }
        if ((int) $record['expires_at'] < $now) {
            $db->prepare('DELETE FROM customer_phone_codes WHERE phone_e164 = :phone')
                ->execute(['phone' => $phone]);
            $db->commit();
            throw new AuthProblem(400, 'CODE_EXPIRED', 'El código venció. Solicita uno nuevo.');
        }

        $expectedHash = makeOtpHash($phone, $code, $config['otpPepper']);
        if (!hash_equals($record['code_hash'], $expectedHash)) {
            $attempts = (int) $record['attempts'] + 1;
            $blockedUntil = $attempts >= 5 ? $now + 900 : 0;
            $db->prepare(
                'UPDATE customer_phone_codes
                 SET attempts = :attempts, blocked_until = :blocked_until
                 WHERE phone_e164 = :phone'
            )->execute([
                'attempts' => $attempts,
                'blocked_until' => $blockedUntil,
                'phone' => $phone,
            ]);
            $db->commit();

            if ($attempts >= 5) {
                throw new AuthProblem(429, 'TOO_MANY_CODE_ATTEMPTS', 'Se bloquearon los intentos por 15 minutos. Solicita otro código más tarde.');
            }
            throw new AuthProblem(400, 'CODE_INCORRECT', 'El código no coincide. Revísalo e inténtalo de nuevo.');
        }

        $consentedAt = (int) $record['consented_at'];
        if ($consentedAt === 0) {
            throw new AuthProblem(400, 'CONSENT_REQUIRED', 'Vuelve a solicitar el código y acepta el aviso de almacenamiento del teléfono.');
        }

        $saveAccount = $db->prepare(
            'INSERT INTO customer_accounts (phone_e164, created_at, last_verified_at, consented_at)
             VALUES (:phone, UTC_TIMESTAMP(), UTC_TIMESTAMP(), FROM_UNIXTIME(:consented_at))
             ON DUPLICATE KEY UPDATE
               last_verified_at = UTC_TIMESTAMP(),
               consented_at = UTC_TIMESTAMP()'
        );
        $saveAccount->execute([
            'phone' => $phone,
            'consented_at' => $consentedAt,
        ]);
        $db->prepare('DELETE FROM customer_phone_codes WHERE phone_e164 = :phone')
            ->execute(['phone' => $phone]);
        $db->commit();
    } catch (AuthProblem $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    } catch (PDOException $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    } catch (RuntimeException $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }

    session_regenerate_id(true);
    $_SESSION['verified_customer_phone'] = $phone;
    $_SESSION['verified_customer_at'] = $now;
}

function handleProblem(AuthProblem $problem): void
{
    respond($problem->status, [
        'error' => $problem->getMessage(),
        'code' => $problem->errorCode,
    ]);
}

$config = loadConfig();
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    respond(405, ['error' => 'Método no permitido.', 'code' => 'METHOD_NOT_ALLOWED']);
}

if ($config['ready'] && !isHttpsRequest() && !isLocalDevelopment($config)) {
    respond(503, ['error' => 'La verificación requiere HTTPS.', 'code' => 'HTTPS_REQUIRED']);
}

startCustomerSession($config);

if ($method === 'GET') {
    if (($_GET['action'] ?? '') !== 'state') {
        respond(404, ['error' => 'Acción no encontrada.', 'code' => 'ACTION_NOT_FOUND']);
    }

    if (!$config['ready']) {
        respond(200, [
            'configured' => false,
            'authenticated' => false,
            'csrf' => $_SESSION['customer_auth_csrf'],
        ]);
    }

    try {
        $db = createPdo($config);
        $phone = $_SESSION['verified_customer_phone'] ?? null;
        $authenticated = false;
        if (is_string($phone) && $phone !== '') {
            $accountQuery = $db->prepare('SELECT phone_e164 FROM customer_accounts WHERE phone_e164 = :phone');
            $accountQuery->execute(['phone' => $phone]);
            $authenticated = $accountQuery->fetchColumn() !== false;
            if (!$authenticated) {
                unset($_SESSION['verified_customer_phone'], $_SESSION['verified_customer_at']);
            }
        }
    } catch (PDOException $error) {
        error_log('Customer authentication database connection failed: ' . $error->getMessage());
        respond(503, ['error' => 'La verificación no está disponible en este momento.', 'code' => 'AUTH_UNAVAILABLE']);
    }

    respond(200, [
        'configured' => true,
        'authenticated' => $authenticated,
        'phone' => $authenticated ? $phone : null,
        'csrf' => $_SESSION['customer_auth_csrf'],
    ]);
}

requireSecureTransport($config);
requireAllowedOrigin($config);
$payload = readJsonPayload();
requireCsrf($payload);

if (!$config['ready']) {
    respond(503, [
        'error' => 'La verificación de WhatsApp aún no está configurada.',
        'code' => 'PHONE_AUTH_NOT_CONFIGURED',
    ]);
}

$action = $payload['action'] ?? null;
if (!is_string($action)) {
    respond(400, ['error' => 'La acción no es válida.', 'code' => 'ACTION_INVALID']);
}

try {
    $db = createPdo($config);
} catch (PDOException $error) {
    error_log('Customer authentication database connection failed: ' . $error->getMessage());
    respond(503, ['error' => 'La verificación no está disponible en este momento.', 'code' => 'AUTH_UNAVAILABLE']);
}

if ($action === 'request_code') {
    if (($payload['consent'] ?? null) !== true) {
        respond(400, [
            'error' => 'Acepta el aviso para guardar el teléfono en tu cuenta.',
            'code' => 'CONSENT_REQUIRED',
        ]);
    }
    if (isset($_SESSION['verified_customer_phone'])) {
        respond(409, [
            'error' => 'Cierra la sesión actual antes de verificar otro teléfono.',
            'code' => 'ALREADY_AUTHENTICATED',
        ]);
    }

    try {
        $phone = normalizePhone($payload['phone'] ?? null);
    } catch (AuthProblem $problem) {
        handleProblem($problem);
    }

    try {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codeHash = makeOtpHash($phone, $code, $config['otpPepper']);
        $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ipHash = hash_hmac('sha256', $remoteAddress, $config['otpPepper']);
        reserveCodeRequest($db, $phone, $codeHash, $ipHash, time());
    } catch (AuthProblem $problem) {
        handleProblem($problem);
    } catch (PDOException $error) {
        error_log('Customer authentication rate-limit database error: ' . $error->getMessage());
        respond(503, ['error' => 'No se pudo solicitar el código. Inténtalo más tarde.', 'code' => 'AUTH_UNAVAILABLE']);
    } catch (RuntimeException $error) {
        error_log('Customer authentication rate-limit error: ' . $error->getMessage());
        respond(500, ['error' => 'No se pudo preparar la verificación.', 'code' => 'AUTH_INTERNAL_ERROR']);
    }

    if (!sendWhatsAppCode($config, $phone, $code)) {
        respond(502, [
            'error' => 'WhatsApp no pudo enviar el código. Revisa la configuración e inténtalo de nuevo en un minuto.',
            'code' => 'WHATSAPP_SEND_FAILED',
        ]);
    }

    respond(200, [
        'sent' => true,
        'phone' => $phone,
        'message' => 'Enviamos un código de seis dígitos a tu WhatsApp. Vence en 10 minutos.',
    ]);
}

if ($action === 'verify_code') {
    if (isset($_SESSION['verified_customer_phone'])) {
        respond(409, [
            'error' => 'Cierra la sesión actual antes de verificar otro teléfono.',
            'code' => 'ALREADY_AUTHENTICATED',
        ]);
    }

    try {
        $phone = normalizePhone($payload['phone'] ?? null);
    } catch (AuthProblem $problem) {
        handleProblem($problem);
    }

    $code = $payload['code'] ?? null;
    if (!is_string($code) || preg_match('/^[0-9]{6}$/D', $code) !== 1) {
        respond(400, ['error' => 'Ingresa el código de seis dígitos.', 'code' => 'CODE_INVALID']);
    }

    try {
        verifyOtp($db, $config, $phone, $code);
    } catch (AuthProblem $problem) {
        handleProblem($problem);
    } catch (PDOException $error) {
        error_log('Customer authentication verification database error: ' . $error->getMessage());
        respond(503, ['error' => 'No se pudo verificar el código. Inténtalo más tarde.', 'code' => 'AUTH_UNAVAILABLE']);
    } catch (RuntimeException $error) {
        error_log('Customer authentication verification error: ' . $error->getMessage());
        respond(500, ['error' => 'No se pudo completar la verificación.', 'code' => 'AUTH_INTERNAL_ERROR']);
    }

    $_SESSION['customer_auth_csrf'] = bin2hex(random_bytes(32));
    respond(200, [
        'authenticated' => true,
        'phone' => $phone,
        'csrf' => $_SESSION['customer_auth_csrf'],
        'message' => 'Teléfono verificado y guardado en tu cuenta.',
    ]);
}

if ($action === 'logout') {
    unset($_SESSION['verified_customer_phone'], $_SESSION['verified_customer_at']);
    session_regenerate_id(true);
    $_SESSION['customer_auth_csrf'] = bin2hex(random_bytes(32));
    respond(200, [
        'authenticated' => false,
        'csrf' => $_SESSION['customer_auth_csrf'],
        'message' => 'Cerraste sesión. Verifica el teléfono para usar otro número.',
    ]);
}

if ($action === 'delete_account') {
    $phone = $_SESSION['verified_customer_phone'] ?? null;
    if (!is_string($phone) || $phone === '') {
        respond(401, ['error' => 'Inicia sesión para eliminar tu cuenta.', 'code' => 'AUTH_REQUIRED']);
    }

    try {
        $db->beginTransaction();
        $db->prepare('DELETE FROM customer_accounts WHERE phone_e164 = :phone')
            ->execute(['phone' => $phone]);
        $db->prepare('DELETE FROM customer_phone_codes WHERE phone_e164 = :phone')
            ->execute(['phone' => $phone]);
        $db->commit();
    } catch (PDOException $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Customer account deletion failed: ' . $error->getMessage());
        respond(503, ['error' => 'No se pudo eliminar la cuenta. Inténtalo más tarde.', 'code' => 'DELETE_FAILED']);
    }

    unset($_SESSION['verified_customer_phone'], $_SESSION['verified_customer_at']);
    session_regenerate_id(true);
    $_SESSION['customer_auth_csrf'] = bin2hex(random_bytes(32));
    respond(200, [
        'authenticated' => false,
        'csrf' => $_SESSION['customer_auth_csrf'],
        'message' => 'Se eliminó la cuenta y el teléfono guardado.',
    ]);
}

respond(400, ['error' => 'Acción no reconocida.', 'code' => 'ACTION_NOT_FOUND']);

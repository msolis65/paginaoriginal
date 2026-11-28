<?php
/** Shared database and optional integration settings. Keep secrets in config.local.php or server env vars. */
$localConfigFile = __DIR__ . '/config.local.php';
$localConfig = is_file($localConfigFile) ? require $localConfigFile : [];
if (!is_array($localConfig)) $localConfig = [];

function appSetting(string $name, array $localConfig, string $default = ''): string
{
    $environmentValue = getenv($name);
    if ($environmentValue !== false && $environmentValue !== '') return trim($environmentValue);
    return trim((string)($localConfig[$name] ?? $default));
}

$dbHost = appSetting('DB_HOST', $localConfig, 'localhost');
$dbName = appSetting('DB_NAME', $localConfig);
$dbUser = appSetting('DB_USER', $localConfig);
$dbPassword = appSetting('DB_PASSWORD', $localConfig);
$appConfig = [
    'dbHost' => $dbHost,
    'dbName' => $dbName,
    'dbUser' => $dbUser,
    'dbPassword' => $dbPassword,
    'dsn' => appSetting('DB_DSN', $localConfig, $dbName !== '' ? "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4" : ''),
];
foreach (['APP_OTP_PEPPER', 'WHATSAPP_GRAPH_API_VERSION', 'WHATSAPP_PHONE_NUMBER_ID', 'WHATSAPP_ACCESS_TOKEN', 'WHATSAPP_OTP_TEMPLATE_NAME', 'WHATSAPP_OTP_TEMPLATE_LANGUAGE', 'APP_ALLOWED_ORIGINS', 'APP_ENV'] as $key) {
    $appConfig[$key] = appSetting($key, $localConfig);
}

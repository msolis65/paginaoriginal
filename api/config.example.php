<?php
// Copy to config.local.php and replace values. Never commit config.local.php.
return [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'tierrayuc',
    'DB_USER' => 'root',
    'DB_PASSWORD' => '',
    'APP_ENV' => 'development',
    // Required only for WhatsApp verification. Generate a random secret of 32+ characters.
    'APP_OTP_PEPPER' => '',
    'WHATSAPP_GRAPH_API_VERSION' => '',
    'WHATSAPP_PHONE_NUMBER_ID' => '',
    'WHATSAPP_ACCESS_TOKEN' => '',
    'WHATSAPP_OTP_TEMPLATE_NAME' => '',
    'WHATSAPP_OTP_TEMPLATE_LANGUAGE' => 'es_MX',
    'APP_ALLOWED_ORIGINS' => 'http://localhost, http://127.0.0.1',
];

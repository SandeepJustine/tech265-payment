<?php
define('SITE_NAME', 'Tech265 Payments');
define('SITE_URL', 'http://localhost/tech265-payment');
// Tech265 Gateway Base URL (no trailing slash)
define('TECH265_BASE_URL', 'https://api.tech265.com/v1');
define('DEFAULT_SMS_RATE', 21); // Cost per SMS unit
define('MAX_SMS_LENGTH', 160);
define('MAX_RECIPIENTS', 1000);
define('UPLOAD_PATH', 'assets/uploads/');
define('LOGO_MAX_SIZE', 2 * 1024 * 1024); // 2MB
// CA bundle for cURL SSL validation (download cacert.pem and place alongside this file)
define('CURL_CA_BUNDLE_PATH', __DIR__ . '/cacert.pem');
// Insecure SSL fallback MUST remain false in production to prevent MITM attacks.
define('ALLOW_INSECURE_SSL_FALLBACK', false);
?>
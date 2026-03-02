<?php
if (!function_exists('cfg_env')) {
  function cfg_env(string $key, $default = null) {
    $val = getenv($key);
    if ($val === false || $val === '') return $default;
    return $val;
  }
}

$config = [
  'db' => [
    'host' => (string)cfg_env('DB_HOST', '127.0.0.1'),
    'port' => (int)cfg_env('DB_PORT', 3306),
    'name' => (string)cfg_env('DB_NAME', 'u305836601_SEGEN'),
    'user' => (string)cfg_env('DB_USER', ''),
    'pass' => (string)cfg_env('DB_PASS', ''),
    'charset' => (string)cfg_env('DB_CHARSET', 'utf8mb4'),
  ],
  'app' => [
    'base_url' => (string)cfg_env('APP_BASE_URL', '/'),
    'env' => (string)cfg_env('APP_ENV', 'prod'),
  ],
  'mail' => [
    'from' => (string)cfg_env('MAIL_FROM', 'nao-responder@segen.gov.br'),
  ],
];

// Opcional para ambiente local (não versionar com segredos).
$localConfig = __DIR__.'/config.local.php';
if (is_file($localConfig)) {
  $local = require $localConfig;
  if (is_array($local)) {
    $config = array_replace_recursive($config, $local);
  }
}

return $config;

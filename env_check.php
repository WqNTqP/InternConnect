<?php
// Environment variables diagnostic
header('Content-Type: application/json');

$envVars = [
    'DB_HOST',
    'DB_USERNAME', 
    'DB_PASSWORD',
    'DB_NAME',
    'PORT',
    'FLASK_ENV'
];

$result = [
    'environment_variables' => [],
    'all_env_vars' => $_ENV,
    'server_vars' => $_SERVER
];

foreach ($envVars as $var) {
    $result['environment_variables'][$var] = [
        'value' => getenv($var) ?: 'NOT SET',
        'isset' => getenv($var) !== false
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>
<?php
// Custom router for PHP built-in server on Render
// Replicates .htaccess pretty routes and canonical redirects

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$docRoot = __DIR__;

// Serve existing files/directories directly
$real = realpath($docRoot . $uri);
if ($real && is_file($real)) {
    return false; // Let built-in server handle it
}

// Canonicalize index to directory root
if ($uri === '/index' || $uri === '/index/' || $uri === '/index.php') {
    header('Location: /', true, 301);
    exit;
}

// Canonical redirects from legacy filenames to slugs (GET only)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $legacy = [
        '#^/admindashboard(?:\.php)?/?$#i'        => '/supervisor/dashboard',
        '#^/admin(?:/dashboard)?/?$#i'             => '/supervisor',
        '#^/mainDashboard(?:\.php)?/?$#i'         => '/dashboard',
        '#^/student_login(?:\.php)?/?$#i'         => '/student',
        '#^/student_dashboard(?:\.php)?/?$#i'     => '/student/dashboard',
        '#^/superadmin_dashboard(?:\.php)?/?$#i'  => '/superadmin/dashboard',
        '#^/forgot_password(?:\.php)?/?$#i'       => '/forgot-password',
        '#^/reset_password(?:\.php)?/?$#i'        => '/reset-password',
        '#^/admin\.php$#i'                         => '/supervisor',
    ];
    foreach ($legacy as $pattern => $target) {
        if (preg_match($pattern, $uri)) {
            header('Location: ' . $target, true, 301);
            exit;
        }
    }
}

// Pretty route mappings
$routes = [
    '/'                     => 'index.php',
    '/supervisor'           => 'admin.php',
    '/supervisor/dashboard' => 'admindashboard.php',
    '/student'              => 'student_login.php',
    '/student/dashboard'    => 'student_dashboard.php',
    '/superadmin/dashboard' => 'superadmin_dashboard.php',
    '/dashboard'            => 'mainDashboard.php',
    '/forgot-password'      => 'forgot_password.php',
    '/reset-password'       => 'reset_password.php',
];

if (isset($routes[$uri])) {
    require $routes[$uri];
    return true;
}

// Generic extensionless fallback: try appending .php
$tryPhp = $docRoot . rtrim($uri, '/') . '.php';
if (is_file($tryPhp)) {
    require $tryPhp;
    return true;
}

// 404 Not Found
http_response_code(404);
echo "404 Not Found";
return true;

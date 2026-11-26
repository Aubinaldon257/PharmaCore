<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifierJetonCsrf()) {
    http_response_code(400);
    exit('Requête invalide.');
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $parametres = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $parametres['path'],
        'domain' => $parametres['domain'],
        'secure' => $parametres['secure'],
        'httponly' => $parametres['httponly'],
        'samesite' => $parametres['samesite'] ?? 'Lax',
    ]);
}

session_destroy();
rediriger('login.php');

<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/connexion.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

function e(string|int|float|null $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function jetonCsrf(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifierJetonCsrf(): bool
{
    $jeton = $_POST['csrf_token'] ?? null;

    return is_string($jeton)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $jeton);
}

function exigerAdministrateur(): void
{
    if (
        !isset($_SESSION['admin_id'], $_SESSION['admin_role'])
        || !is_int($_SESSION['admin_id'])
        || $_SESSION['admin_id'] < 1
        || $_SESSION['admin_role'] !== 'admin'
    ) {
        header('Location: login.php');
        exit;
    }
}

function exigerClient(): void
{
    if (
        !isset($_SESSION['client_id'], $_SESSION['client_role'])
        || !is_int($_SESSION['client_id'])
        || $_SESSION['client_id'] < 1
        || $_SESSION['client_role'] !== 'client'
    ) {
        header('Location: login.php');
        exit;
    }
}

function avatarAdministrateurUrl(int $adminId): ?string
{
    if ($adminId < 1) {
        return null;
    }

    $extensions = ['jpg', 'png', 'webp'];
    $dossier = dirname(__DIR__) . '/storage/admin-avatars';

    foreach ($extensions as $extension) {
        $fichier = $dossier . '/' . $adminId . '.' . $extension;
        if (is_file($fichier)) {
            return 'avatar.php?v=' . (string) filemtime($fichier);
        }
    }

    return null;
}

function rediriger(string $destination): never
{
    header('Location: ' . $destination);
    exit;
}

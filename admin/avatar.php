<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
exigerAdministrateur();

$adminId = (int) $_SESSION['admin_id'];
$dossier = dirname(__DIR__) . '/storage/admin-avatars';
$typesImage = [
    'jpg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];

foreach ($typesImage as $extension => $typeMime) {
    $fichier = $dossier . '/' . $adminId . '.' . $extension;
    if (!is_file($fichier)) {
        continue;
    }

    header('Content-Type: ' . $typeMime);
    header('Content-Length: ' . (string) filesize($fichier));
    header('Cache-Control: private, max-age=300');
    header('X-Content-Type-Options: nosniff');
    readfile($fichier);
    exit;
}

http_response_code(404);
header('Cache-Control: no-store');

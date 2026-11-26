<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
exigerAdministrateur();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Méthode non autorisée.');
}
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!verifierJetonCsrf() || $id === false) {
    http_response_code(400);
    exit('Requête invalide ou expirée. Actualisez le catalogue puis réessayez.');
}

$pdo = connexionPDO();
try {
    $pdo->beginTransaction();
    $verification = $pdo->prepare('SELECT id FROM medicaments WHERE id = :id FOR UPDATE');
    $verification->execute(['id' => $id]);
    if (!$verification->fetch()) {
        $pdo->rollBack();
        http_response_code(404);
        exit('Médicament introuvable.');
    }
    $ventesExistantes = $pdo->prepare('SELECT 1 FROM vente_details WHERE medicament_id = :id LIMIT 1');
    $ventesExistantes->execute(['id' => $id]);
    if ($ventesExistantes->fetchColumn()) {
        $pdo->rollBack();
        rediriger('medicaments.php?error=sold');
    }
    $suppression = $pdo->prepare('DELETE FROM medicaments WHERE id = :id');
    $suppression->execute(['id' => $id]);
    $pdo->commit();
    rediriger('medicaments.php?deleted=1');
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('PharmaCore medicine deletion failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Le médicament n’a pas pu être supprimé.');
}

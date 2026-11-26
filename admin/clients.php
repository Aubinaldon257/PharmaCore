<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
exigerAdministrateur();
$pdo = connexionPDO();
try {
    $clients = $pdo->query(
        'SELECT u.id, u.prenom, u.nom, u.email, u.telephone, u.date_creation,
                COUNT(v.id) AS ventes_associees
         FROM utilisateurs u LEFT JOIN ventes v ON v.utilisateur_id = u.id
         WHERE u.role = \'client\'
         GROUP BY u.id, u.prenom, u.nom, u.email, u.telephone, u.date_creation
         ORDER BY u.date_creation DESC, u.id DESC LIMIT 200'
    )->fetchAll();
    $erreur = '';
} catch (PDOException $exception) {
    error_log('PharmaCore client list failed: ' . $exception->getMessage());
    $clients = [];
    $erreur = 'La liste des clients est momentanément indisponible.';
}
$notifications = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE utilisateur_id = :id AND est_lue = 0');
$notifications->execute(['id' => (int) $_SESSION['admin_id']]);
require_once __DIR__ . '/../includes/admin_layout.php';
rendreEnteteAdmin('Clients', 'clients', (int) $notifications->fetchColumn());
?>
<section class="page-heading"><div><span class="eyebrow">COMPTES CLIENTS</span><h1>Clients</h1><p>Comptes clients inscrits. Aucun mot de passe ni donnée sensible n’est affiché.</p></div><span class="heading-period"><?= e(count($clients)) ?> compte<?= count($clients) === 1 ? '' : 's' ?></span></section>
<?php if ($erreur !== ''): ?><div class="alert alert-danger dashboard-alert" role="alert"><?= e($erreur) ?></div><?php endif; ?>
<section class="panel module-panel">
    <?php if (!$clients && $erreur === ''): ?><div class="module-empty"><strong>Aucun compte client</strong><p>Les inscriptions facultatives apparaîtront ici.</p></div>
    <?php elseif ($clients): ?><div class="table-scroll"><table class="module-table"><thead><tr><th>Client</th><th>Adresse e-mail</th><th>Téléphone</th><th>Inscription</th><th>Ventes associées</th></tr></thead><tbody>
        <?php foreach ($clients as $client): ?><tr><td><strong><?= e(trim((string) $client['prenom'] . ' ' . (string) $client['nom'])) ?></strong></td><td><?= e($client['email']) ?></td><td><?= e($client['telephone'] ?: '—') ?></td><td><?= e((new DateTimeImmutable((string) $client['date_creation']))->format('d/m/Y')) ?></td><td><?= e((int) $client['ventes_associees']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php rendrePiedAdmin(); ?>

<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
exigerAdministrateur();
$pdo = connexionPDO();
try {
    $ventes = $pdo->query(
        'SELECT v.id, v.date_vente, v.montant_total, COUNT(d.id) AS lignes,
                COALESCE(SUM(d.quantite), 0) AS unites
         FROM ventes v LEFT JOIN vente_details d ON d.vente_id = v.id
         GROUP BY v.id, v.date_vente, v.montant_total
         ORDER BY v.date_vente DESC, v.id DESC LIMIT 200'
    )->fetchAll();
    $detailsQuery = $pdo->prepare('SELECT nom_medicament, quantite, prix_unitaire FROM vente_details WHERE vente_id = :id ORDER BY id');
    $details = [];
    foreach ($ventes as $vente) {
        $detailsQuery->execute(['id' => (int) $vente['id']]);
        $details[(int) $vente['id']] = $detailsQuery->fetchAll();
    }
    $erreur = '';
} catch (PDOException $exception) {
    error_log('PharmaCore sales history failed: ' . $exception->getMessage());
    $ventes = [];
    $details = [];
    $erreur = 'L’historique des ventes est momentanément indisponible.';
}
$notifications = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE utilisateur_id = :id AND est_lue = 0');
$notifications->execute(['id' => (int) $_SESSION['admin_id']]);
require_once __DIR__ . '/../includes/admin_layout.php';
rendreEnteteAdmin('Historique des ventes', 'historique', (int) $notifications->fetchColumn());
?>
<section class="page-heading"><div><span class="eyebrow">JOURNAL DES TRANSACTIONS</span><h1>Historique des ventes</h1><p>Les noms et prix ci-dessous sont ceux enregistrés au moment de chaque vente.</p></div><a class="button button-primary" href="ventes.php">＋ Nouvelle vente</a></section>
<?php if (isset($_GET['created'])): ?><div class="module-feedback" role="status">Vente enregistrée avec succès. Le stock a été déduit.</div><?php endif; ?>
<?php if ($erreur !== ''): ?><div class="alert alert-danger dashboard-alert" role="alert"><?= e($erreur) ?></div><?php endif; ?>
<section class="panel module-panel">
    <?php if (!$ventes && $erreur === ''): ?><div class="module-empty"><strong>Aucune vente enregistrée</strong><p>Une fois les ventes saisies, elles apparaîtront ici avec leurs lignes détaillées.</p><a class="button button-primary" href="ventes.php">Commencer une vente</a></div>
    <?php elseif ($ventes): ?><div class="table-scroll"><table class="module-table"><thead><tr><th>Référence</th><th>Date</th><th>Articles</th><th>Unités</th><th class="align-right">Total</th><th>Détails</th></tr></thead><tbody>
        <?php foreach ($ventes as $vente): $venteId = (int) $vente['id']; ?>
        <tr><td><strong>#<?= e(str_pad((string) $venteId, 5, '0', STR_PAD_LEFT)) ?></strong></td><td><?= e((new DateTimeImmutable((string) $vente['date_vente']))->format('d/m/Y · H:i')) ?></td><td><?= e((int) $vente['lignes']) ?></td><td><?= e((int) $vente['unites']) ?></td><td class="align-right amount-cell"><?= e(number_format((float) $vente['montant_total'], 0, ',', "\u{202F}")) ?> BIF</td><td><details class="sale-details"><summary>Voir les lignes</summary><ul><?php foreach ($details[$venteId] as $ligne): ?><li><?= e($ligne['nom_medicament']) ?> × <?= e((int) $ligne['quantite']) ?> — <?= e(number_format((float) $ligne['prix_unitaire'], 0, ',', "\u{202F}")) ?> BIF</li><?php endforeach; ?></ul></details></td></tr>
        <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php rendrePiedAdmin(); ?>

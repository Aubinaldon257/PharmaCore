<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
exigerAdministrateur();
$pdo = connexionPDO();
$erreur = '';
try {
    $debut = (new DateTimeImmutable('first day of this month'))->modify('-11 months');
    $fin = (new DateTimeImmutable('first day of next month'));
    $requete = $pdo->prepare(
        "SELECT DATE_FORMAT(date_vente, '%Y-%m-01') AS mois, COUNT(*) AS ventes,
                COALESCE(SUM(montant_total), 0) AS chiffre_affaires
         FROM ventes WHERE date_vente >= :debut AND date_vente < :fin
         GROUP BY DATE_FORMAT(date_vente, '%Y-%m-01') ORDER BY mois"
    );
    $requete->execute(['debut' => $debut->format('Y-m-d 00:00:00'), 'fin' => $fin->format('Y-m-d 00:00:00')]);
    $donnees = [];
    foreach ($requete->fetchAll() as $ligne) {
        $donnees[(string) $ligne['mois']] = $ligne;
    }
    $rapport = [];
    $totalVentes = 0;
    $totalCA = 0.0;
    for ($date = $debut; $date < $fin; $date = $date->modify('+1 month')) {
        $key = $date->format('Y-m-01');
        $ligne = $donnees[$key] ?? ['ventes' => 0, 'chiffre_affaires' => 0];
        $ligne['mois'] = $key;
        $ligne['ventes'] = (int) $ligne['ventes'];
        $ligne['chiffre_affaires'] = (float) $ligne['chiffre_affaires'];
        $totalVentes += $ligne['ventes'];
        $totalCA += $ligne['chiffre_affaires'];
        $rapport[] = $ligne;
    }
    $erreurStock = false;
    $stock = $pdo->query(
        'SELECT COUNT(*) AS references_total,
                COALESCE(SUM(quantite), 0) AS unites_total,
                SUM(CASE WHEN quantite <= 5 THEN 1 ELSE 0 END) AS references_a_surveiller
         FROM medicaments WHERE COALESCE(description, \'\') NOT LIKE \'DEMO DATA:%\''
    )->fetch();
} catch (PDOException $exception) {
    error_log('PharmaCore report query failed: ' . $exception->getMessage());
    $rapport = [];
    $totalVentes = 0;
    $totalCA = 0.0;
    $stock = ['references_total' => 0, 'unites_total' => 0, 'references_a_surveiller' => 0];
    $erreur = 'Les rapports ne sont pas disponibles pour le moment.';
}
$notifications = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE utilisateur_id = :id AND est_lue = 0');
$notifications->execute(['id' => (int) $_SESSION['admin_id']]);
$moisFrancais = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
require_once __DIR__ . '/../includes/admin_layout.php';
rendreEnteteAdmin('Rapports', 'rapports', (int) $notifications->fetchColumn());
?>
<section class="page-heading"><div><span class="eyebrow">SYNTHÈSE D’ACTIVITÉ</span><h1>Rapports</h1><p>Ventes mensuelles enregistrées et inventaire actuel · montants en BIF.</p></div><span class="heading-period">12 derniers mois</span></section>
<?php if ($erreur !== ''): ?><div class="alert alert-danger dashboard-alert" role="alert"><?= e($erreur) ?></div><?php endif; ?>
<section class="kpi-grid report-kpis">
    <article class="kpi-card"><span class="kpi-caption">VENTES</span><div class="kpi-value"><?= e(number_format($totalVentes, 0, ',', "\u{202F}")) ?></div><div class="kpi-label">Transactions sur 12 mois</div></article>
    <article class="kpi-card"><span class="kpi-caption">CHIFFRE D’AFFAIRES</span><div class="kpi-value kpi-value-currency"><?= e(number_format($totalCA, 0, ',', "\u{202F}")) ?> BIF</div><div class="kpi-label">Total des ventes enregistrées</div></article>
    <article class="kpi-card"><span class="kpi-caption">INVENTAIRE</span><div class="kpi-value"><?= e((int) $stock['references_total']) ?></div><div class="kpi-label">Références · <?= e((int) $stock['unites_total']) ?> unités</div><span class="kpi-footnote"><?= e((int) $stock['references_a_surveiller']) ?> références à 5 unités ou moins</span></article>
</section>
<section class="panel module-panel report-panel">
    <div class="panel-heading"><div><span class="eyebrow">ÉVOLUTION</span><h2>Activité mensuelle</h2></div><span class="panel-kicker">Une ligne par mois, sans projection</span></div>
    <?php if (!$rapport): ?><div class="module-empty">Aucune donnée de rapport disponible.</div>
    <?php else: ?><div class="table-scroll"><table class="module-table"><thead><tr><th>Mois</th><th>Ventes</th><th class="align-right">Chiffre d’affaires</th></tr></thead><tbody>
        <?php foreach (array_reverse($rapport) as $ligne): $dateMois = new DateTimeImmutable((string) $ligne['mois']); ?><tr><td><strong><?= e($moisFrancais[(int) $dateMois->format('n')] . ' ' . $dateMois->format('Y')) ?></strong></td><td><?= e($ligne['ventes']) ?></td><td class="align-right amount-cell"><?= e(number_format($ligne['chiffre_affaires'], 0, ',', "\u{202F}")) ?> BIF</td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php rendrePiedAdmin(); ?>

<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
exigerAdministrateur();

$pdo = connexionPDO();
$erreur = '';
try {
    $medicaments = $pdo->query(
        'SELECT id, nom, categorie, description, prix, quantite, date_expiration
         FROM medicaments ORDER BY nom ASC, id ASC'
    )->fetchAll();
    $notifications = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE utilisateur_id = :id AND est_lue = 0');
    $notifications->execute(['id' => (int) $_SESSION['admin_id']]);
    $notificationsNonLues = (int) $notifications->fetchColumn();
} catch (PDOException $exception) {
    error_log('PharmaCore medicine list failed: ' . $exception->getMessage());
    $medicaments = [];
    $notificationsNonLues = 0;
    $erreur = 'Le catalogue est momentanément indisponible.';
}

require_once __DIR__ . '/../includes/admin_layout.php';
rendreEnteteAdmin('Médicaments', 'medicaments', $notificationsNonLues);
?>
<section class="page-heading">
    <div><span class="eyebrow">CATALOGUE PHARMACEUTIQUE</span><h1>Médicaments</h1><p>Gérez les références, les prix en BIF et les quantités disponibles.</p></div>
    <a class="button button-primary" href="ajouter_medicament.php">＋ Ajouter un médicament</a>
</section>
<?php if (isset($_GET['saved'])): ?><div class="module-feedback" role="status">Le médicament a été enregistré.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="module-feedback" role="status">Le médicament a été supprimé du catalogue.</div><?php endif; ?>
<?php if (($_GET['error'] ?? '') === 'sold'): ?><div class="alert alert-danger dashboard-alert" role="alert">Cette référence est liée à des ventes et ne peut pas être supprimée. Son historique est conservé.</div><?php endif; ?>
<?php if ($erreur !== ''): ?><div class="alert alert-danger dashboard-alert" role="alert"><?= e($erreur) ?></div><?php endif; ?>
<section class="panel module-panel">
    <div class="module-toolbar"><div><strong><?= e(count($medicaments)) ?> référence<?= count($medicaments) === 1 ? '' : 's' ?></strong><span>Les produits déjà vendus ne peuvent pas être supprimés afin de préserver l’historique.</span></div><label class="module-search"><span class="visually-hidden">Rechercher un médicament</span><input type="search" id="medicineSearch" placeholder="Rechercher…" autocomplete="off"></label></div>
    <?php if ($medicaments === [] && $erreur === ''): ?>
        <div class="module-empty"><span class="empty-icon">Rx</span><strong>Votre catalogue est vide</strong><p>Ajoutez vos médicaments avec leurs prix en francs burundais (BIF) et quantités réelles.</p><a class="button button-primary" href="ajouter_medicament.php">Ajouter une référence</a></div>
    <?php elseif ($medicaments !== []): ?>
        <div class="table-scroll"><table class="module-table" id="medicineTable">
            <thead><tr><th>Médicament</th><th>Catégorie</th><th>Prix unitaire</th><th>Quantité</th><th>Expiration</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($medicaments as $medicament): ?>
                <tr data-search="<?= e(mb_strtolower((string) $medicament['nom'] . ' ' . (string) $medicament['categorie'], 'UTF-8')) ?>">
                    <td><strong><?= e($medicament['nom']) ?></strong><?php if (str_starts_with((string) ($medicament['description'] ?? ''), 'DEMO DATA:')): ?><small class="demo-label">Démonstration · stock non réel</small><?php endif; ?></td><td><?= e($medicament['categorie'] ?: '—') ?></td>
                    <td class="amount-cell"><?= e(number_format((float) $medicament['prix'], 0, ',', "\u{202F}")) ?> BIF</td>
                    <td><span class="stock-pill<?= (int) $medicament['quantite'] < 1 ? ' stock-empty' : ((int) $medicament['quantite'] <= 5 ? ' stock-low' : '') ?>"><?= e((int) $medicament['quantite']) ?></span></td>
                    <td><?= $medicament['date_expiration'] ? e((new DateTimeImmutable((string) $medicament['date_expiration']))->format('d/m/Y')) : 'Non renseignée' ?></td>
                    <td class="module-actions"><a class="button button-filter" href="modifier_medicament.php?id=<?= e((int) $medicament['id']) ?>">Modifier</a><form method="post" action="supprimer_medicament.php" onsubmit="return confirm('Supprimer cette référence du catalogue ?');"><input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>"><input type="hidden" name="id" value="<?= e((int) $medicament['id']) ?>"><button class="button button-danger" type="submit">Supprimer</button></form></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <p class="module-no-results" id="medicineNoResults" hidden>Aucun médicament ne correspond à votre recherche.</p>
    <?php endif; ?>
</section>
<script>
document.getElementById('medicineSearch')?.addEventListener('input', (event) => {
    const term = event.target.value.trim().toLocaleLowerCase('fr');
    let shown = 0;
    document.querySelectorAll('#medicineTable tbody tr').forEach((row) => {
        const visible = row.dataset.search.includes(term);
        row.hidden = !visible;
        shown += visible ? 1 : 0;
    });
    const noResults = document.getElementById('medicineNoResults');
    if (noResults) noResults.hidden = shown !== 0;
});
</script>
<?php rendrePiedAdmin(); ?>

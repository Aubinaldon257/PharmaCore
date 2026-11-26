<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
exigerAdministrateur();

$pdo = connexionPDO();
$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $quantite = filter_var($_POST['quantite'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if (!verifierJetonCsrf() || $id === false || $quantite === false) {
        $erreur = 'La mise à jour est invalide ou votre session a expiré.';
    } else {
        try {
            $update = $pdo->prepare('UPDATE medicaments SET quantite = :quantite WHERE id = :id');
            $update->execute(['quantite' => $quantite, 'id' => $id]);
            if ($update->rowCount() === 0) {
                $check = $pdo->prepare('SELECT 1 FROM medicaments WHERE id = :id');
                $check->execute(['id' => $id]);
                if (!$check->fetchColumn()) {
                    $erreur = 'Ce médicament n’existe plus dans le catalogue.';
                } else {
                    rediriger('stock.php?saved=1');
                }
            } else {
                rediriger('stock.php?saved=1');
            }
        } catch (PDOException $exception) {
            error_log('PharmaCore stock update failed: ' . $exception->getMessage());
            $erreur = 'La quantité n’a pas pu être mise à jour.';
        }
    }
}
try {
    $medicaments = $pdo->query(
        'SELECT id, nom, categorie, quantite, date_expiration FROM medicaments
         WHERE COALESCE(description, \'\') NOT LIKE \'DEMO DATA:%\'
         ORDER BY CASE WHEN quantite = 0 THEN 0 WHEN quantite <= 5 THEN 1 ELSE 2 END, quantite, nom'
    )->fetchAll();
    $stockCritique = count(array_filter($medicaments, static fn(array $item): bool => (int) $item['quantite'] <= 5));
} catch (PDOException $exception) {
    error_log('PharmaCore stock list failed: ' . $exception->getMessage());
    $medicaments = [];
    $stockCritique = 0;
    $erreur = 'Les niveaux de stock ne sont pas disponibles.';
}
$notifications = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE utilisateur_id = :id AND est_lue = 0');
$notifications->execute(['id' => (int) $_SESSION['admin_id']]);
require_once __DIR__ . '/../includes/admin_layout.php';
rendreEnteteAdmin('Gestion du stock', 'stock', (int) $notifications->fetchColumn());
?>
<section class="page-heading"><div><span class="eyebrow">INVENTAIRE ACTUEL</span><h1>Gestion du stock</h1><p><?= e($stockCritique) ?> référence<?= $stockCritique === 1 ? '' : 's' ?> à réapprovisionner (seuil d’alerte : 5 unités).</p></div><a class="button button-filter" href="medicaments.php">Catalogue complet</a></section>
<?php if (isset($_GET['saved'])): ?><div class="module-feedback" role="status">Le stock a été mis à jour.</div><?php endif; ?>
<?php if ($erreur !== ''): ?><div class="alert alert-danger dashboard-alert" role="alert"><?= e($erreur) ?></div><?php endif; ?>
<div class="insight-note stock-note">Les quantités enregistrées doivent correspondre au stock physique vérifié. Le schéma actuel ne conserve pas d’historique des mouvements.</div>
<section class="panel module-panel">
    <?php if (!$medicaments && $erreur === ''): ?><div class="module-empty"><strong>Aucun article en stock</strong><p>Ajoutez vos références au catalogue pour gérer l’inventaire.</p><a class="button button-primary" href="ajouter_medicament.php">Ajouter un médicament</a></div>
    <?php elseif ($medicaments): ?>
        <div class="table-scroll"><table class="module-table"><thead><tr><th>Médicament</th><th>Expiration</th><th>État</th><th>Quantité physique</th><th>Mettre à jour</th></tr></thead><tbody>
        <?php foreach ($medicaments as $medicament): $quantity = (int) $medicament['quantite']; ?>
            <tr><td><strong><?= e($medicament['nom']) ?></strong><small class="table-muted"><?= e($medicament['categorie'] ?: 'Sans catégorie') ?></small></td>
                <td><?= $medicament['date_expiration'] ? e((new DateTimeImmutable((string) $medicament['date_expiration']))->format('d/m/Y')) : 'Non renseignée' ?></td>
                <td><span class="stock-pill<?= $quantity === 0 ? ' stock-empty' : ($quantity <= 5 ? ' stock-low' : '') ?>"><?= $quantity === 0 ? 'Rupture' : ($quantity <= 5 ? 'Stock bas' : 'Disponible') ?></span></td>
                <td><?= e($quantity) ?> unité<?= $quantity === 1 ? '' : 's' ?></td>
                <td><form class="stock-update-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>"><input type="hidden" name="id" value="<?= e((int) $medicament['id']) ?>"><label class="visually-hidden" for="qty-<?= e((int) $medicament['id']) ?>">Nouvelle quantité pour <?= e($medicament['nom']) ?></label><input id="qty-<?= e((int) $medicament['id']) ?>" type="number" name="quantite" min="0" step="1" value="<?= e($quantity) ?>" required><button class="button button-filter" type="submit">Enregistrer</button></form></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php rendrePiedAdmin(); ?>

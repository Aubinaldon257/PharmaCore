<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/medicament.php';
exigerAdministrateur();

$id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    http_response_code(400);
    exit('Référence de médicament invalide.');
}
$pdo = connexionPDO();
$requete = $pdo->prepare('SELECT id, nom, categorie, description, prix, quantite, date_expiration FROM medicaments WHERE id = :id');
$requete->execute(['id' => $id]);
$valeurs = $requete->fetch();
if (!is_array($valeurs)) {
    http_response_code(404);
    exit('Médicament introuvable.');
}
$estDemonstration = str_starts_with((string) ($valeurs['description'] ?? ''), 'DEMO DATA:');
$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierJetonCsrf()) {
        $erreur = 'Votre session a expiré. Actualisez la page puis réessayez.';
    } else {
        try {
            if ($estDemonstration) {
                $_POST['description'] = (string) $valeurs['description'];
            }
            $medicament = validerMedicament($_POST);
            $medicament['id'] = (int) $id;
            $update = $pdo->prepare(
                'UPDATE medicaments SET nom = :nom, categorie = :categorie, description = :description,
                    prix = :prix, quantite = :quantite, date_expiration = :date_expiration WHERE id = :id'
            );
            $update->execute($medicament);
            rediriger('medicaments.php?saved=1');
        } catch (InvalidArgumentException $exception) {
            $erreur = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('PharmaCore medicine update failed: ' . $exception->getMessage());
            $erreur = 'Le médicament n’a pas pu être modifié. Réessayez.';
        }
    }
    foreach (['nom', 'categorie', 'description', 'prix', 'quantite', 'date_expiration'] as $champ) {
        if (isset($_POST[$champ]) && is_string($_POST[$champ])) {
            $valeurs[$champ] = $_POST[$champ];
        }
    }
}
$notifications = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE utilisateur_id = :id AND est_lue = 0');
$notifications->execute(['id' => (int) $_SESSION['admin_id']]);
require_once __DIR__ . '/../includes/admin_layout.php';
rendreEnteteAdmin('Modifier un médicament', 'medicaments', (int) $notifications->fetchColumn());
?>
<section class="page-heading"><div><span class="eyebrow">CATALOGUE · RÉFÉRENCE #<?= e((int) $id) ?></span><h1>Modifier le médicament</h1><p>Les modifications des informations du catalogue ne réécrivent pas les lignes de ventes passées.</p></div></section>
<?php if ($erreur !== ''): ?><div class="alert alert-danger dashboard-alert" role="alert"><?= e($erreur) ?></div><?php endif; ?>
<section class="panel module-form-panel">
    <form method="post" action="modifier_medicament.php?id=<?= e((int) $id) ?>" class="module-form">
        <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>"><input type="hidden" name="id" value="<?= e((int) $id) ?>">
        <div class="form-grid">
            <label>Nom du médicament<input name="nom" maxlength="150" value="<?= e($valeurs['nom']) ?>" required></label>
            <label>Catégorie <span class="optional-label">(facultatif)</span><input name="categorie" maxlength="100" value="<?= e($valeurs['categorie']) ?>"></label>
            <label>Prix unitaire (BIF)<input name="prix" type="number" min="0" max="99999999" step="1" inputmode="numeric" value="<?= e((int) $valeurs['prix']) ?>" required></label>
            <label>Quantité en stock<input name="quantite" type="number" min="0" step="1" value="<?= e((int) $valeurs['quantite']) ?>" required></label>
            <label>Date d’expiration <span class="optional-label">(facultatif)</span><input name="date_expiration" type="date" value="<?= e($valeurs['date_expiration'] ?? '') ?>"></label>
            <label class="form-span-all">Description <span class="optional-label">(facultatif)</span><textarea name="description" rows="4" maxlength="65535"<?= $estDemonstration ? ' readonly aria-describedby="demo-description-hint"' : '' ?>><?= e($valeurs['description'] ?? '') ?></textarea><?php if ($estDemonstration): ?><small id="demo-description-hint">Cette référence reste une démonstration et n’apparaît pas dans les ventes, même si son prix ou sa quantité sont modifiés.</small><?php endif; ?></label>
        </div>
        <div class="module-form-footer"><a class="back-link" href="medicaments.php">Annuler</a><button class="button button-primary" type="submit">Enregistrer les modifications</button></div>
    </form>
</section>
<?php rendrePiedAdmin(); ?>

<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/medicament.php';
exigerAdministrateur();

$erreur = '';
$valeurs = ['nom' => '', 'categorie' => '', 'description' => '', 'prix' => '', 'quantite' => '0', 'date_expiration' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($valeurs as $champ => $valeur) {
        if (isset($_POST[$champ]) && is_string($_POST[$champ])) {
            $valeurs[$champ] = $_POST[$champ];
        }
    }
    if (!verifierJetonCsrf()) {
        $erreur = 'Votre session a expiré. Actualisez la page puis réessayez.';
    } else {
        try {
            $medicament = validerMedicament($_POST);
            $requete = connexionPDO()->prepare(
                'INSERT INTO medicaments (nom, categorie, description, prix, quantite, date_expiration)
                 VALUES (:nom, :categorie, :description, :prix, :quantite, :date_expiration)'
            );
            $requete->execute($medicament);
            rediriger('medicaments.php?saved=1');
        } catch (InvalidArgumentException $exception) {
            $erreur = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('PharmaCore medicine creation failed: ' . $exception->getMessage());
            $erreur = 'Le médicament n’a pas pu être enregistré. Vérifiez les données puis réessayez.';
        }
    }
}

$notifications = connexionPDO()->prepare('SELECT COUNT(*) FROM notifications WHERE utilisateur_id = :id AND est_lue = 0');
$notifications->execute(['id' => (int) $_SESSION['admin_id']]);
require_once __DIR__ . '/../includes/admin_layout.php';
rendreEnteteAdmin('Ajouter un médicament', 'medicaments', (int) $notifications->fetchColumn());
?>
<section class="page-heading"><div><span class="eyebrow">CATALOGUE</span><h1>Ajouter un médicament</h1><p>Les quantités et prix saisis doivent correspondre à votre stock réel.</p></div></section>
<?php if ($erreur !== ''): ?><div class="alert alert-danger dashboard-alert" role="alert"><?= e($erreur) ?></div><?php endif; ?>
<section class="panel module-form-panel">
    <form method="post" action="ajouter_medicament.php" class="module-form">
        <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
        <div class="form-grid">
            <label>Nom du médicament<input name="nom" maxlength="150" value="<?= e($valeurs['nom']) ?>" required></label>
            <label>Catégorie <span class="optional-label">(facultatif)</span><input name="categorie" maxlength="100" value="<?= e($valeurs['categorie']) ?>"></label>
            <label>Prix unitaire (BIF)<input name="prix" type="number" min="0" max="99999999" step="1" inputmode="numeric" value="<?= e($valeurs['prix']) ?>" required><small>Saisir le prix en francs burundais, sans décimales.</small></label>
            <label>Quantité en stock<input name="quantite" type="number" min="0" step="1" inputmode="numeric" value="<?= e($valeurs['quantite']) ?>" required></label>
            <label>Date d’expiration <span class="optional-label">(facultatif)</span><input name="date_expiration" type="date" value="<?= e($valeurs['date_expiration']) ?>"></label>
            <label class="form-span-all">Description <span class="optional-label">(facultatif)</span><textarea name="description" rows="4" maxlength="65535"><?= e($valeurs['description']) ?></textarea></label>
        </div>
        <div class="module-form-footer"><a class="back-link" href="medicaments.php">Annuler</a><button class="button button-primary" type="submit">Enregistrer le médicament</button></div>
    </form>
</section>
<?php rendrePiedAdmin(); ?>

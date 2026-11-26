<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
exigerAdministrateur();

$erreur = '';
$prenom = '';
$nom = '';
$email = '';
$creationReussie = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenom = is_string($_POST['prenom'] ?? null) ? trim($_POST['prenom']) : '';
    $nom = is_string($_POST['nom'] ?? null) ? trim($_POST['nom']) : '';
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $motDePasse = is_string($_POST['mot_de_passe'] ?? null) ? $_POST['mot_de_passe'] : '';
    $confirmation = is_string($_POST['confirmation'] ?? null) ? $_POST['confirmation'] : '';

    if (!verifierJetonCsrf()) {
        $erreur = 'Votre session a expiré. Actualisez la page puis réessayez.';
    } elseif (
        $prenom === ''
        || mb_strlen($prenom, 'UTF-8') > 100
        || $nom === ''
        || mb_strlen($nom, 'UTF-8') > 100
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 150
        || strlen($motDePasse) < 12
        || strlen($motDePasse) > 72
        || $motDePasse !== $confirmation
    ) {
        $erreur = 'Vérifiez les champs : le mot de passe doit compter au moins 12 caractères et les deux saisies doivent correspondre.';
    } else {
        try {
            $creation = connexionPDO()->prepare(
                "INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role)
                 VALUES (:nom, :prenom, :email, :mot_de_passe, 'admin')"
            );
            $creation->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'mot_de_passe' => password_hash($motDePasse, PASSWORD_DEFAULT),
            ]);
            $creationReussie = true;
        } catch (PDOException $exception) {
            error_log('PharmaCore administrator creation failed: ' . $exception->getMessage());
            $erreur = 'Le compte administrateur n’a pas pu être créé. Vérifiez notamment que l’adresse e-mail n’est pas déjà utilisée.';
        }

    }
}

if ($creationReussie) {
    header('Location: administrateurs.php?created=1');
    exit;
}

try {
    $notifications = connexionPDO()->prepare(
        'SELECT COUNT(*) FROM notifications WHERE utilisateur_id = :id AND est_lue = 0'
    );
    $notifications->execute(['id' => (int) $_SESSION['admin_id']]);
    $notificationsNonLues = (int) $notifications->fetchColumn();
} catch (PDOException $exception) {
    error_log('PharmaCore admin notification count failed: ' . $exception->getMessage());
    $notificationsNonLues = 0;
}

require_once __DIR__ . '/../includes/admin_layout.php';
rendreEnteteAdmin('Administrateurs', 'administrateurs', $notificationsNonLues);
?>
<section class="page-heading">
    <div>
        <span class="eyebrow">GESTION DES ACCÈS</span>
        <h1>Créer un administrateur</h1>
        <p>Réservez cette action aux personnes autorisées à gérer la pharmacie.</p>
    </div>
</section>

<?php if (isset($_GET['created'])): ?>
    <div class="module-feedback" role="status">Le compte administrateur a été créé. Son titulaire peut maintenant se connecter.</div>
<?php endif; ?>
<?php if ($erreur !== ''): ?>
    <div class="alert alert-danger dashboard-alert" role="alert"><?= e($erreur) ?></div>
<?php endif; ?>

<section class="panel module-form-panel admin-account-panel">
    <div class="admin-account-intro">
        <span class="admin-account-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M19 8v6m-3-3h6"/></svg>
        </span>
        <div>
            <h2>Nouvel accès administrateur</h2>
            <p>Le mot de passe sera haché avant son enregistrement. Aucun mot de passe ne sera envoyé par e-mail.</p>
        </div>
    </div>
    <form method="post" action="administrateurs.php" class="module-form admin-account-form" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
        <div class="form-grid">
            <label for="prenom">Prénom
                <input id="prenom" name="prenom" maxlength="100" autocomplete="given-name" required value="<?= e($prenom) ?>">
            </label>
            <label for="nom">Nom
                <input id="nom" name="nom" maxlength="100" autocomplete="family-name" required value="<?= e($nom) ?>">
            </label>
            <label class="form-span-all" for="email">Adresse e-mail professionnelle
                <input id="email" name="email" type="email" maxlength="150" autocomplete="email" required value="<?= e($email) ?>">
            </label>
            <label for="mot_de_passe">Mot de passe (12 caractères minimum)
                <input id="mot_de_passe" name="mot_de_passe" type="password" minlength="12" maxlength="72" autocomplete="new-password" required>
                <small>Utilisez un mot de passe unique, d’au moins 12 caractères et au plus 72 octets.</small>
            </label>
            <label for="confirmation">Confirmer le mot de passe
                <input id="confirmation" name="confirmation" type="password" minlength="12" maxlength="72" autocomplete="new-password" required>
                <small>La confirmation doit correspondre exactement au mot de passe.</small>
            </label>
        </div>
        <div class="module-form-footer">
            <span class="admin-account-security">Création réservée aux administrateurs connectés.</span>
            <button class="button button-primary" type="submit">Créer le compte admin <span aria-hidden="true">→</span></button>
        </div>
    </form>
</section>
<?php rendrePiedAdmin(); ?>

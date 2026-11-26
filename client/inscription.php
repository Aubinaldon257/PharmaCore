<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';

if (
    isset($_SESSION['admin_id'], $_SESSION['admin_role'])
    && is_int($_SESSION['admin_id'])
    && $_SESSION['admin_role'] === 'admin'
) {
    rediriger('../admin/dashboard.php');
}

if (
    isset($_SESSION['client_id'], $_SESSION['client_role'])
    && is_int($_SESSION['client_id'])
    && $_SESSION['client_role'] === 'client'
) {
    rediriger('profil.php');
}

$erreur = '';
$prenom = '';
$nom = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenom = is_string($_POST['prenom'] ?? null) ? trim($_POST['prenom']) : '';
    $nom = is_string($_POST['nom'] ?? null) ? trim($_POST['nom']) : '';
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $telephone = is_string($_POST['telephone'] ?? null) ? trim($_POST['telephone']) : '';
    $motDePasse = is_string($_POST['mot_de_passe'] ?? null) ? $_POST['mot_de_passe'] : '';
    $confirmation = is_string($_POST['confirmation'] ?? null) ? $_POST['confirmation'] : '';

    if (!verifierJetonCsrf()) {
        $erreur = 'Votre session a expiré. Actualisez la page puis réessayez.';
    } elseif (
        $prenom === ''
        || mb_strlen($prenom) > 100
        || $nom === ''
        || mb_strlen($nom) > 100
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 150
        || mb_strlen($telephone) > 30
        || mb_strlen($motDePasse, 'UTF-8') < 8
        || strlen($motDePasse) > 72
        || $motDePasse !== $confirmation
    ) {
        $erreur = 'Vérifiez vos informations et choisissez un mot de passe de 8 caractères minimum (72 octets maximum).';
    } else {
        try {
            $insertion = connexionPDO()->prepare(
                "INSERT INTO utilisateurs (nom, prenom, email, telephone, mot_de_passe, role)
                 VALUES (:nom, :prenom, :email, :telephone, :mot_de_passe, 'client')"
            );
            $insertion->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone !== '' ? $telephone : null,
                'mot_de_passe' => password_hash($motDePasse, PASSWORD_DEFAULT),
            ]);

            session_regenerate_id(true);
            unset($_SESSION['csrf_token']);
            $_SESSION['client_id'] = (int) connexionPDO()->lastInsertId();
            $_SESSION['client_role'] = 'client';
            $_SESSION['client_name'] = $prenom . ' ' . $nom;
            $_SESSION['client_email'] = $email;
            rediriger('profil.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $erreur = 'Un compte utilise déjà cette adresse e-mail. Essayez de vous connecter.';
            } else {
                error_log('PharmaCore client registration failed: ' . $exception->getMessage());
                $erreur = 'La création du compte est momentanément indisponible. Réessayez dans quelques instants.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f8f6">
    <title>Créer un compte · PharmaCore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="login-page">
    <main class="setup-card">
        <a class="brand" href="../index.php"><span class="brand-mark" aria-hidden="true">+</span><span>Pharma<span class="brand-light">Core</span></span></a>
        <span class="eyebrow">ESPACE CLIENT</span>
        <h1>Créer votre compte</h1>
        <p class="muted">La création d’un compte est facultative pour consulter les informations de la pharmacie.</p>
        <?php if ($erreur !== ''): ?><div class="alert alert-danger" role="alert"><?= e($erreur) ?></div><?php endif; ?>
        <form method="post" action="inscription.php" class="login-form" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
            <div class="setup-name-fields">
                <div><label for="prenom">Prénom</label><input id="prenom" name="prenom" maxlength="100" autocomplete="given-name" required value="<?= e($prenom) ?>"></div>
                <div><label for="nom">Nom</label><input id="nom" name="nom" maxlength="100" autocomplete="family-name" required value="<?= e($nom) ?>"></div>
            </div>
            <label for="email">Adresse e-mail</label>
            <input id="email" name="email" type="email" maxlength="150" autocomplete="email" required value="<?= e($email) ?>">
            <label for="telephone">Téléphone <span class="optional-label">Facultatif</span></label>
            <input id="telephone" name="telephone" type="tel" maxlength="30" autocomplete="tel" value="<?= e($telephone ?? '') ?>">
            <label for="mot_de_passe">Mot de passe (8 caractères minimum)</label>
            <input id="mot_de_passe" name="mot_de_passe" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
            <label for="confirmation">Confirmer le mot de passe</label>
            <input id="confirmation" name="confirmation" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
            <button class="button button-primary button-full" type="submit">Créer mon compte</button>
        </form>
        <p class="account-switch">Déjà inscrit ? <a href="login.php">Se connecter</a></p>
        <a class="login-back" href="../index.php">← Retour à l’accueil</a>
    </main>
</body>
</html>

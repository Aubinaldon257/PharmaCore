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
$email = '';
$message = '';

if (($_SESSION['password_reset_success'] ?? null) === 'client') {
    $message = 'Votre mot de passe a été modifié. Vous pouvez vous connecter.';
    unset($_SESSION['password_reset_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $motDePasse = is_string($_POST['mot_de_passe'] ?? null) ? $_POST['mot_de_passe'] : '';

    if (!verifierJetonCsrf()) {
        $erreur = 'Votre session a expiré. Actualisez la page puis réessayez.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $motDePasse === '') {
        $erreur = 'Adresse e-mail ou mot de passe incorrect.';
    } else {
        try {
            $requete = connexionPDO()->prepare(
                'SELECT id, prenom, nom, email, mot_de_passe, role
                 FROM utilisateurs
                 WHERE email = :email
                 LIMIT 1'
            );
            $requete->execute(['email' => $email]);
            $compte = $requete->fetch();

            if (is_array($compte) && password_verify($motDePasse, (string) $compte['mot_de_passe'])) {
                session_regenerate_id(true);
                unset(
                    $_SESSION['admin_id'],
                    $_SESSION['admin_role'],
                    $_SESSION['admin_name'],
                    $_SESSION['admin_email'],
                    $_SESSION['client_id'],
                    $_SESSION['client_role'],
                    $_SESSION['client_name'],
                    $_SESSION['client_email'],
                    $_SESSION['csrf_token']
                );

                $nomComplet = trim((string) $compte['prenom'] . ' ' . (string) $compte['nom']);

                if ($compte['role'] === 'admin') {
                    $_SESSION['admin_id'] = (int) $compte['id'];
                    $_SESSION['admin_role'] = 'admin';
                    $_SESSION['admin_name'] = $nomComplet;
                    $_SESSION['admin_email'] = (string) $compte['email'];
                    rediriger('../admin/dashboard.php');
                }

                if ($compte['role'] === 'client') {
                    $_SESSION['client_id'] = (int) $compte['id'];
                    $_SESSION['client_role'] = 'client';
                    $_SESSION['client_name'] = $nomComplet;
                    $_SESSION['client_email'] = (string) $compte['email'];
                    rediriger('profil.php');
                }
            }

            $erreur = 'Adresse e-mail ou mot de passe incorrect.';
        } catch (PDOException $exception) {
            error_log('PharmaCore user login failed: ' . $exception->getMessage());
            $erreur = 'La connexion est momentanément indisponible. Réessayez dans quelques instants.';
        }
    }
}

$titre = 'Connexion';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f8f6">
    <title><?= e($titre) ?> · PharmaCore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="login-page">
    <main class="login-layout">
        <section class="login-story" aria-label="Présentation PharmaCore">
            <a class="brand brand-login" href="../index.php">
                <span class="brand-mark" aria-hidden="true">+</span>
                <span>Pharma<span class="brand-light">Core</span></span>
            </a>
            <div class="login-story-copy">
                <span class="eyebrow">VOTRE ESPACE SANTÉ</span>
                <h1>Un espace à vous,<br>en toute simplicité.</h1>
                <p>Connectez-vous à votre espace personnel pour retrouver vos informations. Votre compte est facultatif pour découvrir la pharmacie.</p>
            </div>
            <div class="login-story-note"><span class="status-dot"></span> Les informations de votre compte restent personnelles</div>
            <div class="login-orbit login-orbit-one" aria-hidden="true"></div>
            <div class="login-orbit login-orbit-two" aria-hidden="true"></div>
        </section>

        <section class="login-panel">
            <div class="login-form-wrap">
                <span class="eyebrow">BON RETOUR</span>
                <h2>Connexion</h2>
                <p class="muted">Utilisez l’adresse e-mail et le mot de passe de votre compte.</p>

                <?php if ($erreur !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?= e($erreur) ?></div>
                <?php endif; ?>
                <?php if ($message !== ''): ?>
                    <div class="alert alert-success" role="status"><?= e($message) ?></div>
                <?php endif; ?>

                <form method="post" action="login.php" class="login-form">
                    <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
                    <label for="email">Adresse e-mail</label>
                    <input id="email" name="email" type="email" autocomplete="username" maxlength="150" required value="<?= e($email) ?>" placeholder="vous@exemple.fr">

                    <label for="mot_de_passe">Mot de passe</label>
                    <input id="mot_de_passe" name="mot_de_passe" type="password" autocomplete="current-password" required placeholder="Votre mot de passe">

                    <div class="login-extra-row">
                        <a href="../index.php" class="text-link">Retour à l’accueil</a>
                        <a href="../mot_de_passe_oublie.php?type=client" class="text-link">Mot de passe oublié ?</a>
                    </div>

                    <button class="button button-primary button-full" type="submit">
                        Se connecter <span aria-hidden="true">→</span>
                    </button>
                </form>
                <p class="account-switch">Pas encore de compte ? <a href="inscription.php">Créer un compte client</a></p>
            </div>
            <footer class="login-footer">PharmaCore <span>·</span> Votre pharmacie</footer>
        </section>
    </main>
</body>
</html>

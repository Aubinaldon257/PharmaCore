<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
exigerClient();

$pdo = connexionPDO();
$requeteCompte = $pdo->prepare(
    "SELECT prenom, nom, email, telephone, adresse, date_creation, mot_de_passe
     FROM utilisateurs
     WHERE id = :id AND role = 'client'
     LIMIT 1"
);
$requeteCompte->execute(['id' => (int) $_SESSION['client_id']]);
$compte = $requeteCompte->fetch();

if (!is_array($compte)) {
    unset(
        $_SESSION['client_id'],
        $_SESSION['client_role'],
        $_SESSION['client_name'],
        $_SESSION['client_email']
    );
    rediriger('login.php');
}

$erreur = '';
$messageCompte = '';
if (isset($_SESSION['client_account_message']) && is_string($_SESSION['client_account_message'])) {
    $messageCompte = $_SESSION['client_account_message'];
    unset($_SESSION['client_account_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

    if (!verifierJetonCsrf()) {
        $erreur = 'Votre session a expiré. Actualisez la page puis réessayez.';
    } elseif ($action === 'update_email') {
        $nouvelEmail = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $motDePasseActuel = is_string($_POST['mot_de_passe_actuel'] ?? null)
            ? $_POST['mot_de_passe_actuel']
            : '';

        if (!filter_var($nouvelEmail, FILTER_VALIDATE_EMAIL) || strlen($nouvelEmail) > 150 || $motDePasseActuel === '') {
            $erreur = 'Saisissez une adresse e-mail valide et confirmez votre mot de passe actuel.';
        } elseif (!password_verify($motDePasseActuel, (string) $compte['mot_de_passe'])) {
            $erreur = 'Le mot de passe actuel est incorrect.';
        } elseif (strcasecmp($nouvelEmail, (string) $compte['email']) === 0) {
            $erreur = 'Cette adresse e-mail est déjà celle de votre compte.';
        } else {
            try {
                $requeteEmail = $pdo->prepare(
                    'SELECT id FROM utilisateurs WHERE email = :email AND id <> :id LIMIT 1'
                );
                $requeteEmail->execute([
                    'email' => $nouvelEmail,
                    'id' => (int) $_SESSION['client_id'],
                ]);

                if ($requeteEmail->fetchColumn() !== false) {
                    $erreur = 'Cette adresse e-mail est déjà utilisée par un autre compte.';
                } else {
                    $miseAJour = $pdo->prepare(
                        "UPDATE utilisateurs
                         SET email = :email
                         WHERE id = :id AND role = 'client'"
                    );
                    $miseAJour->execute([
                        'email' => $nouvelEmail,
                        'id' => (int) $_SESSION['client_id'],
                    ]);

                    if ($miseAJour->rowCount() !== 1) {
                        throw new RuntimeException('Le compte client n’a pas pu être mis à jour.');
                    }

                    session_regenerate_id(true);
                    $_SESSION['client_email'] = $nouvelEmail;
                    $_SESSION['client_account_message'] = 'Votre adresse e-mail a été mise à jour.';
                    rediriger('profil.php#email');
                }
            } catch (PDOException | RuntimeException $exception) {
                error_log('PharmaCore client email update failed: ' . $exception->getMessage());
                $erreur = $exception instanceof PDOException && $exception->getCode() === '23000'
                    ? 'Cette adresse e-mail est déjà utilisée par un autre compte.'
                    : 'L’adresse e-mail n’a pas pu être modifiée. Réessayez dans quelques instants.';
            }
        }
    } elseif ($action === 'update_password') {
        $motDePasseActuel = is_string($_POST['mot_de_passe_actuel'] ?? null)
            ? $_POST['mot_de_passe_actuel']
            : '';
        $nouveauMotDePasse = is_string($_POST['nouveau_mot_de_passe'] ?? null)
            ? $_POST['nouveau_mot_de_passe']
            : '';
        $confirmation = is_string($_POST['confirmation'] ?? null)
            ? $_POST['confirmation']
            : '';

        if (
            $motDePasseActuel === ''
            || mb_strlen($nouveauMotDePasse, 'UTF-8') < 8
            || strlen($nouveauMotDePasse) > 72
            || $nouveauMotDePasse !== $confirmation
        ) {
            $erreur = 'Choisissez un mot de passe d’au moins 8 caractères (72 octets maximum) et confirmez-le correctement.';
        } elseif (!password_verify($motDePasseActuel, (string) $compte['mot_de_passe'])) {
            $erreur = 'Le mot de passe actuel est incorrect.';
        } else {
            try {
                $miseAJour = $pdo->prepare(
                    "UPDATE utilisateurs
                     SET mot_de_passe = :mot_de_passe
                     WHERE id = :id AND role = 'client'"
                );
                $miseAJour->execute([
                    'mot_de_passe' => password_hash($nouveauMotDePasse, PASSWORD_DEFAULT),
                    'id' => (int) $_SESSION['client_id'],
                ]);

                if ($miseAJour->rowCount() !== 1) {
                    throw new RuntimeException('Le compte client n’a pas pu être mis à jour.');
                }

                session_regenerate_id(true);
                unset($_SESSION['csrf_token']);
                $_SESSION['client_account_message'] = 'Votre mot de passe a été modifié.';
                rediriger('profil.php#password');
            } catch (PDOException | RuntimeException $exception) {
                error_log('PharmaCore client password update failed: ' . $exception->getMessage());
                $erreur = 'Le mot de passe n’a pas pu être modifié. Réessayez dans quelques instants.';
            }
        }
    } else {
        http_response_code(400);
        $erreur = 'Cette action de compte n’est pas disponible.';
    }
}

$nomClient = trim((string) $compte['prenom'] . ' ' . (string) $compte['nom']);
$initiale = mb_strtoupper(mb_substr((string) $compte['prenom'], 0, 1, 'UTF-8'), 'UTF-8');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f8f6">
    <title>Mon espace · PharmaCore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/account.js" defer></script>
</head>
<body class="login-page">
    <main class="setup-card client-profile-card">
        <div class="client-profile-header">
            <a class="brand" href="../index.php"><span class="brand-mark" aria-hidden="true">+</span><span>Pharma<span class="brand-light">Core</span></span></a>
            <div class="account-menu" data-account-menu>
                <button class="avatar avatar-top account-avatar account-menu-trigger" data-account-avatar data-account-id="<?= e((int) $_SESSION['client_id']) ?>" type="button" aria-label="Ouvrir le menu du compte" aria-haspopup="true" aria-expanded="false" aria-controls="accountMenu"><?= e($initiale) ?></button>
                <div class="account-menu-panel account-menu-panel-client" id="accountMenu" hidden>
                    <div class="account-menu-heading">
                        <span class="account-menu-name"><?= e($nomClient) ?></span>
                        <span class="account-menu-email"><?= e($compte['email']) ?></span>
                        <span class="account-menu-role">Compte client</span>
                    </div>
                    <a href="#profile">Profil et avatar</a>
                    <a href="#email">Adresse e-mail</a>
                    <a href="#password">Mot de passe</a>
                    <form method="post" action="logout.php">
                        <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
                        <button class="account-menu-logout" type="submit">Se déconnecter</button>
                    </form>
                </div>
            </div>
        </div>
        <div id="account-overview">
        <span class="eyebrow">ESPACE PERSONNEL</span>
        <h1>Bonjour <?= e($compte['prenom']) ?></h1>
        <p class="muted">Voici les informations de votre compte PharmaCore.</p>
        </div>
        <?php if ($erreur !== ''): ?><div class="alert alert-danger" role="alert"><?= e($erreur) ?></div><?php endif; ?>
        <?php if ($messageCompte !== ''): ?><div class="settings-success" role="status"><?= e($messageCompte) ?></div><?php endif; ?>
        <dl class="client-profile-details">
            <div><dt>Prénom et nom</dt><dd><?= e($nomClient) ?></dd></div>
            <div><dt>Adresse e-mail</dt><dd><?= e($compte['email']) ?></dd></div>
            <div><dt>Téléphone</dt><dd><?= $compte['telephone'] !== null && $compte['telephone'] !== '' ? e($compte['telephone']) : 'Non renseigné' ?></dd></div>
            <div><dt>Adresse</dt><dd><?= $compte['adresse'] !== null && $compte['adresse'] !== '' ? e($compte['adresse']) : 'Non renseignée' ?></dd></div>
            <div><dt>Compte créé le</dt><dd><?= e((new DateTimeImmutable((string) $compte['date_creation']))->format('d/m/Y')) ?></dd></div>
        </dl>
        <section class="account-settings-section" id="profile">
            <div class="settings-card-heading"><span class="settings-card-icon" aria-hidden="true">✳</span><div><h2>Personnaliser votre avatar</h2><p>Choisissez une illustration PharmaCore enregistrée dans ce navigateur.</p></div></div>
            <div class="profile-avatar-preview">
                <span class="avatar avatar-profile account-avatar" data-account-avatar data-account-id="<?= e((int) $_SESSION['client_id']) ?>"><?= e($initiale) ?></span>
                <div><strong><?= e($nomClient) ?></strong><span><?= e($compte['email']) ?></span></div>
            </div>
            <fieldset class="avatar-picker" data-avatar-picker data-account-id="<?= e((int) $_SESSION['client_id']) ?>">
                <legend>Votre avatar</legend>
                <?php foreach (['leaf' => ['Feuille', '✿'], 'plus' => ['PharmaCore', '+'], 'star' => ['Étoile', '★'], 'sun' => ['Soleil', '☀'], 'heart' => ['Cœur', '♥'], 'shield' => ['Bouclier', '◆']] as $cle => [$nomAvatar, $symboleAvatar]): ?>
                    <label class="avatar-choice"><input type="radio" name="avatar" value="<?= e($cle) ?>" aria-label="Avatar <?= e($nomAvatar) ?>"><span class="avatar avatar-choice-icon" data-avatar="<?= e($cle) ?>" aria-hidden="true"><?= e($symboleAvatar) ?></span><span><?= e($nomAvatar) ?></span></label>
                <?php endforeach; ?>
            </fieldset>
            <p class="avatar-status" id="avatarStatus" role="status">L’avatar est conservé dans ce navigateur et lié à votre compte sur cet appareil.</p>
        </section>
        <section class="account-settings-section" id="email">
            <div class="settings-card-heading"><span class="settings-card-icon" aria-hidden="true">@</span><div><h2>Adresse e-mail</h2><p>Cette adresse sert à vous connecter à PharmaCore.</p></div></div>
            <form method="post" action="profil.php#email" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
                <input type="hidden" name="action" value="update_email">
                <div class="settings-field"><label for="nouvel_email">Nouvelle adresse e-mail</label><input id="nouvel_email" name="email" type="email" maxlength="150" autocomplete="email" required value="<?= e($compte['email']) ?>"></div>
                <div class="settings-field"><label for="mot_de_passe_actuel_email">Mot de passe actuel</label><input id="mot_de_passe_actuel_email" name="mot_de_passe_actuel" type="password" autocomplete="current-password" required></div>
                <div class="settings-form-footer"><span>Confirmez votre identité pour sécuriser le changement.</span><button class="button button-primary" type="submit">Mettre à jour l’e-mail</button></div>
            </form>
        </section>
        <section class="account-settings-section" id="password">
            <div class="settings-card-heading"><span class="settings-card-icon" aria-hidden="true">⌑</span><div><h2>Mot de passe</h2><p>Choisissez un mot de passe d’au moins 8 caractères.</p></div></div>
            <form method="post" action="profil.php#password" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
                <input type="hidden" name="action" value="update_password">
                <div class="settings-field"><label for="mot_de_passe_actuel">Mot de passe actuel</label><input id="mot_de_passe_actuel" name="mot_de_passe_actuel" type="password" autocomplete="current-password" required></div>
                <div class="settings-field"><label for="nouveau_mot_de_passe">Nouveau mot de passe</label><input id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
                <div class="settings-field"><label for="confirmation">Confirmer le nouveau mot de passe</label><input id="confirmation" name="confirmation" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
                <div class="settings-form-footer"><span>Votre mot de passe reste privé et n’est jamais affiché.</span><button class="button button-primary" type="submit">Mettre à jour le mot de passe</button></div>
            </form>
        </section>
        <p class="profile-privacy-note">Vos informations de compte ne sont visibles que lorsque vous êtes connecté.</p>
        <a class="button button-primary button-full" href="../index.php">Retour au site</a>
        <footer class="account-footer"><span>PharmaCore · Espace client</span></footer>
    </main>
</body>
</html>

<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
exigerAdministrateur();

$erreur = '';
$emailChange = isset($_SESSION['email_change']);
unset($_SESSION['email_change']);
$motDePasseChange = isset($_SESSION['mot_de_passe_change']);
unset($_SESSION['mot_de_passe_change']);
$avatarChange = isset($_SESSION['avatar_change']);
unset($_SESSION['avatar_change']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierJetonCsrf()) {
        $erreur = 'Votre session a expiré. Actualisez la page puis réessayez.';
    } elseif (($_POST['action'] ?? '') === 'upload_avatar') {
        $fichierAvatar = $_FILES['avatar'] ?? null;

        if (
            !is_array($fichierAvatar)
            || !isset($fichierAvatar['error'], $fichierAvatar['tmp_name'], $fichierAvatar['size'])
            || !is_string($fichierAvatar['tmp_name'])
            || !is_numeric($fichierAvatar['size'])
        ) {
            $erreur = 'Sélectionnez une photo avant de continuer.';
        } elseif ((int) $fichierAvatar['error'] !== UPLOAD_ERR_OK) {
            $erreur = match ((int) $fichierAvatar['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La photo dépasse la taille maximale autorisée de 5 Mo.',
                UPLOAD_ERR_NO_FILE => 'Sélectionnez une photo avant de continuer.',
                default => 'La photo n’a pas pu être téléversée. Réessayez.',
            };
        } elseif (
            !is_uploaded_file((string) $fichierAvatar['tmp_name'])
            || (int) $fichierAvatar['size'] < 1
            || (int) $fichierAvatar['size'] > 5 * 1024 * 1024
        ) {
            $erreur = 'Choisissez une image valide de 5 Mo maximum.';
        } else {
            $informationImage = @getimagesize((string) $fichierAvatar['tmp_name']);
            $detecteurMime = new finfo(FILEINFO_MIME_TYPE);
            $mime = $detecteurMime->file((string) $fichierAvatar['tmp_name']);
            $formatsAutorises = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];

            if (
                !is_array($informationImage)
                || !isset($formatsAutorises[$mime])
                || ($informationImage['mime'] ?? '') !== $mime
                || $informationImage[0] < 1
                || $informationImage[1] < 1
                || $informationImage[0] > 5000
                || $informationImage[1] > 5000
                || $informationImage[0] * $informationImage[1] > 15_000_000
            ) {
                $erreur = 'Format de photo invalide. Choisissez une image JPEG, PNG ou WebP valide.';
            } else {
                $dossierAvatar = dirname(__DIR__) . '/storage/admin-avatars';
                if (!is_dir($dossierAvatar) && !mkdir($dossierAvatar, 0750, true) && !is_dir($dossierAvatar)) {
                    $erreur = 'Le dossier des photos de profil n’est pas accessible en écriture.';
                } else {
                    $adminId = (int) $_SESSION['admin_id'];
                    $extension = $formatsAutorises[$mime];
                    $destination = $dossierAvatar . '/' . $adminId . '.' . $extension;

                    if (!move_uploaded_file((string) $fichierAvatar['tmp_name'], $destination)) {
                        $erreur = 'La photo n’a pas pu être enregistrée. Vérifiez les droits du dossier du projet.';
                    } else {
                        foreach (array_diff(['jpg', 'png', 'webp'], [$extension]) as $ancienneExtension) {
                            $ancienAvatar = $dossierAvatar . '/' . $adminId . '.' . $ancienneExtension;
                            if (is_file($ancienAvatar) && !unlink($ancienAvatar)) {
                                error_log('PharmaCore: unable to remove an outdated administrator avatar.');
                            }
                        }

                        $_SESSION['avatar_change'] = true;
                        rediriger('reglages.php#profile');
                    }
                }
            }
        }
    } elseif (($_POST['action'] ?? '') === 'update_email') {
        $nouvelEmail = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $motDePasseActuel = is_string($_POST['mot_de_passe_email'] ?? null)
            ? $_POST['mot_de_passe_email']
            : '';

        if (!filter_var($nouvelEmail, FILTER_VALIDATE_EMAIL) || strlen($nouvelEmail) > 150 || $motDePasseActuel === '') {
            $erreur = 'Saisissez une adresse e-mail valide et confirmez votre mot de passe actuel.';
        } else {
            try {
                $pdo = connexionPDO();
                $requeteCompte = $pdo->prepare(
                    "SELECT mot_de_passe
                     FROM utilisateurs
                     WHERE id = :id AND role = 'admin'
                     LIMIT 1"
                );
                $requeteCompte->execute(['id' => (int) $_SESSION['admin_id']]);
                $compte = $requeteCompte->fetch();

                if (!is_array($compte) || !password_verify($motDePasseActuel, (string) $compte['mot_de_passe'])) {
                    $erreur = 'Le mot de passe actuel est incorrect.';
                } else {
                    $requeteEmail = $pdo->prepare(
                        'SELECT id FROM utilisateurs WHERE email = :email AND id <> :id LIMIT 1'
                    );
                    $requeteEmail->execute([
                        'email' => $nouvelEmail,
                        'id' => (int) $_SESSION['admin_id'],
                    ]);

                    if ($requeteEmail->fetchColumn() !== false) {
                        $erreur = 'Cette adresse e-mail est déjà utilisée par un autre compte.';
                    } elseif (strcasecmp($nouvelEmail, (string) ($_SESSION['admin_email'] ?? '')) === 0) {
                        $erreur = 'Cette adresse e-mail est déjà celle de votre compte.';
                    } else {
                        $miseAJour = $pdo->prepare(
                            "UPDATE utilisateurs
                             SET email = :email
                             WHERE id = :id AND role = 'admin'"
                        );
                        $miseAJour->execute([
                            'email' => $nouvelEmail,
                            'id' => (int) $_SESSION['admin_id'],
                        ]);

                        if ($miseAJour->rowCount() !== 1) {
                            throw new RuntimeException('Le compte administrateur n’a pas pu être mis à jour.');
                        }

                        session_regenerate_id(true);
                        $_SESSION['admin_email'] = $nouvelEmail;
                        $_SESSION['email_change'] = true;
                        rediriger('reglages.php#email');
                    }
                }
            } catch (PDOException | RuntimeException $exception) {
                error_log('PharmaCore admin email update failed: ' . $exception->getMessage());
                $erreur = $exception instanceof PDOException && $exception->getCode() === '23000'
                    ? 'Cette adresse e-mail est déjà utilisée par un autre compte.'
                    : 'L’adresse e-mail n’a pas pu être modifiée. Réessayez dans quelques instants.';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'update_password') {
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
            || strlen($nouveauMotDePasse) < 12
            || strlen($nouveauMotDePasse) > 72
            || $nouveauMotDePasse !== $confirmation
        ) {
            $erreur = 'Vérifiez les champs : le nouveau mot de passe doit compter au moins 12 caractères (72 octets maximum) et les deux saisies doivent correspondre.';
        } else {
            $pdo = null;

            try {
                $pdo = connexionPDO();
                $pdo->beginTransaction();

                $requeteCompte = $pdo->prepare(
                    "SELECT mot_de_passe
                     FROM utilisateurs
                     WHERE id = :id AND role = 'admin'
                     LIMIT 1
                     FOR UPDATE"
                );
                $requeteCompte->execute(['id' => (int) $_SESSION['admin_id']]);
                $compte = $requeteCompte->fetch();

                if (!is_array($compte) || !password_verify($motDePasseActuel, (string) $compte['mot_de_passe'])) {
                    $erreur = 'Le mot de passe actuel est incorrect.';
                    $pdo->rollBack();
                } else {
                    $ancienHash = (string) $compte['mot_de_passe'];
                    $motDePasseDejaUtilise = password_verify($nouveauMotDePasse, $ancienHash);

                    if (!$motDePasseDejaUtilise) {
                        $requeteHistorique = $pdo->prepare(
                            'SELECT mot_de_passe_hash
                             FROM historique_mots_de_passe
                             WHERE utilisateur_id = :user_id'
                        );
                        $requeteHistorique->execute(['user_id' => (int) $_SESSION['admin_id']]);

                        foreach ($requeteHistorique->fetchAll(PDO::FETCH_COLUMN) as $hashHistorique) {
                            if (password_verify($nouveauMotDePasse, (string) $hashHistorique)) {
                                $motDePasseDejaUtilise = true;
                                break;
                            }
                        }
                    }

                    if ($motDePasseDejaUtilise) {
                        $erreur = 'Choisissez un nouveau mot de passe qui n’a pas déjà été utilisé.';
                        $pdo->rollBack();
                    } else {
                        $archive = $pdo->prepare(
                            'INSERT INTO historique_mots_de_passe (utilisateur_id, mot_de_passe_hash)
                             VALUES (:user_id, :password_hash)'
                        );
                        $archive->execute([
                            'user_id' => (int) $_SESSION['admin_id'],
                            'password_hash' => $ancienHash,
                        ]);

                        $miseAJour = $pdo->prepare(
                            "UPDATE utilisateurs
                             SET mot_de_passe = :password_hash
                             WHERE id = :id AND role = 'admin'"
                        );
                        $miseAJour->execute([
                            'password_hash' => password_hash($nouveauMotDePasse, PASSWORD_DEFAULT),
                            'id' => (int) $_SESSION['admin_id'],
                        ]);

                        if ($miseAJour->rowCount() !== 1) {
                            throw new RuntimeException('Le compte administrateur n’a pas pu être mis à jour.');
                        }

                        $pdo->commit();
                        session_regenerate_id(true);
                        unset($_SESSION['csrf_token']);
                        $_SESSION['mot_de_passe_change'] = true;
                        rediriger('reglages.php#password');
                    }
                }
            } catch (PDOException | RuntimeException $exception) {
                if ($pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('PharmaCore admin password update failed: ' . $exception->getMessage());
                $erreur = 'Le mot de passe n’a pas pu être modifié. Réessayez dans quelques instants.';
            }
        }
    } else {
        http_response_code(400);
        $erreur = 'Cette action de compte n’est pas disponible.';
    }
}

$nomAdministrateur = is_string($_SESSION['admin_name'] ?? null) && $_SESSION['admin_name'] !== ''
    ? $_SESSION['admin_name']
    : 'Administrateur';
$initiales = mb_strtoupper(mb_substr($nomAdministrateur, 0, 1, 'UTF-8'), 'UTF-8');
$emailAdministrateur = is_string($_SESSION['admin_email'] ?? null) ? $_SESSION['admin_email'] : '';
$idAdministrateur = (int) $_SESSION['admin_id'];
$avatarUrl = avatarAdministrateurUrl($idAdministrateur);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f8f6">
    <title>Réglages · PharmaCore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/account.js" defer></script>
</head>
<body class="app-page">
<main class="settings-page">
    <header class="settings-topbar">
        <a class="brand" href="dashboard.php"><span class="brand-mark" aria-hidden="true">+</span><span>Pharma<span class="brand-light">Core</span></span></a>
        <div class="notifications-top-actions">
            <a class="icon-button" href="dashboard.php" aria-label="Retour au tableau de bord" title="Tableau de bord"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10 12 3l9 7v10a1 1 0 0 1-1 1h-6v-7h-4v7H4a1 1 0 0 1-1-1V10Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg></a>
            <div class="account-menu" data-account-menu>
                <button class="avatar avatar-top account-avatar account-menu-trigger" type="button" aria-label="Ouvrir le menu du compte" aria-haspopup="true" aria-expanded="false" aria-controls="accountMenu"><?php if ($avatarUrl !== null): ?><img src="<?= e($avatarUrl) ?>" alt=""><?php else: ?><?= e($initiales) ?><?php endif; ?></button>
                <div class="account-menu-panel" id="accountMenu" hidden>
                    <div class="account-menu-heading">
                        <span class="account-menu-name"><?= e($nomAdministrateur) ?></span>
                        <span class="account-menu-email"><?= e($emailAdministrateur) ?></span>
                        <span class="account-menu-role">Administrateur</span>
                    </div>
                    <a href="#profile">Profil et avatar</a>
                    <a href="#email">Adresse e-mail</a>
                    <a href="#password">Mot de passe</a>
                    <form method="post" action="logout.php">
                        <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
                        <button class="account-menu-logout" type="submit">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3m9-9h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
                            Se déconnecter
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <div class="settings-content">
        <a class="back-link" href="dashboard.php">← Retour au tableau de bord</a>
        <div class="page-heading">
            <div><span class="eyebrow">VOTRE COMPTE</span><h1>Réglages</h1><p>Gérez vos informations de connexion et protégez votre compte.</p></div>
        </div>

        <?php if ($erreur !== ''): ?>
            <div class="alert alert-danger dashboard-alert" role="alert"><?= e($erreur) ?></div>
        <?php endif; ?>

        <?php if ($emailChange): ?>
            <div class="settings-success" role="status">Votre adresse e-mail a été mise à jour.</div>
        <?php endif; ?>
        <?php if ($motDePasseChange): ?>
            <div class="settings-success" role="status">Votre mot de passe a été modifié. Utilisez-le lors de votre prochaine connexion.</div>
        <?php endif; ?>
        <?php if ($avatarChange): ?>
            <div class="settings-success" role="status">Votre photo de profil a été mise à jour.</div>
        <?php endif; ?>

        <section class="panel settings-card account-profile-card" id="profile" aria-labelledby="profile-title">
            <div class="settings-card-heading">
                <span class="settings-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M4 21a8 8 0 0 1 16 0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                <div><h2 id="profile-title">Votre profil</h2><p>Ajoutez une photo de profil qui sera visible dans votre espace administrateur.</p></div>
            </div>
            <div class="profile-avatar-preview">
                <span class="avatar avatar-profile account-avatar"><?php if ($avatarUrl !== null): ?><img src="<?= e($avatarUrl) ?>" alt="Photo de profil de <?= e($nomAdministrateur) ?>"><?php else: ?><?= e($initiales) ?><?php endif; ?></span>
                <div><strong><?= e($nomAdministrateur) ?></strong><span><?= e($emailAdministrateur) ?></span></div>
            </div>
            <form method="post" action="reglages.php#profile" class="settings-form avatar-upload-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
                <input type="hidden" name="action" value="upload_avatar">
                <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                <div class="settings-field">
                    <label for="avatar">Photo de profil</label>
                    <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" required>
                    <small>JPEG, PNG ou WebP · 5 Mo maximum. La photo est enregistrée pour votre compte sur le serveur.</small>
                </div>
                <div class="settings-form-footer"><span>Votre photo est accessible uniquement après connexion à l’administration.</span><button class="button button-primary" type="submit">Enregistrer la photo <span aria-hidden="true">→</span></button></div>
            </form>
        </section>

        <section class="panel settings-card" id="email" aria-labelledby="email-title">
            <div class="settings-card-heading">
                <span class="settings-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="2.5" y="4.5" width="19" height="15" rx="2" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="m3 6 9 7 9-7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg></span>
                <div><h2 id="email-title">Adresse e-mail</h2><p>Utilisée pour vous connecter à votre compte administrateur.</p></div>
            </div>
            <div class="settings-account"><span class="settings-account-label">Adresse actuelle</span><strong><?= e($emailAdministrateur) ?></strong><span class="settings-role">Administrateur</span></div>
            <form method="post" action="reglages.php#email" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
                <input type="hidden" name="action" value="update_email">
                <div class="settings-field">
                    <label for="nouvel_email">Nouvelle adresse e-mail</label>
                    <input id="nouvel_email" name="email" type="email" maxlength="150" autocomplete="email" required value="<?= e($emailAdministrateur) ?>">
                </div>
                <div class="settings-field">
                    <label for="mot_de_passe_email">Mot de passe actuel</label>
                    <input id="mot_de_passe_email" name="mot_de_passe_email" type="password" autocomplete="current-password" required>
                </div>
                <div class="settings-form-footer"><span>Une confirmation du mot de passe protège cette modification.</span><button class="button button-primary" type="submit">Mettre à jour l’e-mail <span aria-hidden="true">→</span></button></div>
            </form>
        </section>

        <section class="panel settings-card" id="password" aria-labelledby="security-title">
            <div class="settings-card-heading">
                <span class="settings-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M8 10V7a4 4 0 1 1 8 0v3m-4 4v3" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                <div><h2 id="security-title">Sécurité du compte</h2><p>Confirmez votre mot de passe actuel pour en définir un nouveau.</p></div>
            </div>

            <div class="settings-account"><span class="settings-account-label">Connecté en tant que</span><strong><?= e($emailAdministrateur) ?></strong><span class="settings-role">Administrateur</span></div>

            <form method="post" action="reglages.php" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
                <input type="hidden" name="action" value="update_password">
                <div class="settings-field">
                    <label for="mot_de_passe_actuel">Mot de passe actuel</label>
                    <input id="mot_de_passe_actuel" name="mot_de_passe_actuel" type="password" autocomplete="current-password" required>
                </div>
                <div class="settings-field">
                    <label for="nouveau_mot_de_passe">Nouveau mot de passe</label>
                    <input id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" type="password" minlength="12" maxlength="72" autocomplete="new-password" aria-describedby="password-hint" required>
                    <small id="password-hint">12 caractères minimum. Les mots de passe déjà utilisés ne peuvent pas être réutilisés.</small>
                </div>
                <div class="settings-field">
                    <label for="confirmation">Confirmer le nouveau mot de passe</label>
                    <input id="confirmation" name="confirmation" type="password" minlength="12" maxlength="72" autocomplete="new-password" required>
                </div>
                <div class="settings-form-footer"><span>Votre nouveau mot de passe sera enregistré de manière sécurisée.</span><button class="button button-primary" type="submit">Mettre à jour le mot de passe <span aria-hidden="true">→</span></button></div>
            </form>
        </section>

        <footer class="dashboard-footer"><span>PharmaCore <span class="footer-separator">·</span> Réglages du compte</span><span>Accès réservé à l’administration</span></footer>
    </div>
</main>
</body>
</html>

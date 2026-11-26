<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/password_reset.php';

header('Cache-Control: no-store, max-age=0');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$jeton = is_string($_POST['token'] ?? null)
    ? $_POST['token']
    : (is_string($_GET['token'] ?? null) ? $_GET['token'] : '');
$roleDemande = is_string($_GET['type'] ?? null) && in_array($_GET['type'], ['client', 'admin'], true)
    ? $_GET['type']
    : 'client';
$jetonValideSyntaxe = preg_match('/\A[a-f0-9]{64}\z/', $jeton) === 1;
$erreur = '';
$succes = '';
$role = '';

try {
    $pdo = connexionPDO();
    assurerTableJetonsReinitialisation($pdo);
    if ($jetonValideSyntaxe) {
        $requeteJeton = $pdo->prepare(
            'SELECT role_compte
             FROM jetons_reinitialisation_mot_de_passe
             WHERE jeton_hash = :jeton_hash
               AND utilise_le IS NULL
               AND expire_le > NOW()
             LIMIT 1'
        );
        $requeteJeton->execute(['jeton_hash' => hash('sha256', $jeton)]);
        $roleTrouve = $requeteJeton->fetchColumn();
        if (is_string($roleTrouve) && in_array($roleTrouve, ['client', 'admin'], true)) {
            $role = $roleTrouve;
        }
    }
} catch (PDOException $exception) {
    error_log('PharmaCore password reset validation failed: ' . $exception->getMessage());
    http_response_code(503);
    $erreur = 'La réinitialisation est momentanément indisponible. Réessayez plus tard.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $erreur === '') {
    $nouveauMotDePasse = is_string($_POST['nouveau_mot_de_passe'] ?? null) ? $_POST['nouveau_mot_de_passe'] : '';
    $confirmation = is_string($_POST['confirmation'] ?? null) ? $_POST['confirmation'] : '';
    $longueurMinimum = $role === 'admin' ? 12 : 8;

    if (!verifierJetonCsrf()) {
        $erreur = 'Votre session a expiré. Actualisez la page puis réessayez.';
    } elseif (
        !$jetonValideSyntaxe
        || $role === ''
        || mb_strlen($nouveauMotDePasse, 'UTF-8') < $longueurMinimum
        || strlen($nouveauMotDePasse) > 72
        || $nouveauMotDePasse !== $confirmation
    ) {
        $erreur = 'Le lien est invalide ou expiré, ou les mots de passe ne respectent pas les critères demandés.';
    } else {
        try {
            $pdo->beginTransaction();
            $hashJeton = hash('sha256', $jeton);
            $verrouJeton = $pdo->prepare(
                'SELECT id, utilisateur_id, role_compte
                 FROM jetons_reinitialisation_mot_de_passe
                 WHERE jeton_hash = :jeton_hash
                   AND utilise_le IS NULL
                   AND expire_le > NOW()
                 LIMIT 1
                 FOR UPDATE'
            );
            $verrouJeton->execute(['jeton_hash' => $hashJeton]);
            $donneesJeton = $verrouJeton->fetch();

            if (!is_array($donneesJeton) || $donneesJeton['role_compte'] !== $role) {
                $pdo->rollBack();
                $erreur = 'Ce lien de réinitialisation est invalide ou a expiré. Faites une nouvelle demande.';
            } else {
                $verrouCompte = $pdo->prepare(
                    'SELECT mot_de_passe
                     FROM utilisateurs
                     WHERE id = :id AND role = :role
                     LIMIT 1
                     FOR UPDATE'
                );
                $verrouCompte->execute([
                    'id' => (int) $donneesJeton['utilisateur_id'],
                    'role' => $role,
                ]);
                $compte = $verrouCompte->fetch();

                if (!is_array($compte)) {
                    $pdo->rollBack();
                    $erreur = 'Ce lien de réinitialisation est invalide ou a expiré. Faites une nouvelle demande.';
                } else {
                    $ancienHash = (string) $compte['mot_de_passe'];
                    $motDePasseDejaUtilise = password_verify($nouveauMotDePasse, $ancienHash);

                    if ($role === 'admin' && !$motDePasseDejaUtilise) {
                        $requeteHistorique = $pdo->prepare(
                            'SELECT mot_de_passe_hash
                             FROM historique_mots_de_passe
                             WHERE utilisateur_id = :utilisateur_id'
                        );
                        $requeteHistorique->execute(['utilisateur_id' => (int) $donneesJeton['utilisateur_id']]);
                        foreach ($requeteHistorique->fetchAll(PDO::FETCH_COLUMN) as $hashHistorique) {
                            if (password_verify($nouveauMotDePasse, (string) $hashHistorique)) {
                                $motDePasseDejaUtilise = true;
                                break;
                            }
                        }
                    }

                    if ($motDePasseDejaUtilise) {
                        $pdo->rollBack();
                        $erreur = 'Choisissez un mot de passe qui n’a pas déjà été utilisé.';
                    } else {
                        if ($role === 'admin') {
                            $archive = $pdo->prepare(
                                'INSERT INTO historique_mots_de_passe (utilisateur_id, mot_de_passe_hash)
                                 VALUES (:utilisateur_id, :mot_de_passe_hash)'
                            );
                            $archive->execute([
                                'utilisateur_id' => (int) $donneesJeton['utilisateur_id'],
                                'mot_de_passe_hash' => $ancienHash,
                            ]);
                        }

                        $miseAJour = $pdo->prepare(
                            'UPDATE utilisateurs
                             SET mot_de_passe = :mot_de_passe
                             WHERE id = :id AND role = :role'
                        );
                        $miseAJour->execute([
                            'mot_de_passe' => password_hash($nouveauMotDePasse, PASSWORD_DEFAULT),
                            'id' => (int) $donneesJeton['utilisateur_id'],
                            'role' => $role,
                        ]);

                        $consommerJetons = $pdo->prepare(
                            'UPDATE jetons_reinitialisation_mot_de_passe
                             SET utilise_le = NOW()
                             WHERE utilisateur_id = :utilisateur_id
                               AND role_compte = :role
                               AND utilise_le IS NULL'
                        );
                        $consommerJetons->execute([
                            'utilisateur_id' => (int) $donneesJeton['utilisateur_id'],
                            'role' => $role,
                        ]);
                        $pdo->commit();

                        $_SESSION['password_reset_success'] = $role;
                        rediriger($role === 'admin' ? 'admin/login.php' : 'client/login.php');
                    }
                }
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('PharmaCore password reset update failed: ' . $exception->getMessage());
            $erreur = 'Le mot de passe n’a pas pu être modifié. Réessayez plus tard.';
        }
    }
}

if ($role === '' && $erreur === '') {
    http_response_code(400);
    $erreur = 'Ce lien de réinitialisation est invalide ou a expiré. Faites une nouvelle demande.';
}

$minimumAffiche = $role === 'admin' ? 12 : 8;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f8f6">
    <title>Choisir un nouveau mot de passe · PharmaCore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <main class="setup-card">
        <a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">+</span><span>Pharma<span class="brand-light">Core</span></span></a>
        <span class="eyebrow">SÉCURITÉ DU COMPTE</span>
        <h1>Nouveau mot de passe</h1>
        <p class="muted">Choisissez un mot de passe d’au moins <?= $minimumAffiche ?> caractères (72 octets maximum).</p>
        <?php if ($erreur !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= e($erreur) ?></div>
            <p class="account-switch"><a href="mot_de_passe_oublie.php?type=<?= e($role !== '' ? $role : $roleDemande) ?>">Demander un nouveau lien</a></p>
        <?php elseif ($role !== ''): ?>
            <form method="post" action="reinitialiser_mot_de_passe.php?type=<?= e($role) ?>" class="login-form">
                <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
                <input type="hidden" name="token" value="<?= e($jeton) ?>">
                <label for="nouveau_mot_de_passe">Nouveau mot de passe</label>
                <input id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" type="password" minlength="<?= $minimumAffiche ?>" maxlength="72" autocomplete="new-password" required>
                <label for="confirmation">Confirmer le nouveau mot de passe</label>
                <input id="confirmation" name="confirmation" type="password" minlength="<?= $minimumAffiche ?>" maxlength="72" autocomplete="new-password" required>
                <button class="button button-primary button-full" type="submit">Enregistrer le nouveau mot de passe</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>

<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/password_reset.php';

$role = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
if (!in_array($role, ['client', 'admin'], true)) {
    http_response_code(404);
    exit('Page introuvable.');
}

header('Cache-Control: no-store, max-age=0');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$erreur = '';
$message = '';
$email = '';
$titreEspace = $role === 'admin' ? 'Espace administrateur' : 'Espace client';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';

    if (!verifierJetonCsrf()) {
        $erreur = 'Votre session a expiré. Actualisez la page puis réessayez.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $erreur = 'Saisissez une adresse e-mail valide.';
    } else {
        $pdo = null;
        try {
            $configuration = configurationCourrielReinitialisation();
            $pdo = connexionPDO();
            assurerTableJetonsReinitialisation($pdo);
            $pdo->exec(
                'DELETE FROM jetons_reinitialisation_mot_de_passe
                 WHERE expire_le < DATE_SUB(NOW(), INTERVAL 1 DAY)'
            );

            $requeteCompte = $pdo->prepare(
                'SELECT id
                 FROM utilisateurs
                 WHERE email = :email AND role = :role
                 LIMIT 1'
            );
            $requeteCompte->execute(['email' => $email, 'role' => $role]);
            $compte = $requeteCompte->fetch();

            if (is_array($compte)) {
                $pdo->beginTransaction();
                $verrouCompte = $pdo->prepare(
                    'SELECT id, email
                     FROM utilisateurs
                     WHERE id = :id AND role = :role
                     LIMIT 1
                     FOR UPDATE'
                );
                $verrouCompte->execute([
                    'id' => (int) $compte['id'],
                    'role' => $role,
                ]);
                $compteVerrouille = $verrouCompte->fetch();

                if (!is_array($compteVerrouille)) {
                    $pdo->commit();
                } else {
                    $limite = $pdo->prepare(
                        'SELECT COUNT(*)
                         FROM jetons_reinitialisation_mot_de_passe
                         WHERE role_compte = :role
                           AND cree_le >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
                           AND utilisateur_id = :utilisateur_id'
                    );
                    $limite->execute([
                        'role' => $role,
                        'utilisateur_id' => (int) $compteVerrouille['id'],
                    ]);
                    $limiteAtteinte = (int) $limite->fetchColumn() > 0;

                    if (!$limiteAtteinte) {
                        $jeton = bin2hex(random_bytes(32));
                        $jetonHash = hash('sha256', $jeton);
                        $invaliderAnciensJetons = $pdo->prepare(
                            'UPDATE jetons_reinitialisation_mot_de_passe
                             SET utilise_le = NOW()
                             WHERE utilisateur_id = :utilisateur_id
                               AND role_compte = :role
                               AND utilise_le IS NULL'
                        );
                        $invaliderAnciensJetons->execute([
                            'utilisateur_id' => (int) $compteVerrouille['id'],
                            'role' => $role,
                        ]);
                        $creation = $pdo->prepare(
                            'INSERT INTO jetons_reinitialisation_mot_de_passe
                                (utilisateur_id, role_compte, jeton_hash, cree_le, expire_le)
                             VALUES
                                (:utilisateur_id, :role, :jeton_hash, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR))'
                        );
                        $creation->execute([
                            'utilisateur_id' => (int) $compteVerrouille['id'],
                            'role' => $role,
                            'jeton_hash' => $jetonHash,
                        ]);
                    }
                    $pdo->commit();

                    if (!$limiteAtteinte) {
                        $lien = $configuration['url_base']
                            . '/reinitialiser_mot_de_passe.php?token=' . rawurlencode($jeton)
                            . '&type=' . rawurlencode($role);
                        if (!envoyerLienReinitialisation((string) $compteVerrouille['email'], $lien, $configuration)) {
                            $invalider = $pdo->prepare(
                                'UPDATE jetons_reinitialisation_mot_de_passe
                                 SET utilise_le = NOW()
                                 WHERE jeton_hash = :jeton_hash AND utilise_le IS NULL'
                            );
                            $invalider->execute(['jeton_hash' => $jetonHash]);
                            error_log('PharmaCore password reset email could not be sent via SMTP.');
                        }
                    }
                }
            }

            $message = 'Si un compte de cet espace correspond à cette adresse, PharmaCore a tenté de lui envoyer un lien. Sa réception dépend du serveur de messagerie et n’est pas garantie.';
            $email = '';
        } catch (PDOException | RuntimeException $exception) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('PharmaCore password reset request failed: ' . $exception->getMessage());
            $erreur = 'Le service de réinitialisation est momentanément indisponible. Réessayez plus tard.';
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
    <title>Mot de passe oublié · PharmaCore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <main class="setup-card">
        <a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">+</span><span>Pharma<span class="brand-light">Core</span></span></a>
        <span class="eyebrow"><?= e($titreEspace) ?></span>
        <h1>Mot de passe oublié ?</h1>
        <p class="muted">Indiquez l’adresse e-mail de votre compte. Si elle correspond à cet espace, nous vous enverrons un lien valable une heure.</p>
        <?php if ($erreur !== ''): ?><div class="alert alert-danger" role="alert"><?= e($erreur) ?></div><?php endif; ?>
        <?php if ($message !== ''): ?><div class="alert alert-success" role="status"><?= e($message) ?></div><?php endif; ?>
        <form method="post" action="mot_de_passe_oublie.php?type=<?= e($role) ?>" class="login-form">
            <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
            <label for="email">Adresse e-mail</label>
            <input id="email" name="email" type="email" autocomplete="email" maxlength="150" required value="<?= e($email) ?>" placeholder="vous@exemple.fr">
            <button class="button button-primary button-full" type="submit">Envoyer le lien</button>
        </form>
        <p class="account-switch"><a href="<?= $role === 'admin' ? 'admin/login.php' : 'client/login.php' ?>">Retour à la connexion</a></p>
    </main>
</body>
</html>

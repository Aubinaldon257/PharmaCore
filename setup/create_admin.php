<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';

$adresseCliente = $_SERVER['REMOTE_ADDR'] ?? '';
$accesLocal = is_string($adresseCliente) && in_array($adresseCliente, ['127.0.0.1', '::1'], true);
$erreur = '';
$compteCree = false;
$email = '';
$prenom = '';
$nom = '';
$baseDisponible = true;

try {
    $pdo = connexionPDO();
    $administrateursExistants = (int) $pdo->query(
        "SELECT COUNT(*) FROM utilisateurs WHERE role = 'admin'"
    )->fetchColumn();
} catch (PDOException $exception) {
    error_log('PharmaCore setup check failed: ' . $exception->getMessage());
    http_response_code(503);
    $baseDisponible = false;
    $administrateursExistants = 1;
    $erreur = 'La base de données est indisponible. Vérifiez sa configuration avant de réessayer.';
}

if ($baseDisponible && !$accesLocal) {
    http_response_code(403);
} elseif ($baseDisponible && $administrateursExistants > 0) {
    http_response_code(404);
}

if ($administrateursExistants === 0 && $accesLocal && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenom = is_string($_POST['prenom'] ?? null) ? trim($_POST['prenom']) : '';
    $nom = is_string($_POST['nom'] ?? null) ? trim($_POST['nom']) : '';
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
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
        || strlen($motDePasse) < 12
        || strlen($motDePasse) > 72
        || $motDePasse !== $confirmation
    ) {
        $erreur = 'Vérifiez les champs et choisissez un mot de passe de 12 caractères minimum (72 octets maximum).';
    } else {
        $verrouObtenu = false;

        try {
            $verrouObtenu = (int) $pdo->query(
                "SELECT GET_LOCK('pharmacore_first_admin', 5)"
            )->fetchColumn() === 1;

            if (!$verrouObtenu) {
                throw new RuntimeException('Impossible de réserver la création du premier administrateur.');
            }

            $pdo->beginTransaction();
            $administrateurExistant = $pdo->query(
                "SELECT id FROM utilisateurs WHERE role = 'admin' LIMIT 1 FOR UPDATE"
            )->fetchColumn();

            if ($administrateurExistant !== false) {
                $pdo->rollBack();
                http_response_code(404);
            } else {
                $insertion = $pdo->prepare(
                    "INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role)
                     VALUES (:nom, :prenom, :email, :mot_de_passe, 'admin')"
                );
                $insertion->execute([
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'mot_de_passe' => password_hash($motDePasse, PASSWORD_DEFAULT),
                ]);
                $pdo->commit();

                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int) $pdo->lastInsertId();
                $_SESSION['admin_role'] = 'admin';
                $_SESSION['admin_name'] = $prenom . ' ' . $nom;
                $_SESSION['admin_email'] = $email;
                unset($_SESSION['csrf_token']);
                $compteCree = true;
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('PharmaCore admin installation failed: ' . $exception->getMessage());
            $erreur = 'La création du compte a échoué. Vérifiez l’adresse e-mail et réessayez.';
        } catch (RuntimeException $exception) {
            error_log('PharmaCore admin installation lock failed: ' . $exception->getMessage());
            $erreur = 'La création du compte est momentanément indisponible. Réessayez dans quelques instants.';
        } finally {
            if ($verrouObtenu) {
                $pdo->query("SELECT RELEASE_LOCK('pharmacore_first_admin')");
            }
        }
    }
}

if ($compteCree) {
    rediriger('../admin/dashboard.php');
}

$installateurDisponible = $baseDisponible && $accesLocal && $administrateursExistants === 0;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installation administrateur · PharmaCore</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="login-page">
    <main class="setup-card">
        <a class="brand" href="../index.php"><span class="brand-mark" aria-hidden="true">+</span><span>Pharma<span class="brand-light">Core</span></span></a>
        <?php if (!$baseDisponible): ?>
            <div class="setup-closed">
                <span class="setup-lock" aria-hidden="true">!</span>
                <span class="eyebrow">BASE DE DONNÉES INDISPONIBLE</span>
                <h1>Installation suspendue</h1>
                <p>La présence d’un compte administrateur n’a pas pu être vérifiée. Aucun compte n’a été créé.</p>
                <div class="alert alert-danger" role="alert"><?= e($erreur) ?></div>
            </div>
        <?php elseif (!$accesLocal): ?>
            <div class="setup-closed">
                <span class="setup-lock" aria-hidden="true">!</span>
                <span class="eyebrow">ACCÈS LOCAL UNIQUEMENT</span>
                <h1>Installation réservée à votre ordinateur</h1>
                <p>Ouvrez cette page avec <code>http://localhost/pharmacore/setup/create_admin.php</code> ou <code>http://127.0.0.1/pharmacore/setup/create_admin.php</code> sur l’ordinateur qui héberge PharmaCore.</p>
            </div>
        <?php elseif ($administrateursExistants > 0): ?>
            <div class="setup-closed">
                <span class="setup-lock" aria-hidden="true">✓</span>
                <span class="eyebrow">INSTALLATION TERMINÉE</span>
                <h1>L’installateur est désactivé.</h1>
                <p>Un compte administrateur existe déjà. Connectez-vous pour accéder au tableau de bord.</p>
                <a class="button button-primary button-full" href="../admin/login.php">Ouvrir la connexion</a>
            </div>
        <?php elseif ($installateurDisponible): ?>
            <span class="eyebrow">PREMIÈRE CONFIGURATION</span>
            <h1>Créer le compte administrateur</h1>
            <p class="muted">Remplissez le formulaire pour choisir votre adresse e-mail et votre mot de passe. Cet installateur, réservé à votre ordinateur, se désactive dès la création du premier compte. Seul un hash du mot de passe est enregistré.</p>
            <?php if ($erreur !== ''): ?><div class="alert alert-danger" role="alert"><?= e($erreur) ?></div><?php endif; ?>
            <form method="post" class="login-form" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
                <div class="setup-name-fields">
                    <div><label for="prenom">Prénom</label><input id="prenom" name="prenom" maxlength="100" required value="<?= e($prenom) ?>"></div>
                    <div><label for="nom">Nom</label><input id="nom" name="nom" maxlength="100" required value="<?= e($nom) ?>"></div>
                </div>
                <label for="email">Adresse e-mail</label>
                <input id="email" name="email" type="email" maxlength="150" required value="<?= e($email) ?>">
                <label for="mot_de_passe">Mot de passe (12 caractères minimum, 72 octets maximum)</label>
                <input id="mot_de_passe" name="mot_de_passe" type="password" minlength="12" required autocomplete="new-password">
                <label for="confirmation">Confirmer le mot de passe</label>
                <input id="confirmation" name="confirmation" type="password" minlength="12" required autocomplete="new-password">
                <button class="button button-primary button-full" type="submit">Créer mon compte administrateur</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>

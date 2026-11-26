<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';

function chargerConfigurationEnv(): void
{
    static $charge = false;
    if ($charge) {
        return;
    }

    $charge = true;
    $fichier = dirname(__DIR__) . '/.env';
    if (!is_file($fichier) || !is_readable($fichier)) {
        return;
    }

    $lignes = file($fichier, FILE_IGNORE_NEW_LINES);
    if ($lignes === false) {
        throw new RuntimeException('Le fichier de configuration .env ne peut pas être lu.');
    }

    foreach ($lignes as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || str_starts_with($ligne, '#') || !str_contains($ligne, '=')) {
            continue;
        }

        [$nom, $valeur] = explode('=', $ligne, 2);
        $nom = trim($nom);
        $valeur = trim($valeur);

        if (!preg_match('/\A[A-Z][A-Z0-9_]*\z/', $nom)) {
            continue;
        }

        $premierCaractere = $valeur[0] ?? '';
        $dernierCaractere = $valeur[strlen($valeur) - 1] ?? '';
        if (
            strlen($valeur) >= 2
            && (($premierCaractere === '"' && $dernierCaractere === '"')
                || ($premierCaractere === "'" && $dernierCaractere === "'"))
        ) {
            $valeur = substr($valeur, 1, -1);
        }

        if (getenv($nom) === false) {
            putenv($nom . '=' . $valeur);
            $_ENV[$nom] = $valeur;
            $_SERVER[$nom] = $valeur;
        }
    }
}

chargerConfigurationEnv();

function assurerTableJetonsReinitialisation(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS jetons_reinitialisation_mot_de_passe (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            utilisateur_id INT NOT NULL,
            role_compte VARCHAR(20) NOT NULL,
            jeton_hash CHAR(64) NOT NULL,
            cree_le DATETIME NOT NULL,
            expire_le DATETIME NOT NULL,
            utilise_le DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_jeton_reinitialisation_hash (jeton_hash),
            KEY idx_jeton_reinitialisation_utilisateur (utilisateur_id, role_compte, cree_le),
            KEY idx_jeton_reinitialisation_expiration (expire_le)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function configurationCourrielReinitialisation(): array
{
    $hote = trim((string) getenv('PHARMACORE_SMTP_HOST'));
    $port = filter_var(getenv('PHARMACORE_SMTP_PORT') ?: '587', FILTER_VALIDATE_INT);
    $utilisateur = trim((string) getenv('PHARMACORE_SMTP_USERNAME'));
    $motDePasse = (string) getenv('PHARMACORE_SMTP_PASSWORD');
    $chiffrement = strtolower(trim((string) (getenv('PHARMACORE_SMTP_ENCRYPTION') ?: 'tls')));
    $serveurLocal = in_array(strtolower($hote), ['localhost', '127.0.0.1', '::1'], true);
    $expediteur = trim((string) getenv('PHARMACORE_MAIL_FROM'));
    $nomExpediteur = trim((string) (getenv('PHARMACORE_MAIL_FROM_NAME') ?: 'PharmaCore'));
    $urlBase = trim((string) (getenv('PHARMACORE_BASE_URL') ?: 'http://localhost/pharmacore'));

    if (
        $hote === ''
        || $port === false
        || $port < 1
        || $port > 65535
        || !in_array($chiffrement, ['tls', 'ssl', 'none'], true)
        || ($chiffrement === 'none' && (!$serveurLocal || $utilisateur !== '' || $motDePasse !== ''))
        || ($chiffrement !== 'none' && ($utilisateur === '' || $motDePasse === ''))
    ) {
        throw new RuntimeException('La configuration SMTP de PharmaCore est incomplète ou invalide.');
    }

    if (filter_var($expediteur, FILTER_VALIDATE_EMAIL) === false || $nomExpediteur === '') {
        throw new RuntimeException('Configurez une adresse expéditeur valide dans le fichier .env.');
    }

    $urlAnalysee = parse_url($urlBase);
    if (
        !is_array($urlAnalysee)
        || !in_array($urlAnalysee['scheme'] ?? '', ['http', 'https'], true)
        || !isset($urlAnalysee['host'])
        || isset($urlAnalysee['user'])
        || isset($urlAnalysee['pass'])
        || isset($urlAnalysee['query'])
        || isset($urlAnalysee['fragment'])
    ) {
        throw new RuntimeException('Configurez PHARMACORE_BASE_URL avec l’URL publique de PharmaCore.');
    }

    return [
        'smtp' => [
            'hote' => $hote,
            'port' => $port,
            'utilisateur' => $utilisateur,
            'mot_de_passe' => $motDePasse,
            'chiffrement' => $chiffrement,
        ],
        'expediteur' => $expediteur,
        'nom_expediteur' => $nomExpediteur,
        'url_base' => rtrim($urlBase, '/'),
    ];
}

function envoyerLienReinitialisation(string $destinataire, string $lien, array $configuration): bool
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        error_log('PharmaCore password reset email failed: Composer dependencies are not installed.');
        return false;
    }

    require_once $autoload;
    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        error_log('PharmaCore password reset email failed: PHPMailer is unavailable.');
        return false;
    }

    $message = "Bonjour,\n\n"
        . "Une demande de réinitialisation du mot de passe de votre compte PharmaCore a été reçue.\n"
        . "Pour choisir un nouveau mot de passe, ouvrez ce lien dans l’heure qui suit :\n\n"
        . $lien . "\n\n"
        . "Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.\n"
        . "PharmaCore\n";

    try {
        $courriel = new \PHPMailer\PHPMailer\PHPMailer(true);
        $courriel->isSMTP();
        $courriel->Timeout = 15;
        $courriel->Host = $configuration['smtp']['hote'];
        $courriel->SMTPAuth = $configuration['smtp']['utilisateur'] !== '';
        $courriel->Username = $configuration['smtp']['utilisateur'];
        $courriel->Password = $configuration['smtp']['mot_de_passe'];
        $courriel->SMTPSecure = match ($configuration['smtp']['chiffrement']) {
            'ssl' => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS,
            'tls' => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };
        $courriel->SMTPAutoTLS = $configuration['smtp']['chiffrement'] !== 'none';
        $courriel->Port = $configuration['smtp']['port'];
        $courriel->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $courriel->setFrom($configuration['expediteur'], $configuration['nom_expediteur']);
        $courriel->addAddress($destinataire);
        $courriel->Subject = 'Réinitialisation de votre mot de passe PharmaCore';
        $courriel->Body = $message;
        $courriel->isHTML(false);
        $courriel->send();

        return true;
    } catch (\PHPMailer\PHPMailer\Exception $exception) {
        error_log('PharmaCore password reset SMTP error: ' . $exception->getMessage());
        return false;
    } catch (Throwable $exception) {
        error_log('PharmaCore password reset email error: ' . $exception->getMessage());
        return false;
    }
}

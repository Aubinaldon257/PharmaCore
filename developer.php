<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';

function iconePlateforme(string $plateforme): string
{
    $contenus = [
        'portfolio' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>',
        'github' => '<path d="M8 19c-4 1.2-4-2-5-2m10 4v-3.1a2.7 2.7 0 0 0-.8-2.1c2.7-.3 5.5-1.3 5.5-6A4.7 4.7 0 0 0 16.4 7a4.3 4.3 0 0 0-.1-3S15.2 3.7 13 5a12 12 0 0 0-6 0C4.8 3.7 3.7 4 3.7 4A4.3 4.3 0 0 0 3.6 7a4.7 4.7 0 0 0-1.3 3.3c0 4.7 2.8 5.7 5.5 6A2.7 2.7 0 0 0 7 18.9V21"/>',
        'linkedin' => '<path d="M5 9v10M5 5v.01M10 19v-6a4 4 0 0 1 8 0v6m-8-10v10"/>',
        'email' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'x' => '<path d="m5 4 14 16M19 4 5 20"/>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>',
        'facebook' => '<path d="M14 21v-8h3l.5-4H14V7c0-1.2.4-2 2-2h2V1.4A25 25 0 0 0 15.2 1C12.4 1 10 2.7 10 6v3H7v4h3v8"/>',
        'telegram' => '<path d="m22 3-7 18-4-8-8-4 19-6ZM11 13l5-5"/>',
        'whatsapp' => '<path d="M20.5 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20l1.2-4.7a8.5 8.5 0 1 1 16.3-3.8Z"/><path d="M8.5 8.5c.4 2.7 2.3 4.6 5 5"/>',
        'wikipedia' => '<path d="m3 6 4 12 5-9 5 9 4-12M2 6h4m12 0h4"/>',
        'wikiquote' => '<path d="M5 11h5V6H6a3 3 0 0 0-3 3v7h7v-5H5Zm10 0h5V6h-4a3 3 0 0 0-3 3v7h7v-5h-5Z"/>',
        'pinterest' => '<path d="M12 21a9 9 0 1 0-3.6-.8l1.3-5.4m-.2-3.3c0-2.5 1.5-4.3 3.7-4.3 1.8 0 2.9 1.2 2.9 2.8 0 2.3-1 5.2-3.2 5.2-1.1 0-1.9-.9-1.6-2.1l.8-3.2"/>',
    ];

    $contenu = $contenus[$plateforme] ?? $contenus['portfolio'];

    return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $contenu . '</svg>';
}

$profilsProfessionnels = [
    ['Portfolio', 'https://sea_creed-portfolio.netlify.app/', 'portfolio'],
    ['GitHub', 'https://github.com/Aubinaldon257', 'github'],
    ['LinkedIn', 'https://www.linkedin.com/in/aubin-aldon-23151135a/', 'linkedin'],
    ['E-mail', 'mailto:aubinaldon@gmail.com', 'email'],
];

$reseaux = [
    ['X', 'https://x.com/Aubinaldon257', 'x'],
    ['Instagram', 'https://www.instagram.com/zen_light_01/', 'instagram'],
    ['Facebook', 'https://www.facebook.com/aubin.aldon', 'facebook'],
    ['Telegram', 'https://t.me/sea_creed', 'telegram'],
    ['WhatsApp', 'https://wa.me/25768991166', 'whatsapp'],
    ['Wikipédia', 'https://fr.wikipedia.org/wiki/Utilisateur:Sea_Creed', 'wikipedia'],
    ['Wikiquote', 'https://fr.wikiquote.org/wiki/Utilisateur:Sea_Creed', 'wikiquote'],
    ['Pinterest', 'https://www.pinterest.com/pin/1082693566753118390/', 'pinterest'],
];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f8f3">
    <meta name="description" content="À propos d’ISHUMBUSHO Aubin Aldon, développeur web connu sous le pseudonyme Sea Creed.">
    <title>À propos du développeur · PharmaCore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="public-page developer-page">
    <header class="developer-header">
        <a class="public-brand" href="index.php">
            <span class="public-brand-mark" aria-hidden="true"><span></span><span></span></span>
            <span class="public-brand-name">PharmaCore<small>VOTRE PHARMACIE</small></span>
        </a>
        <a class="developer-home-link" href="index.php" aria-label="Accueil PharmaCore" title="Accueil PharmaCore">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7M5 9v11h14V9M9 20v-7h6v7"/></svg>
        </a>
    </header>

    <main class="developer-main">
        <section class="developer-card">
            <span class="public-eyebrow"><span class="public-eyebrow-line"></span> À PROPOS DU DÉVELOPPEUR</span>
            <div class="developer-profile">
                <a class="developer-photo-link" href="https://www.pinterest.com/pin/1082693566753118390/" target="_blank" rel="noopener noreferrer" aria-label="Voir la source de la photo sur Pinterest">
                    <img class="developer-photo" src="https://i.pinimg.com/736x/c7/46/55/c74655655302ae08a596e6f881a1482a.jpg" alt="Portrait d’un homme en costume noir, cravate rouge et chapeau" fetchpriority="high">
                </a>
                <div>
                    <h1>ISHUMBUSHO Aubin Aldon</h1>
                    <p class="developer-alias">Développeur web · Sea Creed</p>
                </div>
            </div>
            <p class="developer-intro">Je conçois et développe des expériences web claires, utiles et adaptées aux besoins de leurs utilisateurs. PharmaCore est un projet dédié à une expérience numérique simple et soignée pour une pharmacie.</p>

            <div class="developer-link-group">
                <h2>Profil professionnel</h2>
                <div class="developer-link-list">
                    <?php foreach ($profilsProfessionnels as [$label, $url, $icone]): ?>
                        <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">
                            <span class="developer-platform-icon"><?= iconePlateforme($icone) ?></span>
                            <span class="developer-platform-name"><?= e($label) ?></span>
                            <span class="developer-external" aria-hidden="true">↗</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="developer-link-group">
                <h2>Réseaux et communautés</h2>
                <div class="developer-social-list">
                    <?php foreach ($reseaux as [$label, $url, $icone]): ?>
                        <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">
                            <span class="developer-platform-icon"><?= iconePlateforme($icone) ?></span>
                            <span><?= e($label) ?></span><span class="developer-external" aria-hidden="true">↗</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <footer class="developer-page-footer">
                <a href="mailto:aubinaldon@gmail.com"><?= iconePlateforme('email') ?><span>aubinaldon@gmail.com</span></a>
                <span>Profil personnel · Sea Creed</span>
            </footer>
        </section>
    </main>
</body>
</html>

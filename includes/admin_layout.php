<?php
declare(strict_types=1);

function rendreEnteteAdmin(string $titre, string $sectionActive, int $notificationsNonLues = 0): void
{
    $nomAdministrateur = is_string($_SESSION['admin_name'] ?? null) && $_SESSION['admin_name'] !== ''
        ? $_SESSION['admin_name']
        : 'Administrateur';
    $initiales = mb_strtoupper(mb_substr($nomAdministrateur, 0, 1, 'UTF-8'), 'UTF-8');
    $avatarUrl = avatarAdministrateurUrl((int) $_SESSION['admin_id']);
    $liens = [
        ['dashboard', 'Vue générale', 'dashboard.php', 'M 4 4h7v7H4z M 13 4h7v7h-7z M 4 13h7v7H4z M 13 13h7v7h-7z'],
        ['medicaments', 'Médicaments', 'medicaments.php', 'm 10 14 4-4 M 7 20a4 4 0 0 1-5-5l8-8a4 4 0 1 1 6 6l-8 8a4 4 0 0 1-1 1z M 14 13l4 4a4 4 0 1 0 6-6l-4-4'],
        ['stock', 'Gestion du stock', 'stock.php', 'm 12 3 9 5-9 5-9-5 9-5z M 3 8v8l9 5 9-5V8 M 12 13v8'],
        ['ventes', 'Ventes', 'ventes.php', 'M 3 3h2l3 12h11l3-8H6 M 9 20h.01 M 19 20h.01'],
        ['historique', 'Historique des ventes', 'historique_ventes.php', 'M 12 8v5l3 2 M 21 12a9 9 0 1 1-2.6-6.4 M 21 4v5h-5'],
        ['clients', 'Clients', 'clients.php', 'M 16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M 10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M 20 21v-2a4 4 0 0 0-3-3.9 M 16 3.1a4 4 0 0 1 0 7.8'],
        ['administrateurs', 'Administrateurs', 'administrateurs.php', 'M 16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M 10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M 19 8v6m-3-3h6'],
        ['notifications', 'Notifications', 'notifications.php', 'M 18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9 M 10 21h4'],
        ['rapports', 'Rapports', 'rapports.php', 'M 3 3v18h18 M 8 16v-4m5 4V7m5 9v-7'],
        ['reglages', 'Paramètres', 'reglages.php', 'M 12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M 19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.4 2.4-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5v.2h-3.4v-.2a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1-2.4-2.4.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H4v-3.4h.2a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1 2.4-2.4.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5V4h3.4v.2a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1 2.4 2.4-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.2v3.4h-.2a1.7 1.7 0 0 0-1.5 1z'],
    ];
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
        <script src="../assets/js/admin.js" defer></script>
        <script src="../assets/js/account.js" defer></script>
    </head>
    <body class="app-page">
    <div class="app-shell">
        <aside class="sidebar" id="sidebar" aria-label="Navigation principale">
            <a class="brand" href="dashboard.php"><span class="brand-mark" aria-hidden="true">+</span><span class="brand-name">Pharma<span class="brand-light">Core</span></span></a>
            <div class="workspace-label">ESPACE DE TRAVAIL</div>
            <nav class="side-nav" aria-label="Administration">
                <div class="nav-section-label">GESTION</div>
                <?php foreach ($liens as [$cle, $libelle, $destination, $chemin]): ?>
                    <?php if ($cle === 'administrateurs'): ?><div class="nav-section-label">COMPTE</div><?php endif; ?>
                    <a class="nav-item<?= $sectionActive === $cle ? ' is-active' : '' ?>" href="<?= e($destination) ?>"<?= $sectionActive === $cle ? ' aria-current="page"' : '' ?>>
                        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="<?= e($chemin) ?>"/></svg><span><?= e($libelle) ?></span>
                        <?php if ($cle === 'notifications' && $notificationsNonLues > 0): ?><span class="nav-count"><?= e($notificationsNonLues > 99 ? '99+' : $notificationsNonLues) ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-bottom">
                <div class="sidebar-user">
                    <a class="avatar avatar-sidebar account-avatar" href="reglages.php#profile" aria-label="Ouvrir les réglages du compte de <?= e($nomAdministrateur) ?>"><?php if ($avatarUrl !== null): ?><img src="<?= e($avatarUrl) ?>" alt=""><?php else: ?><?= e($initiales) ?><?php endif; ?></a>
                    <span class="sidebar-user-info"><strong><?= e($nomAdministrateur) ?></strong><span>Administrateur</span></span>
                    <form method="post" action="logout.php" class="sidebar-logout-form">
                        <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
                        <button class="sidebar-logout-button" type="submit" aria-label="Se déconnecter" title="Se déconnecter">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3m9-9h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>
        <div class="main-column">
            <header class="topbar">
                <div class="topbar-leading">
                    <button class="icon-button desktop-collapse-button" id="desktopCollapseButton" type="button" aria-label="Réduire la barre latérale" aria-expanded="true" aria-controls="sidebar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4zM9 5v14"/></svg></button>
                    <button class="icon-button mobile-menu-button" id="mobileMenuButton" type="button" aria-label="Ouvrir le menu" aria-controls="sidebar" aria-expanded="false"><span></span><span></span><span></span></button>
                    <div class="breadcrumb"><span>PharmaCore</span><span class="breadcrumb-separator">/</span><strong><?= e($titre) ?></strong></div>
                </div>
                <div class="topbar-actions">
                    <div class="topbar-date"><span class="online-indicator"></span><?= e((new DateTimeImmutable('today'))->format('d/m/Y')) ?></div>
                    <a class="icon-button notification-button" href="notifications.php" aria-label="Notifications" title="Notifications"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><?php if ($notificationsNonLues > 0): ?><span class="notification-badge"><?= e($notificationsNonLues > 99 ? '99+' : $notificationsNonLues) ?></span><?php endif; ?></a>
                    <div class="account-menu" data-account-menu>
                        <button class="avatar avatar-top account-avatar account-menu-trigger" type="button" aria-label="Ouvrir le menu du compte" aria-haspopup="true" aria-expanded="false" aria-controls="accountMenu"><?php if ($avatarUrl !== null): ?><img src="<?= e($avatarUrl) ?>" alt=""><?php else: ?><?= e($initiales) ?><?php endif; ?></button>
                        <div class="account-menu-panel" id="accountMenu" hidden>
                            <div class="account-menu-heading">
                                <span class="account-menu-name"><?= e($nomAdministrateur) ?></span>
                                <span class="account-menu-email"><?= e($_SESSION['admin_email'] ?? '') ?></span>
                                <span class="account-menu-role">Administrateur</span>
                            </div>
                            <a href="reglages.php#profile">Profil et avatar</a>
                            <a href="reglages.php#email">Adresse e-mail</a>
                            <a href="reglages.php#password">Mot de passe</a>
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
            <main class="dashboard-main admin-module-main">
    <?php
}

function rendrePiedAdmin(): void
{
    ?>
            <footer class="dashboard-footer"><span>PharmaCore <span class="footer-separator">·</span> Administration</span><span>Montants en francs burundais (BIF)</span></footer>
            </main>
        </div>
    </div>
    <div class="sidebar-scrim" id="sidebarScrim" aria-hidden="true"></div>
    </body>
    </html>
    <?php
}

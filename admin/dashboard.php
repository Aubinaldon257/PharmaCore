<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
exigerAdministrateur();
require_once __DIR__ . '/../includes/dashboard_data.php';

$periodeDashboard = [
    'period' => '30',
    'start' => (new DateTimeImmutable('today'))->modify('-29 days'),
    'end' => new DateTimeImmutable('today'),
    'granularity' => 'daily',
];
$donneesDashboard = null;
$erreurDashboard = '';

try {
    $periodeDashboard = preparerPeriodeDashboard($_GET);
} catch (InvalidArgumentException $exception) {
    $erreurDashboard = $exception->getMessage();
}

if ($erreurDashboard === '') {
    try {
        $donneesDashboard = chargerDonneesDashboard(connexionPDO(), $periodeDashboard);
    } catch (PDOException $exception) {
        error_log('PharmaCore dashboard query failed: ' . $exception->getMessage());
        $erreurDashboard = 'Les indicateurs ne sont pas disponibles pour le moment. Vérifiez la configuration de la base de données.';
    }
}

$titre = 'Vue générale';
$nomAdministrateur = is_string($_SESSION['admin_name'] ?? null) && $_SESSION['admin_name'] !== ''
    ? $_SESSION['admin_name']
    : 'Administrateur';
$avatarUrl = avatarAdministrateurUrl((int) $_SESSION['admin_id']);
$notificationsNonLues = $donneesDashboard['unread_notifications'] ?? 0;
$initiales = mb_strtoupper(mb_substr($nomAdministrateur, 0, 1, 'UTF-8'), 'UTF-8');
$dateDebutFormatee = $periodeDashboard['start']->format('Y-m-d');
$dateFinFormatee = $periodeDashboard['end']->format('Y-m-d');
$granulariteLabels = [
    'daily' => 'Jour',
    'weekly' => 'Semaine',
    'monthly' => 'Mois',
];
$granulariteAffichee = $granulariteLabels[$periodeDashboard['granularity']] ?? 'Jour';
$donneesGraphiques = [
    'labels' => $donneesDashboard['buckets']['labels'] ?? [],
    'sales' => $donneesDashboard['buckets']['sales'] ?? [],
    'revenue' => $donneesDashboard['buckets']['revenue'] ?? [],
    'categories' => array_map(
        static fn (array $categorie): array => [
            'label' => (string) $categorie['label'],
            'count' => (int) $categorie['count'],
        ],
        $donneesDashboard['categories'] ?? []
    ),
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js" defer></script>
    <script src="../assets/js/dashboard.js" defer></script>
    <script src="../assets/js/account.js" defer></script>
</head>
<body class="app-page">
<svg class="icon-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></symbol>
    <symbol id="i-pill" viewBox="0 0 24 24"><path d="m10.6 13.4 2.8-2.8m-5.9 8.1a4.2 4.2 0 0 1-5.9-5.9l7.7-7.7a4.2 4.2 0 0 1 5.9 5.9l-7.7 7.7Zm5.9-8.1 5.9 5.9a4.2 4.2 0 0 1-5.9 5.9l-5.9-5.9"/></symbol>
    <symbol id="i-box" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5 9-5Zm-9 5v8l9 5 9-5V8m-9 5v8"/></symbol>
    <symbol id="i-cart" viewBox="0 0 24 24"><circle cx="9" cy="20" r="1"/><circle cx="19" cy="20" r="1"/><path d="M2 3h2l2.7 12.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L22 8H5"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m6-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm10 10v-2a4 4 0 0 0-3-3.9m-1-11.9a4 4 0 0 1 0 7.8"/></symbol>
    <symbol id="i-bell" viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 13h4"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24"><path d="M3 3v18h18M8 16v-4m5 4V7m5 9v-7"/></symbol>
    <symbol id="i-settings" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1a1.7 1.7 0 1 1-2.4 2.4L17 17.4a1.7 1.7 0 0 0-2.9 1.2v.2a1.7 1.7 0 1 1-3.4 0v-.2a1.7 1.7 0 0 0-2.9-1.2l-.1.1a1.7 1.7 0 1 1-2.4-2.4l.1-.1a1.7 1.7 0 0 0-1.2-2.9H4a1.7 1.7 0 1 1 0-3.4h.2a1.7 1.7 0 0 0 1.2-2.9l-.1-.1a1.7 1.7 0 1 1 2.4-2.4l.1.1a1.7 1.7 0 0 0 2.9-1.2V2a1.7 1.7 0 1 1 3.4 0v.2a1.7 1.7 0 0 0 2.9 1.2l.1-.1a1.7 1.7 0 1 1 2.4 2.4l-.1.1a1.7 1.7 0 0 0 1.2 2.9h.2a1.7 1.7 0 1 1 0 3.4h-.2a1.7 1.7 0 0 0-1.2 2.9Z"/></symbol>
    <symbol id="i-bag" viewBox="0 0 24 24"><path d="M5 8h14l1 13H4L5 8Zm4 0V6a3 3 0 0 1 6 0v2m-7 5h.01M16 13h.01"/></symbol>
    <symbol id="i-coins" viewBox="0 0 24 24"><ellipse cx="9" cy="6" rx="6" ry="3"/><path d="M3 6v6c0 1.7 2.7 3 6 3s6-1.3 6-3V6M3 9c0 1.7 2.7 3 6 3s6-1.3 6-3m-3 7c0 1.7 2.7 3 6 3s6-1.3 6-3v-6m-12 6v2c0 1.7 2.7 3 6 3s6-1.3 6-3m-6-8c0 1.7 2.7 3 6 3s6-1.3 6-3-2.7-3-6-3-6 1.3-6 3Z"/></symbol>
    <symbol id="i-alert" viewBox="0 0 24 24"><path d="m10.3 4.3-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-2.7l-8-14a2 2 0 0 0-3.4 0ZM12 9v4m0 4h.01"/></symbol>
    <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M7 17 17 7M7 7h10v10"/></symbol>
</svg>

<div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Navigation principale">
        <a class="brand" href="dashboard.php">
            <span class="brand-mark" aria-hidden="true">+</span>
            <span class="brand-name">Pharma<span class="brand-light">Core</span></span>
        </a>
        <div class="workspace-label">ESPACE DE TRAVAIL</div>
        <nav class="side-nav">
            <a class="nav-item is-active" href="dashboard.php" aria-current="page" title="Vue générale"><svg><use href="#i-grid"/></svg><span>Vue générale</span></a>
            <div class="nav-section-label">GESTION</div>
            <?php
            $navigation = [
                ['Médicaments', 'i-pill', 'medicaments.php'],
                ['Gestion du stock', 'i-box', 'stock.php'],
                ['Ventes', 'i-cart', 'ventes.php'],
                ['Historique des ventes', 'i-clock', 'historique_ventes.php'],
                ['Clients', 'i-users', 'clients.php'],
                ['Notifications', 'i-bell', 'notifications.php'],
                ['Rapports', 'i-chart', 'rapports.php'],
                ['Paramètres', 'i-settings', 'reglages.php'],
            ];
            foreach ($navigation as [$libelle, $icone, $destination]):
            ?>
                <?php if ($destination === 'notifications.php'): ?>
                    <a class="nav-item" href="notifications.php" title="Notifications">
                        <svg><use href="#<?= e($icone) ?>"/></svg><span><?= e($libelle) ?></span>
                        <?php if ($notificationsNonLues > 0): ?><span class="nav-count"><?= e($notificationsNonLues > 99 ? '99+' : $notificationsNonLues) ?></span><?php endif; ?>
                    </a>
                <?php else: ?>
                    <a class="nav-item" href="<?= e($destination) ?>" title="<?= e($libelle) ?>">
                        <svg><use href="#<?= e($icone) ?>"/></svg><span><?= e($libelle) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-bottom">
            <div class="sidebar-help">
                <span class="help-spark" aria-hidden="true">✳</span>
                <strong>Besoin d’aide ?</strong>
                <span>Consultez le guide de démarrage.</span>
                <a href="guide.php">Voir le guide <span aria-hidden="true">→</span></a>
            </div>
            <div class="sidebar-user">
                <a class="avatar avatar-sidebar account-avatar" href="reglages.php#profile" aria-label="Ouvrir les réglages du compte de <?= e($nomAdministrateur) ?>"><?php if ($avatarUrl !== null): ?><img src="<?= e($avatarUrl) ?>" alt=""><?php else: ?><?= e($initiales) ?><?php endif; ?></a>
                <span class="sidebar-user-info"><strong><?= e($nomAdministrateur) ?></strong><span>Administrateur</span></span>
                <form method="post" action="logout.php" class="sidebar-logout-form">
                    <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
                    <button class="sidebar-logout-button" type="submit" aria-label="Se déconnecter" title="Se déconnecter"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3m9-9h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg></button>
                </form>
            </div>
        </div>
    </aside>

    <div class="main-column">
        <header class="topbar">
            <div class="topbar-leading">
                <button class="icon-button desktop-collapse-button" id="desktopCollapseButton" type="button" aria-label="Réduire la barre latérale" aria-expanded="true" aria-controls="sidebar" title="Réduire la barre latérale"><svg><use href="#i-grid"/></svg></button>
                <button class="icon-button mobile-menu-button" id="mobileMenuButton" type="button" aria-label="Ouvrir le menu" aria-controls="sidebar" aria-expanded="false"><span></span><span></span><span></span></button>
                <div class="breadcrumb"><span>PharmaCore</span><span class="breadcrumb-separator">/</span><strong>Vue générale</strong></div>
            </div>
            <div class="topbar-actions">
                <div class="topbar-date"><span class="online-indicator"></span><?= e((new DateTimeImmutable('today'))->format('d/m/Y')) ?></div>
                <a class="icon-button notification-button" href="notifications.php" aria-label="Notifications<?= $notificationsNonLues > 0 ? ', ' . e($notificationsNonLues) . ' non lues' : '' ?>" title="Notifications">
                    <svg><use href="#i-bell"/></svg>
                    <?php if ($notificationsNonLues > 0): ?><span class="notification-badge"><?= e($notificationsNonLues > 99 ? '99+' : $notificationsNonLues) ?></span><?php endif; ?>
                </a>
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
                            <button class="account-menu-logout" type="submit"><svg><use href="#i-arrow"/></svg>Se déconnecter</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="dashboard-main">
            <section class="page-heading">
                <div>
                    <span class="eyebrow">TABLEAU DE BORD</span>
                    <h1>Vue générale</h1>
                    <p>Voici ce qui se passe dans votre pharmacie.</p>
                </div>
                <div class="heading-period">
                    <span class="period-live-dot"></span>
                    <span><?= e($periodeDashboard['start']->format('d/m/Y')) ?> — <?= e($periodeDashboard['end']->format('d/m/Y')) ?></span>
                </div>
            </section>

            <section class="filter-card" id="date-filters" aria-label="Filtres du tableau de bord">
                <div class="filter-intro">
                    <span class="filter-icon"><svg><use href="#i-clock"/></svg></span>
                    <span><strong>Période d’analyse</strong><small>Filtre les ventes, le chiffre d’affaires et les graphiques</small></span>
                </div>
                <form id="periodForm" class="period-form" method="get" action="dashboard.php">
                    <label class="visually-hidden" for="periodSelect">Période</label>
                    <select id="periodSelect" name="period">
                        <option value="7" <?= $periodeDashboard['period'] === '7' ? 'selected' : '' ?>>7 derniers jours</option>
                        <option value="30" <?= $periodeDashboard['period'] === '30' ? 'selected' : '' ?>>30 derniers jours</option>
                        <option value="90" <?= $periodeDashboard['period'] === '90' ? 'selected' : '' ?>>90 derniers jours</option>
                        <option value="custom" <?= $periodeDashboard['period'] === 'custom' ? 'selected' : '' ?>>Dates personnalisées</option>
                    </select>
                    <label class="visually-hidden" for="startDate">Du</label>
                    <input class="date-input" id="startDate" type="date" name="start" value="<?= e($dateDebutFormatee) ?>" max="<?= e((new DateTimeImmutable('today'))->format('Y-m-d')) ?>">
                    <span class="date-separator">au</span>
                    <label class="visually-hidden" for="endDate">Au</label>
                    <input class="date-input" id="endDate" type="date" name="end" value="<?= e($dateFinFormatee) ?>" max="<?= e((new DateTimeImmutable('today'))->format('Y-m-d')) ?>">
                    <label class="visually-hidden" for="granularity">Regroupement des graphiques</label>
                    <select id="granularity" name="granularity">
                        <option value="daily" <?= $periodeDashboard['granularity'] === 'daily' ? 'selected' : '' ?>>Par jour</option>
                        <option value="weekly" <?= $periodeDashboard['granularity'] === 'weekly' ? 'selected' : '' ?>>Par semaine</option>
                        <option value="monthly" <?= $periodeDashboard['granularity'] === 'monthly' ? 'selected' : '' ?>>Par mois</option>
                    </select>
                    <button class="button button-filter" type="submit">Appliquer <span aria-hidden="true">→</span></button>
                </form>
            </section>

            <?php if ($erreurDashboard !== ''): ?>
                <div class="alert alert-danger dashboard-alert" role="alert">
                    <svg><use href="#i-alert"/></svg><span><?= e($erreurDashboard) ?></span>
                </div>
            <?php endif; ?>

            <section class="kpi-grid" aria-label="Indicateurs clés">
                <article class="kpi-card">
                    <div class="kpi-top"><span class="kpi-icon icon-mint"><svg><use href="#i-pill"/></svg></span><span class="kpi-caption">CATALOGUE</span></div>
                    <div class="kpi-value"><?= $donneesDashboard === null ? '—' : e(number_format($donneesDashboard['inventory']['total'], 0, ',', "\u{202F}")) ?></div>
                    <div class="kpi-label">Médicaments référencés</div>
                    <span class="kpi-footnote">État actuel du catalogue</span>
                </article>
                <article class="kpi-card">
                    <div class="kpi-top"><span class="kpi-icon icon-sage"><svg><use href="#i-bag"/></svg></span><span class="kpi-caption">AUJOURD’HUI</span></div>
                    <div class="kpi-value"><?= $donneesDashboard === null ? '—' : e(number_format($donneesDashboard['sales']['today'], 0, ',', "\u{202F}")) ?></div>
                    <div class="kpi-label">Ventes aujourd’hui</div>
                    <span class="kpi-footnote">Transactions enregistrées ce jour</span>
                </article>
                <article class="kpi-card">
                    <div class="kpi-top"><span class="kpi-icon icon-sage"><svg><use href="#i-clock"/></svg></span><span class="kpi-caption">ACTIVITÉ</span></div>
                    <div class="kpi-value"><?= $donneesDashboard === null ? '—' : e(number_format($donneesDashboard['sales']['count'], 0, ',', "\u{202F}")) ?></div>
                    <div class="kpi-label">Ventes sur la période</div>
                    <span class="kpi-footnote"><?= e($periodeDashboard['start']->format('d/m')) ?> — <?= e($periodeDashboard['end']->format('d/m/Y')) ?></span>
                </article>
                <article class="kpi-card kpi-card-revenue">
                    <div class="kpi-top"><span class="kpi-icon icon-forest"><svg><use href="#i-coins"/></svg></span><span class="kpi-caption">CHIFFRE D’AFFAIRES</span></div>
                    <div class="kpi-value kpi-value-currency"><?= $donneesDashboard === null ? '—' : e(number_format($donneesDashboard['sales']['revenue'], 0, ',', "\u{202F}") . ' BIF') ?></div>
                    <div class="kpi-label">Sur la période sélectionnée</div>
                    <span class="kpi-footnote">Montants des ventes enregistrées en BIF</span>
                </article>
                <article class="kpi-card">
                    <div class="kpi-top"><span class="kpi-icon icon-amber"><svg><use href="#i-box"/></svg></span><span class="kpi-caption">À SURVEILLER</span></div>
                    <div class="kpi-value"><?= $donneesDashboard === null ? '—' : e(number_format($donneesDashboard['inventory']['low_stock'], 0, ',', "\u{202F}")) ?></div>
                    <div class="kpi-label">Stocks bas</div>
                    <span class="kpi-footnote">De 1 à 5 unités en stock</span>
                </article>
                <article class="kpi-card">
                    <div class="kpi-top"><span class="kpi-icon icon-rose"><svg><use href="#i-alert"/></svg></span><span class="kpi-caption">ÉCHÉANCES</span></div>
                    <div class="kpi-value"><?= $donneesDashboard === null ? '—' : e(number_format($donneesDashboard['inventory']['expiring'], 0, ',', "\u{202F}")) ?></div>
                    <div class="kpi-label">Expirent sous 30 jours</div>
                    <span class="kpi-footnote">Produits en stock avec une date connue</span>
                </article>
            </section>

            <?php if ($donneesDashboard !== null): ?>
                <div class="insight-note"><svg><use href="#i-coins"/></svg><span>La valeur du stock n’est pas affichée : la base contient un prix de vente, mais aucun coût d’achat.</span></div>
            <?php endif; ?>

            <section class="charts-grid" aria-label="Graphiques des ventes">
                <article class="panel chart-panel">
                    <div class="panel-heading">
                        <div><span class="eyebrow">TENDANCE</span><h2>Ventes</h2></div>
                        <span class="chart-period-tag"><?= e($granulariteAffichee) ?></span>
                    </div>
                    <p class="panel-description">Nombre de transactions par <?= e(mb_strtolower($granulariteAffichee, 'UTF-8')) ?>.</p>
                    <div class="chart-wrap">
                        <?php if ($donneesDashboard === null): ?>
                            <p class="chart-message" role="status">Les données du graphique sont indisponibles.</p>
                        <?php elseif ($donneesDashboard['sales']['count'] === 0): ?>
                            <p class="chart-message"><span class="empty-icon"><svg><use href="#i-chart"/></svg></span>Aucune vente sur cette période.</p>
                        <?php else: ?>
                            <canvas id="salesChart" role="img" aria-label="Graphique du nombre de ventes par période"></canvas>
                            <p class="chart-message chart-loading" role="status">Chargement du graphique…</p>
                            <p class="chart-message chart-load-error" hidden role="status">Le graphique ne peut pas être chargé. Les données restent disponibles dans le tableau des ventes.</p>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="panel chart-panel">
                    <div class="panel-heading">
                        <div><span class="eyebrow">REVENUS</span><h2>Évolution du chiffre d’affaires</h2></div>
                        <span class="chart-mini-icon"><svg><use href="#i-arrow"/></svg></span>
                    </div>
                    <p class="panel-description">Montants enregistrés en BIF, regroupés par <?= e(mb_strtolower($granulariteAffichee, 'UTF-8')) ?>.</p>
                    <div class="chart-wrap">
                        <?php if ($donneesDashboard === null): ?>
                            <p class="chart-message" role="status">Les données du graphique sont indisponibles.</p>
                        <?php elseif ($donneesDashboard['sales']['count'] === 0): ?>
                            <p class="chart-message"><span class="empty-icon"><svg><use href="#i-coins"/></svg></span>Aucun chiffre d’affaires sur cette période.</p>
                        <?php else: ?>
                            <canvas id="revenueChart" role="img" aria-label="Graphique de l’évolution du chiffre d’affaires"></canvas>
                            <p class="chart-message chart-loading" role="status">Chargement du graphique…</p>
                            <p class="chart-message chart-load-error" hidden role="status">Le graphique ne peut pas être chargé. Les montants sont disponibles dans les tableaux.</p>
                        <?php endif; ?>
                    </div>
                </article>
            </section>

            <section class="lower-grid">
                <article class="panel category-panel">
                    <div class="panel-heading">
                        <div><span class="eyebrow">ASSORTIMENT</span><h2>Catégories de médicaments</h2></div>
                        <span class="panel-kicker"><?= $donneesDashboard === null ? '—' : e(number_format($donneesDashboard['inventory']['total'], 0, ',', "\u{202F}")) ?> références</span>
                    </div>
                    <?php if ($donneesDashboard === null): ?>
                        <div class="compact-empty">La répartition est indisponible.</div>
                    <?php elseif ($donneesDashboard['categories'] === []): ?>
                        <div class="compact-empty"><span class="empty-icon"><svg><use href="#i-pill"/></svg></span>Aucun médicament à répartir.</div>
                    <?php else: ?>
                        <div class="category-content">
                            <div class="donut-wrap"><canvas id="categoryChart" role="img" aria-label="Répartition du catalogue par catégorie"></canvas><div class="donut-center"><strong><?= e(number_format($donneesDashboard['inventory']['total'], 0, ',', "\u{202F}")) ?></strong><span>produits</span></div></div>
                            <ul class="category-legend" id="categoryLegend">
                                <?php foreach ($donneesDashboard['categories'] as $index => $categorie):
                                    $pourcentage = $donneesDashboard['inventory']['total'] > 0
                                        ? (int) round(((int) $categorie['count'] / $donneesDashboard['inventory']['total']) * 100)
                                        : 0;
                                ?>
                                    <li><span class="legend-dot" data-index="<?= e($index) ?>"></span><span class="legend-name"><?= e($categorie['label']) ?></span><strong><?= e(number_format((int) $categorie['count'], 0, ',', "\u{202F}")) ?></strong><span class="legend-percent"><?= e($pourcentage) ?>%</span></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <p class="chart-message chart-loading category-loading" role="status">Chargement de la répartition…</p>
                        <p class="chart-message chart-load-error category-load-error" hidden role="status">La visualisation est indisponible. Les totaux par catégorie restent affichés.</p>
                    <?php endif; ?>
                </article>

                <article class="panel inventory-panel" id="inventory">
                    <div class="panel-heading">
                        <div><span class="eyebrow">ÉTAT DU STOCK</span><h2>Équilibre de l’inventaire</h2></div>
                        <span class="panel-kicker"><?= $donneesDashboard === null ? '—' : e(number_format($donneesDashboard['inventory']['total'], 0, ',', "\u{202F}")) ?> références</span>
                    </div>
                    <?php
                    $statutsStock = [
                        ['healthy', 'Stock sain', 'status-healthy'],
                        ['low_stock', 'Stock bas · 1 à 5 unités', 'status-low'],
                        ['out_of_stock', 'Rupture de stock', 'status-out'],
                        ['expiring', 'Expire sous 30 jours', 'status-expiring'],
                        ['expired', 'Date dépassée', 'status-expired'],
                    ];
                    ?>
                    <div class="inventory-bars">
                        <?php foreach ($statutsStock as [$cleStock, $libelleStock, $classeStock]):
                            $quantiteStock = $donneesDashboard['inventory'][$cleStock] ?? 0;
                            $totalStock = $donneesDashboard['inventory']['total'] ?? 0;
                            $largeurStock = $donneesDashboard !== null && $totalStock > 0
                                ? round(($quantiteStock / $totalStock) * 100, 1)
                                : 0;
                        ?>
                            <div class="inventory-row <?= e($classeStock) ?>">
                                <div class="inventory-row-head"><span><i class="status-dot"></i><?= e($libelleStock) ?></span><strong><?= $donneesDashboard === null ? '—' : e(number_format($quantiteStock, 0, ',', "\u{202F}")) ?></strong></div>
                                <div class="progress-track" aria-hidden="true"><span style="width: <?= e($largeurStock) ?>%"></span></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="inventory-footnote">Les états sont exclusifs. Stock bas : de 1 à 5 unités. Le premier état applicable est affiché.</p>
                </article>
            </section>

            <section class="data-grid">
                <article class="panel table-panel" id="recent-sales">
                    <div class="panel-heading">
                        <div><span class="eyebrow">ACTIVITÉ RÉCENTE</span><h2>Dernières ventes</h2></div>
                        <span class="panel-kicker">Dans la période sélectionnée</span>
                    </div>
                    <?php if ($donneesDashboard === null): ?>
                        <div class="compact-empty">Les ventes sont indisponibles.</div>
                    <?php elseif ($donneesDashboard['recent_sales'] === []): ?>
                        <div class="compact-empty"><span class="empty-icon"><svg><use href="#i-bag"/></svg></span>Aucune vente enregistrée sur cette période.</div>
                    <?php else: ?>
                        <div class="table-scroll">
                            <table>
                                <thead><tr><th>Référence</th><th>Date et heure</th><th>Articles</th><th class="align-right">Montant</th></tr></thead>
                                <tbody>
                                <?php foreach ($donneesDashboard['recent_sales'] as $vente): ?>
                                    <tr>
                                        <td><span class="sale-reference">#<?= e(str_pad((string) $vente['id'], 5, '0', STR_PAD_LEFT)) ?></span></td>
                                        <td><?= e((new DateTimeImmutable((string) $vente['date_vente']))->format('d/m/Y · H:i')) ?></td>
                                        <td><?= e(number_format((int) $vente['units'], 0, ',', "\u{202F}")) ?> <span class="table-muted">unités</span></td>
                                        <td class="align-right amount-cell"><?= e(number_format((float) $vente['montant_total'], 0, ',', "\u{202F}")) ?> BIF</td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </article>

                <article class="panel top-products-panel" id="top-products">
                    <div class="panel-heading">
                        <div><span class="eyebrow">MEILLEURES VENTES</span><h2>Médicaments les plus vendus</h2></div>
                        <span class="panel-kicker">Quantités · période active</span>
                    </div>
                    <?php if ($donneesDashboard === null): ?>
                        <div class="compact-empty">Le classement est indisponible.</div>
                    <?php elseif ($donneesDashboard['top_products'] === []): ?>
                        <div class="compact-empty"><span class="empty-icon"><svg><use href="#i-chart"/></svg></span>Aucune donnée de vente pour cette période.</div>
                    <?php else: ?>
                        <?php $rang = 0; ?>
                        <ol class="top-products-list">
                            <?php foreach ($donneesDashboard['top_products'] as $produit): $rang++; ?>
                                <li>
                                    <span class="rank-badge"><?= e($rang) ?></span>
                                    <span class="top-product-copy"><strong><?= e($produit['name']) ?></strong><small><?= e(number_format((int) $produit['units'], 0, ',', "\u{202F}")) ?> unités vendues</small></span>
                                    <span class="top-product-revenue"><?= e(number_format((float) $produit['revenue'], 0, ',', "\u{202F}")) ?> BIF</span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </article>
            </section>

            <section class="alerts-grid" id="stock-alerts">
                <article class="panel alert-panel alert-panel-amber">
                    <div class="panel-heading">
                        <div><span class="eyebrow">RÉAPPROVISIONNEMENT</span><h2>Stocks à surveiller</h2></div>
                        <span class="alert-count"><?= $donneesDashboard === null ? '—' : e(number_format($donneesDashboard['inventory']['low_stock'], 0, ',', "\u{202F}")) ?></span>
                    </div>
                    <?php if ($donneesDashboard === null): ?>
                        <div class="alert-empty">Les alertes sont indisponibles.</div>
                    <?php elseif ($donneesDashboard['low_stock_products'] === []): ?>
                        <div class="alert-empty">Aucun stock bas selon le seuil de 5 unités.</div>
                    <?php else: ?>
                        <ul class="alert-list">
                            <?php foreach ($donneesDashboard['low_stock_products'] as $produit): ?>
                                <li><span class="alert-product-icon"><svg><use href="#i-box"/></svg></span><strong><?= e($produit['nom']) ?></strong><span class="quantity-pill"><?= e(number_format((int) $produit['quantite'], 0, ',', "\u{202F}")) ?> unités</span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>

                <article class="panel alert-panel alert-panel-rose">
                    <div class="panel-heading">
                        <div><span class="eyebrow">DATES DE PÉREMPTION</span><h2>À vérifier prochainement</h2></div>
                        <span class="alert-count"><?= $donneesDashboard === null ? '—' : e(number_format($donneesDashboard['inventory']['expiring'], 0, ',', "\u{202F}")) ?></span>
                    </div>
                    <?php if ($donneesDashboard === null): ?>
                        <div class="alert-empty">Les alertes sont indisponibles.</div>
                    <?php elseif ($donneesDashboard['expiring_products'] === []): ?>
                        <div class="alert-empty">Aucun produit en stock n’expire dans les 30 prochains jours.</div>
                    <?php else: ?>
                        <ul class="alert-list">
                            <?php foreach ($donneesDashboard['expiring_products'] as $produit): ?>
                                <li><span class="alert-product-icon"><svg><use href="#i-alert"/></svg></span><strong><?= e($produit['nom']) ?></strong><span class="expiry-date"><?= e((new DateTimeImmutable((string) $produit['date_expiration']))->format('d/m/Y')) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            </section>

            <section class="quick-actions" aria-label="Accès rapides">
                <span class="quick-label">ACCÈS RAPIDES</span>
                <a href="#date-filters"><svg><use href="#i-clock"/></svg>Modifier la période</a>
                <a href="#recent-sales"><svg><use href="#i-bag"/></svg>Consulter les ventes</a>
                <a href="#stock-alerts"><svg><use href="#i-alert"/></svg>Voir les alertes de stock</a>
                <a href="#top-products"><svg><use href="#i-chart"/></svg>Classement des produits</a>
            </section>

            <footer class="dashboard-footer"><span>PharmaCore <span class="footer-separator">·</span> Tableau de bord administrateur</span><span>Données issues de la base en temps réel</span></footer>
        </main>
    </div>
</div>
<div class="sidebar-scrim" id="sidebarScrim" aria-hidden="true"></div>
<script type="application/json" id="dashboardData"><?= json_encode($donneesGraphiques, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?></script>
</body>
</html>

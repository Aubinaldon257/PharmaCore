<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/admin_layout.php';
exigerAdministrateur();

$pdo = connexionPDO();
$adminId = (int) $_SESSION['admin_id'];
$erreur = '';
$vue = is_string($_GET['vue'] ?? null) ? $_GET['vue'] : 'toutes';
$recherche = is_string($_GET['recherche'] ?? null) ? trim($_GET['recherche']) : '';

if (!in_array($vue, ['toutes', 'non-lues', 'lues'], true)) {
    $vue = 'toutes';
}

if (mb_strlen($recherche, 'UTF-8') > 100) {
    $recherche = mb_substr($recherche, 0, 100, 'UTF-8');
}

$urlRetour = 'notifications.php?' . http_build_query([
    'vue' => $vue,
    'recherche' => $recherche,
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

    if (!verifierJetonCsrf()) {
        $erreur = 'Votre session a expiré. Actualisez la page puis réessayez.';
    } elseif ($action === 'tout_marquer_comme_lu') {
        try {
            $marquerTout = $pdo->prepare(
                'UPDATE notifications
                 SET est_lue = 1
                 WHERE utilisateur_id = :user_id AND est_lue = 0'
            );
            $marquerTout->execute(['user_id' => $adminId]);
            rediriger($urlRetour);
        } catch (PDOException $exception) {
            error_log('PharmaCore mark all notifications read failed: ' . $exception->getMessage());
            $erreur = 'Les notifications n’ont pas pu être mises à jour.';
        }
    } elseif ($action === 'marquer_comme_lu') {
        $identifiantNotification = filter_var(
            $_POST['notification_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($identifiantNotification === false) {
            http_response_code(400);
            $erreur = 'La notification sélectionnée est invalide.';
        } else {
            try {
                $miseAJour = $pdo->prepare(
                    'UPDATE notifications
                     SET est_lue = 1
                     WHERE id = :id AND utilisateur_id = :user_id AND est_lue = 0'
                );
                $miseAJour->execute([
                    'id' => $identifiantNotification,
                    'user_id' => $adminId,
                ]);
                rediriger($urlRetour);
            } catch (PDOException $exception) {
                error_log('PharmaCore notification update failed: ' . $exception->getMessage());
                $erreur = 'Cette notification n’a pas pu être mise à jour.';
            }
        }
    } else {
        http_response_code(400);
        $erreur = 'Cette action n’est pas disponible.';
    }
}

$lectureDisponible = false;
$notifications = [];
$nombreTotal = 0;
$nombreNonLues = 0;
$nombreLues = 0;
try {
    $compteurs = $pdo->prepare(
        'SELECT COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN est_lue = 0 THEN 1 ELSE 0 END), 0) AS non_lues,
                COALESCE(SUM(CASE WHEN est_lue = 1 THEN 1 ELSE 0 END), 0) AS lues
         FROM notifications
         WHERE utilisateur_id = :user_id'
    );
    $compteurs->execute(['user_id' => $adminId]);
    $statistiques = $compteurs->fetch();

    if (is_array($statistiques)) {
        $nombreTotal = (int) $statistiques['total'];
        $nombreNonLues = (int) $statistiques['non_lues'];
        $nombreLues = (int) $statistiques['lues'];
    }

    $conditions = ['utilisateur_id = :user_id'];
    $parametres = ['user_id' => $adminId];

    if ($vue === 'non-lues') {
        $conditions[] = 'est_lue = 0';
    } elseif ($vue === 'lues') {
        $conditions[] = 'est_lue = 1';
    }

    if ($recherche !== '') {
        $conditions[] = '(titre LIKE :recherche_titre OR message LIKE :recherche_message)';
        $termeRecherche = '%' . $recherche . '%';
        $parametres['recherche_titre'] = $termeRecherche;
        $parametres['recherche_message'] = $termeRecherche;
    }

    $requeteNotifications = $pdo->prepare(
        'SELECT id, titre, message, est_lue, date_creation
         FROM notifications
         WHERE ' . implode(' AND ', $conditions) . '
         ORDER BY est_lue ASC, date_creation DESC, id DESC
         LIMIT 100'
    );
    $requeteNotifications->execute($parametres);
    $notifications = $requeteNotifications->fetchAll();
    $lectureDisponible = true;
} catch (PDOException $exception) {
    error_log('PharmaCore notifications query failed: ' . $exception->getMessage());
    $erreur = 'Le centre de notifications est temporairement indisponible.';
}

rendreEnteteAdmin('Notifications', 'notifications', $nombreNonLues);
?>
<section class="notifications-module" aria-labelledby="notifications-title">
    <header class="page-heading notifications-heading">
        <div>
            <span class="eyebrow">CENTRE DE MESSAGES</span>
            <h1 id="notifications-title">Notifications</h1>
            <p>Suivez les informations importantes de votre espace PharmaCore.</p>
        </div>
        <?php if ($nombreNonLues > 0): ?>
            <form method="post" action="<?= e($urlRetour) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
                <input type="hidden" name="action" value="tout_marquer_comme_lu">
                <button class="button button-filter notifications-read-all" type="submit">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                    Tout marquer comme lu
                </button>
            </form>
        <?php endif; ?>
    </header>

    <?php if ($erreur !== ''): ?>
        <div class="alert alert-danger dashboard-alert" role="alert"><?= e($erreur) ?></div>
    <?php endif; ?>

    <?php if ($lectureDisponible): ?>
        <section class="notification-stats" aria-label="Résumé des notifications">
            <article class="notification-stat">
                <span class="notification-stat-icon notification-stat-icon-total" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                </span>
                <span class="notification-stat-copy"><span>Toutes les notifications</span><strong><?= e(number_format($nombreTotal, 0, ',', ' ')) ?></strong></span>
            </article>
            <article class="notification-stat">
                <span class="notification-stat-icon notification-stat-icon-unread" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </span>
                <span class="notification-stat-copy"><span>À consulter</span><strong><?= e(number_format($nombreNonLues, 0, ',', ' ')) ?></strong></span>
            </article>
            <article class="notification-stat">
                <span class="notification-stat-icon notification-stat-icon-read" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>
                </span>
                <span class="notification-stat-copy"><span>Déjà consultées</span><strong><?= e(number_format($nombreLues, 0, ',', ' ')) ?></strong></span>
            </article>
        </section>

        <section class="panel notification-inbox" aria-label="Boîte de réception">
            <div class="notification-inbox-header">
                <nav class="notification-filters" aria-label="Filtrer les notifications">
                    <?php foreach ([
                        'toutes' => ['Toutes', $nombreTotal],
                        'non-lues' => ['Non lues', $nombreNonLues],
                        'lues' => ['Lues', $nombreLues],
                    ] as $cleVue => [$libelleVue, $nombreVue]): ?>
                        <a class="notification-filter<?= $vue === $cleVue ? ' is-active' : '' ?>"
                           href="notifications.php?<?= e(http_build_query(['vue' => $cleVue, 'recherche' => $recherche])) ?>"
                           <?= $vue === $cleVue ? 'aria-current="page"' : '' ?>>
                            <?= e($libelleVue) ?><span><?= e(number_format($nombreVue, 0, ',', ' ')) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <form class="notification-search" method="get" action="notifications.php" role="search">
                    <input type="hidden" name="vue" value="<?= e($vue) ?>">
                    <label class="visually-hidden" for="notificationSearch">Rechercher dans vos notifications</label>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>
                    <input id="notificationSearch" name="recherche" type="search" maxlength="100" value="<?= e($recherche) ?>" placeholder="Rechercher un message…">
                    <button type="submit">Rechercher</button>
                </form>
            </div>

            <?php if ($notifications === []): ?>
                <div class="notification-empty">
                    <span class="notification-empty-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                    </span>
                    <h2><?= $recherche !== '' ? 'Aucun résultat trouvé' : ($vue === 'non-lues' ? 'Vous êtes à jour' : 'Votre boîte est vide') ?></h2>
                    <p>
                        <?php if ($recherche !== ''): ?>
                            Aucune notification ne correspond à « <?= e($recherche) ?> ». Essayez un autre terme.
                        <?php elseif ($vue === 'non-lues'): ?>
                            Toutes vos notifications ont été consultées.
                        <?php else: ?>
                            Les nouveaux messages importants apparaîtront ici.
                        <?php endif; ?>
                    </p>
                    <?php if ($recherche !== '' || $vue !== 'toutes'): ?>
                        <a class="button button-filter" href="notifications.php">Afficher toutes les notifications</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="notification-list" aria-label="Notifications">
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                        $dateCreation = new DateTimeImmutable((string) $notification['date_creation']);
                        $estLue = (int) $notification['est_lue'] === 1;
                        ?>
                        <article class="notification-item<?= $estLue ? '' : ' is-unread' ?>">
                            <span class="notification-item-icon<?= $estLue ? '' : ' is-unread' ?>" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                            </span>
                            <div class="notification-item-content">
                                <div class="notification-item-meta">
                                    <span class="notification-status<?= $estLue ? ' is-read' : '' ?>">
                                        <span aria-hidden="true"></span><?= $estLue ? 'Consultée' : 'Nouvelle' ?>
                                    </span>
                                    <time datetime="<?= e($dateCreation->format(DateTimeInterface::ATOM)) ?>"><?= e($dateCreation->format('d/m/Y · H:i')) ?></time>
                                </div>
                                <h2><?= e($notification['titre']) ?></h2>
                                <p><?= nl2br(e($notification['message'])) ?></p>
                            </div>
                            <?php if (!$estLue): ?>
                                <form method="post" action="<?= e($urlRetour) ?>" class="notification-item-action">
                                    <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
                                    <input type="hidden" name="action" value="marquer_comme_lu">
                                    <input type="hidden" name="notification_id" value="<?= e((int) $notification['id']) ?>">
                                    <button class="icon-button notification-mark-read" type="submit" aria-label="Marquer « <?= e($notification['titre']) ?> » comme consultée" title="Marquer comme consultée">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if (count($notifications) === 100): ?>
                    <p class="notification-list-note">Affichage limité à 100 notifications par sélection, avec les non lues en premier.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
<?php rendrePiedAdmin(); ?>

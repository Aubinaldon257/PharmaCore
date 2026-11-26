<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';
exigerAdministrateur();

function lireDateDashboard(mixed $valeur, string $nomChamp): DateTimeImmutable
{
    if (!is_string($valeur) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
        throw new InvalidArgumentException('La période sélectionnée contient une date invalide.');
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);
    $erreurs = DateTimeImmutable::getLastErrors();

    if (
        $date === false
        || ($erreurs !== false && ($erreurs['warning_count'] > 0 || $erreurs['error_count'] > 0))
        || $date->format('Y-m-d') !== $valeur
    ) {
        throw new InvalidArgumentException('La période sélectionnée contient une date invalide.');
    }

    return $date;
}

function preparerPeriodeDashboard(array $parametres): array
{
    $aujourdHui = new DateTimeImmutable('today');
    $periode = $parametres['period'] ?? '30';

    if (!is_string($periode) || !in_array($periode, ['7', '30', '90', 'custom'], true)) {
        throw new InvalidArgumentException('Choisissez une période valide.');
    }

    if ($periode === 'custom') {
        $debut = lireDateDashboard($parametres['start'] ?? null, 'start');
        $fin = lireDateDashboard($parametres['end'] ?? null, 'end');
    } else {
        $fin = $aujourdHui;
        $debut = $fin->modify('-' . ((int) $periode - 1) . ' days');
    }

    if ($debut > $fin) {
        throw new InvalidArgumentException('La date de début doit précéder la date de fin.');
    }

    if ($fin > $aujourdHui) {
        throw new InvalidArgumentException('La date de fin ne peut pas être dans le futur.');
    }

    if ((int) $debut->diff($fin)->format('%a') > 365) {
        throw new InvalidArgumentException('La période ne peut pas dépasser 366 jours.');
    }

    $granularite = $parametres['granularity'] ?? 'daily';
    if (!is_string($granularite) || !in_array($granularite, ['daily', 'weekly', 'monthly'], true)) {
        throw new InvalidArgumentException('Choisissez un regroupement valide pour les graphiques.');
    }

    return [
        'period' => $periode,
        'start' => $debut,
        'end' => $fin,
        'granularity' => $granularite,
    ];
}

function initialiserDonneesDashboard(): array
{
    return [
        'inventory' => [
            'total' => 0,
            'healthy' => 0,
            'low_stock' => 0,
            'out_of_stock' => 0,
            'expiring' => 0,
            'expired' => 0,
        ],
        'sales' => ['today' => 0, 'count' => 0, 'revenue' => 0.0],
        'buckets' => ['labels' => [], 'sales' => [], 'revenue' => []],
        'categories' => [],
        'top_products' => [],
        'recent_sales' => [],
        'expiring_products' => [],
        'low_stock_products' => [],
        'unread_notifications' => 0,
    ];
}

function construireBucketsDashboard(
    DateTimeImmutable $debut,
    DateTimeImmutable $fin,
    string $granularite
): array {
    $curseur = $debut;

    if ($granularite === 'weekly') {
        $curseur = $curseur->modify('-' . ((int) $curseur->format('N') - 1) . ' days');
    } elseif ($granularite === 'monthly') {
        $curseur = $curseur->modify('first day of this month');
    }

    $pas = match ($granularite) {
        'weekly' => 'P7D',
        'monthly' => 'P1M',
        default => 'P1D',
    };
    $format = match ($granularite) {
        'weekly' => 'Y-m-d',
        'monthly' => 'Y-m-01',
        default => 'Y-m-d',
    };
    $buckets = [];

    while ($curseur <= $fin) {
        $cle = $curseur->format($format);
        $buckets[$cle] = ['sales' => 0, 'revenue' => 0.0];
        $curseur = $curseur->add(new DateInterval($pas));
    }

    return $buckets;
}

function chargerDonneesDashboard(PDO $pdo, array $periode): array
{
    $donnees = initialiserDonneesDashboard();
    $debut = $periode['start'];
    $fin = $periode['end'];
    $finExclusive = $fin->modify('+1 day');
    $aujourdHui = (new DateTimeImmutable('today'))->format('Y-m-d');
    $finProcheExpiration = (new DateTimeImmutable($aujourdHui))->modify('+30 days')->format('Y-m-d');

    $requeteStock = $pdo->prepare(
        'SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN quantite <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
            SUM(CASE WHEN quantite > 0 AND date_expiration IS NOT NULL AND date_expiration < :expired_before THEN 1 ELSE 0 END) AS expired,
            SUM(CASE WHEN quantite > 0 AND date_expiration BETWEEN :near_start AND :near_end THEN 1 ELSE 0 END) AS expiring,
            SUM(CASE WHEN quantite BETWEEN 1 AND 5 AND (date_expiration IS NULL OR date_expiration > :low_expiry_after) THEN 1 ELSE 0 END) AS low_stock,
            SUM(CASE WHEN quantite > 5 AND (date_expiration IS NULL OR date_expiration > :healthy_expiry_after) THEN 1 ELSE 0 END) AS healthy
         FROM medicaments
         WHERE COALESCE(description, \'\') NOT LIKE \'DEMO DATA:%\''
    );
    $requeteStock->execute([
        'expired_before' => $aujourdHui,
        'near_start' => $aujourdHui,
        'near_end' => $finProcheExpiration,
        'low_expiry_after' => $finProcheExpiration,
        'healthy_expiry_after' => $finProcheExpiration,
    ]);
    $stock = $requeteStock->fetch();

    foreach ($donnees['inventory'] as $cle => $valeurInitiale) {
        $donnees['inventory'][$cle] = (int) ($stock[$cle] ?? $valeurInitiale);
    }

    $requeteVentesJour = $pdo->prepare(
        'SELECT COUNT(*) FROM ventes WHERE date_vente >= :today AND date_vente < :tomorrow'
    );
    $dateAujourdhui = new DateTimeImmutable($aujourdHui);
    $requeteVentesJour->execute([
        'today' => $dateAujourdhui->format('Y-m-d 00:00:00'),
        'tomorrow' => $dateAujourdhui->modify('+1 day')->format('Y-m-d 00:00:00'),
    ]);
    $donnees['sales']['today'] = (int) $requeteVentesJour->fetchColumn();

    $requeteVentes = $pdo->prepare(
        'SELECT COUNT(*) AS count, COALESCE(SUM(montant_total), 0) AS revenue
         FROM ventes
         WHERE date_vente >= :start_at AND date_vente < :end_at'
    );
    $requeteVentes->execute([
        'start_at' => $debut->format('Y-m-d 00:00:00'),
        'end_at' => $finExclusive->format('Y-m-d 00:00:00'),
    ]);
    $syntheseVentes = $requeteVentes->fetch();
    $donnees['sales'] = [
        'today' => $donnees['sales']['today'],
        'count' => (int) ($syntheseVentes['count'] ?? 0),
        'revenue' => (float) ($syntheseVentes['revenue'] ?? 0),
    ];

    $expressionsBucket = [
        'daily' => 'DATE(date_vente)',
        'weekly' => 'DATE_SUB(DATE(date_vente), INTERVAL WEEKDAY(date_vente) DAY)',
        'monthly' => "STR_TO_DATE(DATE_FORMAT(date_vente, '%Y-%m-01'), '%Y-%m-%d')",
    ];
    $expressionBucket = $expressionsBucket[$periode['granularity']];
    $requeteBuckets = $pdo->prepare(
        "SELECT {$expressionBucket} AS bucket, COUNT(*) AS sales, COALESCE(SUM(montant_total), 0) AS revenue
         FROM ventes
         WHERE date_vente >= :start_at AND date_vente < :end_at
         GROUP BY bucket
         ORDER BY bucket"
    );
    $requeteBuckets->execute([
        'start_at' => $debut->format('Y-m-d 00:00:00'),
        'end_at' => $finExclusive->format('Y-m-d 00:00:00'),
    ]);
    $buckets = construireBucketsDashboard($debut, $fin, $periode['granularity']);

    foreach ($requeteBuckets->fetchAll() as $ligne) {
        $cle = (new DateTimeImmutable((string) $ligne['bucket']))->format(
            $periode['granularity'] === 'monthly' ? 'Y-m-01' : 'Y-m-d'
        );
        if (array_key_exists($cle, $buckets)) {
            $buckets[$cle] = [
                'sales' => (int) $ligne['sales'],
                'revenue' => (float) $ligne['revenue'],
            ];
        }
    }

    $labels = [];
    $ventesParBucket = [];
    $chiffreAffairesParBucket = [];
    foreach ($buckets as $cle => $valeurs) {
        $labels[] = $cle;
        $ventesParBucket[] = $valeurs['sales'];
        $chiffreAffairesParBucket[] = $valeurs['revenue'];
    }
    $donnees['buckets'] = [
        'labels' => $labels,
        'sales' => $ventesParBucket,
        'revenue' => $chiffreAffairesParBucket,
    ];

    $donnees['categories'] = $pdo->query(
        "SELECT COALESCE(NULLIF(TRIM(categorie), ''), 'Sans catégorie') AS label, COUNT(*) AS count
         FROM medicaments
         WHERE COALESCE(description, '') NOT LIKE 'DEMO DATA:%'
         GROUP BY COALESCE(NULLIF(TRIM(categorie), ''), 'Sans catégorie')
         ORDER BY count DESC, label ASC"
    )->fetchAll();
    foreach ($donnees['categories'] as &$categorie) {
        $categorie['count'] = (int) $categorie['count'];
    }
    unset($categorie);

    $requeteTop = $pdo->prepare(
        'SELECT nom_medicament AS name, SUM(quantite) AS units, SUM(quantite * prix_unitaire) AS revenue
         FROM vente_details
         INNER JOIN ventes ON ventes.id = vente_details.vente_id
         WHERE ventes.date_vente >= :start_at AND ventes.date_vente < :end_at
         GROUP BY nom_medicament
         ORDER BY units DESC, revenue DESC, name ASC
         LIMIT 5'
    );
    $requeteTop->execute([
        'start_at' => $debut->format('Y-m-d 00:00:00'),
        'end_at' => $finExclusive->format('Y-m-d 00:00:00'),
    ]);
    $donnees['top_products'] = $requeteTop->fetchAll();

    $requeteVentesRecentes = $pdo->prepare(
        'SELECT ventes.id, ventes.date_vente, ventes.montant_total,
            COUNT(vente_details.id) AS line_count,
            COALESCE(SUM(vente_details.quantite), 0) AS units
         FROM ventes
         LEFT JOIN vente_details ON vente_details.vente_id = ventes.id
         WHERE ventes.date_vente >= :start_at AND ventes.date_vente < :end_at
         GROUP BY ventes.id, ventes.date_vente, ventes.montant_total
         ORDER BY ventes.date_vente DESC, ventes.id DESC
         LIMIT 8'
    );
    $requeteVentesRecentes->execute([
        'start_at' => $debut->format('Y-m-d 00:00:00'),
        'end_at' => $finExclusive->format('Y-m-d 00:00:00'),
    ]);
    $donnees['recent_sales'] = $requeteVentesRecentes->fetchAll();

    $requetePeremption = $pdo->prepare(
        'SELECT nom, quantite, date_expiration
         FROM medicaments
         WHERE COALESCE(description, \'\') NOT LIKE \'DEMO DATA:%\'
            AND quantite > 0 AND date_expiration BETWEEN :near_start AND :near_end
         ORDER BY date_expiration ASC, nom ASC
         LIMIT 5'
    );
    $requetePeremption->execute([
        'near_start' => $aujourdHui,
        'near_end' => $finProcheExpiration,
    ]);
    $donnees['expiring_products'] = $requetePeremption->fetchAll();

    $requeteStockFaible = $pdo->prepare(
        'SELECT nom, quantite
         FROM medicaments
         WHERE COALESCE(description, \'\') NOT LIKE \'DEMO DATA:%\'
            AND quantite BETWEEN 1 AND 5
            AND (date_expiration IS NULL OR date_expiration > :expiry_after)
         ORDER BY quantite ASC, nom ASC
         LIMIT 5'
    );
    $requeteStockFaible->execute(['expiry_after' => $finProcheExpiration]);
    $donnees['low_stock_products'] = $requeteStockFaible->fetchAll();

    $requeteNotifications = $pdo->prepare(
        'SELECT COUNT(*) FROM notifications WHERE utilisateur_id = :user_id AND est_lue = 0'
    );
    $requeteNotifications->execute(['user_id' => (int) $_SESSION['admin_id']]);
    $donnees['unread_notifications'] = (int) $requeteNotifications->fetchColumn();

    return $donnees;
}

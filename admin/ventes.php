<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
exigerAdministrateur();

$pdo = connexionPDO();
$erreur = '';
$notifications = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE utilisateur_id = :id AND est_lue = 0');
$notifications->execute(['id' => (int) $_SESSION['admin_id']]);
$notificationsNonLues = (int) $notifications->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemsPostes = $_POST['articles'] ?? null;
    if (!verifierJetonCsrf()) {
        $erreur = 'Votre session a expiré. Actualisez la page puis réessayez.';
    } elseif (!is_array($itemsPostes) || count($itemsPostes) < 1 || count($itemsPostes) > 40) {
        $erreur = 'Ajoutez au moins un médicament à la vente.';
    } else {
        $articles = [];
        foreach ($itemsPostes as $article) {
            if (!is_array($article)) {
                $erreur = 'Les lignes de vente sont invalides.';
                break;
            }
            $medicamentId = filter_var($article['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantite = filter_var($article['quantite'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($medicamentId === false || $quantite === false) {
                $erreur = 'Chaque ligne doit contenir un médicament et une quantité positive.';
                break;
            }
            $articles[$medicamentId] = ($articles[$medicamentId] ?? 0) + $quantite;
        }
        if ($erreur === '' && count($articles) > 0) {
            try {
                $pdo->beginTransaction();
                ksort($articles);
                $lecture = $pdo->prepare('SELECT id, nom, prix, quantite FROM medicaments WHERE id = :id FOR UPDATE');
                $miseAJour = $pdo->prepare('UPDATE medicaments SET quantite = quantite - :quantite WHERE id = :id AND quantite >= :minimum');
                $total = 0;
                $lignes = [];
                foreach ($articles as $medicamentId => $quantite) {
                    $lecture->execute(['id' => $medicamentId]);
                    $medicament = $lecture->fetch();
                    if (!$medicament) {
                        throw new DomainException('Un médicament choisi n’existe plus dans le catalogue.');
                    }
                    if ((int) $medicament['quantite'] < $quantite) {
                        throw new DomainException('Stock insuffisant pour « ' . (string) $medicament['nom'] . ' » (disponible : ' . (int) $medicament['quantite'] . ').');
                    }
                    $prix = (int) round((float) $medicament['prix']);
                    if ($prix > intdiv(PHP_INT_MAX, $quantite)) {
                        throw new DomainException('Le montant de cette ligne dépasse la limite autorisée.');
                    }
                    $totalLigne = $prix * $quantite;
                    if ($total > PHP_INT_MAX - $totalLigne) {
                        throw new DomainException('Le total de la vente dépasse la limite autorisée.');
                    }
                    $total += $totalLigne;
                    if ($total > 99999999) {
                        throw new DomainException('Le total dépasse la capacité actuelle du champ montant en base de données.');
                    }
                    $lignes[] = ['id' => (int) $medicament['id'], 'nom' => (string) $medicament['nom'], 'prix' => $prix, 'quantite' => $quantite];
                }
                $vente = $pdo->prepare('INSERT INTO ventes (utilisateur_id, montant_total) VALUES (:admin_id, :total)');
                $vente->execute(['admin_id' => (int) $_SESSION['admin_id'], 'total' => $total]);
                $venteId = (int) $pdo->lastInsertId();
                $detail = $pdo->prepare(
                    'INSERT INTO vente_details (vente_id, medicament_id, nom_medicament, quantite, prix_unitaire)
                     VALUES (:vente_id, :medicament_id, :nom, :quantite, :prix)'
                );
                foreach ($lignes as $ligne) {
                    $miseAJour->execute(['quantite' => $ligne['quantite'], 'id' => $ligne['id'], 'minimum' => $ligne['quantite']]);
                    if ($miseAJour->rowCount() !== 1) {
                        throw new DomainException('Le stock a changé pendant cette opération. La vente a été annulée.');
                    }
                    $detail->execute([
                        'vente_id' => $venteId,
                        'medicament_id' => $ligne['id'],
                        'nom' => $ligne['nom'],
                        'quantite' => $ligne['quantite'],
                        'prix' => $ligne['prix'],
                    ]);
                }
                $pdo->commit();
                rediriger('historique_ventes.php?created=1');
            } catch (DomainException $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $erreur = $exception->getMessage();
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('PharmaCore sale creation failed: ' . $exception->getMessage());
                $erreur = 'La vente n’a pas été enregistrée. Aucun stock n’a été modifié.';
            }
        }
    }
}

try {
    $catalogue = $pdo->query("SELECT id, nom, prix, quantite FROM medicaments WHERE quantite > 0 AND COALESCE(description, '') NOT LIKE 'DEMO DATA:%' ORDER BY nom")->fetchAll();
} catch (PDOException $exception) {
    error_log('PharmaCore sale catalog failed: ' . $exception->getMessage());
    $catalogue = [];
    $erreur = 'Le catalogue est indisponible ; la vente ne peut pas être préparée.';
}
require_once __DIR__ . '/../includes/admin_layout.php';
rendreEnteteAdmin('Nouvelle vente', 'ventes', $notificationsNonLues);
?>
<section class="page-heading"><div><span class="eyebrow">ENCAISSEMENT</span><h1>Nouvelle vente</h1><p>Le total est calculé côté serveur ; les lignes et le stock sont enregistrés dans une transaction.</p></div><a class="button button-filter" href="historique_ventes.php">Historique des ventes</a></section>
<?php if (isset($_GET['created'])): ?><div class="module-feedback" role="status">La vente a été enregistrée.</div><?php endif; ?>
<?php if ($erreur !== ''): ?><div class="alert alert-danger dashboard-alert" role="alert"><?= e($erreur) ?></div><?php endif; ?>
<?php if (!$catalogue): ?>
    <section class="panel module-empty"><strong>Aucun médicament disponible à la vente</strong><p>Ajoutez une référence avec une quantité en stock avant de commencer.</p><a class="button button-primary" href="ajouter_medicament.php">Ajouter un médicament</a></section>
<?php else: ?>
<section class="panel module-form-panel">
    <form method="post" class="sale-form" id="saleForm">
        <input type="hidden" name="csrf_token" value="<?= e(jetonCsrf()) ?>">
        <div class="sale-lines" id="saleLines"></div>
        <button class="button button-filter" type="button" id="addSaleLine">＋ Ajouter une ligne</button>
        <div class="sale-total"><span>Total estimé</span><strong id="saleTotal">0 BIF</strong><small>Montant définitif recalculé à partir des prix courants en base lors de l’enregistrement.</small></div>
        <button class="button button-primary" type="submit">Enregistrer la vente</button>
    </form>
</section>
<script>
(() => {
    const catalogue = <?= json_encode(array_map(static fn(array $item): array => ['id' => (int) $item['id'], 'name' => (string) $item['nom'], 'price' => (int) round((float) $item['prix']), 'stock' => (int) $item['quantite']], $catalogue), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?>;
    const lines = document.getElementById('saleLines');
    const total = document.getElementById('saleTotal');
    const formatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
    let index = 0;
    const update = () => {
        let sum = 0;
        lines.querySelectorAll('.sale-line').forEach((line) => {
            const id = Number(line.querySelector('[data-field="id"]').value);
            const quantity = Number(line.querySelector('[data-field="quantity"]').value);
            const product = catalogue.find((item) => item.id === id);
            if (product && Number.isSafeInteger(quantity) && quantity > 0) sum += product.price * quantity;
        });
        total.textContent = `${formatter.format(sum)} BIF`;
    };
    const addLine = () => {
        const row = document.createElement('div');
        row.className = 'sale-line';
        const select = document.createElement('select');
        select.name = `articles[${index}][id]`;
        select.dataset.field = 'id';
        select.required = true;
        const placeholder = new Option('Choisir un médicament', '');
        select.add(placeholder);
        catalogue.forEach((item) => select.add(new Option(`${item.name} · ${formatter.format(item.price)} BIF · stock ${item.stock}`, String(item.id))));
        const quantity = document.createElement('input');
        quantity.type = 'number'; quantity.name = `articles[${index}][quantite]`; quantity.dataset.field = 'quantity';
        quantity.min = '1'; quantity.step = '1'; quantity.value = '1'; quantity.required = true;
        const labelSelect = document.createElement('label'); labelSelect.textContent = 'Médicament'; labelSelect.append(select);
        const labelQuantity = document.createElement('label'); labelQuantity.textContent = 'Quantité'; labelQuantity.append(quantity);
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'button button-danger'; remove.textContent = 'Retirer';
        remove.addEventListener('click', () => { row.remove(); update(); });
        select.addEventListener('change', update); quantity.addEventListener('input', update);
        row.append(labelSelect, labelQuantity, remove); lines.append(row); index += 1; update();
    };
    document.getElementById('addSaleLine').addEventListener('click', addLine);
    document.getElementById('saleForm').addEventListener('submit', (event) => {
        if (!lines.querySelector('.sale-line')) { event.preventDefault(); addLine(); }
    });
    addLine();
})();
</script>
<?php endif; ?>
<?php rendrePiedAdmin(); ?>

<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
exigerAdministrateur();
require_once __DIR__ . '/../includes/admin_layout.php';

rendreEnteteAdmin('Guide de démarrage', 'guide');
?>
<section class="guide-intro">
    <span class="eyebrow">CENTRE D’AIDE · PHARMACORE</span>
    <h1>Guide de démarrage</h1>
    <p>Les repères essentiels pour gérer le catalogue, les stocks, les ventes et les comptes dans PharmaCore.</p>
</section>

<div class="guide-grid">
    <section class="panel guide-card">
        <span class="guide-number">01</span>
        <h2>Vue générale</h2>
        <p>Le tableau de bord résume l’inventaire, les alertes de stock et l’activité des ventes enregistrées. Utilisez le filtre de période pour consulter les indicateurs et rapports correspondants.</p>
        <a class="guide-link" href="dashboard.php">Ouvrir le tableau de bord <span aria-hidden="true">→</span></a>
    </section>

    <section class="panel guide-card">
        <span class="guide-number">02</span>
        <h2>Médicaments et stock</h2>
        <p>Ajoutez ou modifiez les fiches avec leur prix, leur quantité et leur date d’expiration. Vérifiez les informations et les unités avant de valider une mise à jour.</p>
        <div class="guide-links">
            <a class="guide-link" href="medicaments.php">Gérer les médicaments <span aria-hidden="true">→</span></a>
            <a class="guide-link" href="stock.php">Consulter le stock <span aria-hidden="true">→</span></a>
        </div>
    </section>

    <section class="panel guide-card">
        <span class="guide-number">03</span>
        <h2>Enregistrer une vente</h2>
        <p>Sélectionnez les produits et les quantités. PharmaCore recalcule le total côté serveur, vérifie les stocks et enregistre la vente dans une transaction. Si une vérification échoue, l’opération est annulée.</p>
        <div class="guide-links">
            <a class="guide-link" href="ventes.php">Créer une vente <span aria-hidden="true">→</span></a>
            <a class="guide-link" href="historique_ventes.php">Voir l’historique <span aria-hidden="true">→</span></a>
        </div>
    </section>

    <section class="panel guide-card">
        <span class="guide-number">04</span>
        <h2>Comptes et paramètres</h2>
        <p>Les clients peuvent créer un compte facultatif. Un administrateur connecté peut gérer les autres comptes administrateur. Les paramètres de compte permettent notamment de mettre à jour l’adresse e-mail et le mot de passe.</p>
        <div class="guide-links">
            <a class="guide-link" href="clients.php">Consulter les clients <span aria-hidden="true">→</span></a>
            <a class="guide-link" href="administrateurs.php">Gérer les administrateurs <span aria-hidden="true">→</span></a>
            <a class="guide-link" href="reglages.php">Ouvrir les paramètres <span aria-hidden="true">→</span></a>
        </div>
    </section>

    <section class="panel guide-card guide-card-wide">
        <span class="guide-number">À SAVOIR</span>
        <h2>Données, confidentialité et e-mails</h2>
        <ul class="guide-list">
            <li>Les montants sont affichés en francs burundais (BIF). Vérifiez la devise des données existantes avant leur utilisation.</li>
            <li>Les produits marqués <code>DEMO DATA:</code> sont des exemples, pas des références ni des stocks vérifiés. Remplacez-les par vos informations confirmées.</li>
            <li>Un lien de réinitialisation de mot de passe n’est envoyé que si le serveur de messagerie est configuré et accepte l’envoi. La remise dans la boîte de réception n’est pas garantie.</li>
            <li>Les comptes et historiques de vente sont des données opérationnelles. Utilisez des mots de passe robustes et limitez l’accès à l’administration.</li>
        </ul>
        <p class="guide-note">La documentation technique du projet est disponible dans le fichier README.md du dossier PharmaCore.</p>
    </section>
</div>
<?php rendrePiedAdmin(); ?>

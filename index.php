<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';

$informationsPharmacie = require __DIR__ . '/config/pharmacie.php';
$nomPharmacie = is_string($informationsPharmacie['nom'] ?? null) && trim($informationsPharmacie['nom']) !== ''
    ? trim($informationsPharmacie['nom'])
    : 'PharmaCore';
$slogan = is_string($informationsPharmacie['slogan'] ?? null) && trim($informationsPharmacie['slogan']) !== ''
    ? trim($informationsPharmacie['slogan'])
    : 'La santé mérite une attention particulière.';
$adresse = is_string($informationsPharmacie['adresse'] ?? null) ? trim($informationsPharmacie['adresse']) : '';
$ville = is_string($informationsPharmacie['ville'] ?? null) ? trim($informationsPharmacie['ville']) : '';
$codePostal = is_string($informationsPharmacie['code_postal'] ?? null) ? trim($informationsPharmacie['code_postal']) : '';
$telephone = is_string($informationsPharmacie['telephone'] ?? null) ? trim($informationsPharmacie['telephone']) : '';
$telephoneExemple = ($informationsPharmacie['telephone_exemple'] ?? false) === true;
$adresseExemple = ($informationsPharmacie['adresse_exemple'] ?? false) === true;
$emailContact = is_string($informationsPharmacie['email'] ?? null) && filter_var($informationsPharmacie['email'], FILTER_VALIDATE_EMAIL)
    ? trim($informationsPharmacie['email'])
    : '';
$lienCarte = is_string($informationsPharmacie['lien_carte'] ?? null)
    && filter_var($informationsPharmacie['lien_carte'], FILTER_VALIDATE_URL)
    && in_array((string) parse_url($informationsPharmacie['lien_carte'], PHP_URL_SCHEME), ['https', 'http'], true)
        ? $informationsPharmacie['lien_carte']
        : '';
$horaires = is_array($informationsPharmacie['horaires'] ?? null) ? $informationsPharmacie['horaires'] : [];
$services = is_array($informationsPharmacie['services'] ?? null) ? $informationsPharmacie['services'] : [];
$lieuFormate = trim(implode(' ', array_filter([$codePostal, $ville])));
$adresseComplete = trim(implode(', ', array_filter([$adresse, $lieuFormate])));
$telephoneLien = !$telephoneExemple ? (preg_replace('/[^0-9+]/', '', $telephone) ?? '') : '';
$adminConnecte = isset($_SESSION['admin_id'], $_SESSION['admin_role'])
    && is_int($_SESSION['admin_id'])
    && $_SESSION['admin_role'] === 'admin';
$clientConnecte = isset($_SESSION['client_id'], $_SESSION['client_role'])
    && is_int($_SESSION['client_id'])
    && $_SESSION['client_role'] === 'client';

$iconesServicesAutorisees = ['i-consultation', 'i-prescription', 'i-heart', 'i-clock', 'i-spark'];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f8f3">
    <meta name="description" content="<?= e($nomPharmacie) ?> : informations, services, localisation et contact.">
    <title><?= e($nomPharmacie) ?> · Votre pharmacie</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js" defer></script>
</head>
<body class="public-page">
    <a class="public-skip-link" href="#contenu-principal">Aller au contenu principal</a>
    <svg class="icon-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <symbol id="i-arrow-right" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></symbol>
        <symbol id="i-arrow-up" viewBox="0 0 24 24"><path d="M7 17 17 7M7 7h10v10"/></symbol>
        <symbol id="i-pin" viewBox="0 0 24 24"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></symbol>
        <symbol id="i-phone" viewBox="0 0 24 24"><path d="M21 16.4v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 1.1 3.6 2 2 0 0 1 3.1 1.4h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 3a2 2 0 0 1-.5 2.1L7 9.5a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c1 .4 2 .6 3 .8a2 2 0 0 1 1.6 1.9Z"/></symbol>
        <symbol id="i-mail" viewBox="0 0 24 24"><rect x="2.5" y="4.5" width="19" height="15" rx="2"/><path d="m3 6 9 7 9-7"/></symbol>
        <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
        <symbol id="i-consultation" viewBox="0 0 24 24"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H7l-4 2v-5.5A7.5 7.5 0 0 1 10.5 8H12m-1-4h10v9H15l-4 2V4Z"/><path d="M15 8.5h4m-2-2v4"/></symbol>
        <symbol id="i-prescription" viewBox="0 0 24 24"><path d="M7 3h8l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm8 0v5h5m-9 5h6m-6 4h6"/></symbol>
        <symbol id="i-heart" viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8ZM3 12h5l2-3 3 6 2-3h6"/></symbol>
        <symbol id="i-spark" viewBox="0 0 24 24"><path d="m12 2 1.8 7.2L21 12l-7.2 1.8L12 21l-1.8-7.2L3 12l7.2-2.8L12 2Z"/><path d="m19 14 1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3ZM5 1l1.3 3.7L10 6 6.3 7.3 5 11 3.7 7.3 0 6l3.7-1.3L5 1Z"/></symbol>
    </svg>

    <header class="public-header" id="accueil">
        <div class="public-header-inner">
            <a class="public-brand" href="#accueil" aria-label="<?= e($nomPharmacie) ?> — accueil">
                <span class="public-brand-mark" aria-hidden="true"><span></span><span></span></span>
                <span class="public-brand-name"><?= e($nomPharmacie) ?><small>VOTRE PHARMACIE</small></span>
            </a>
            <button class="public-menu-toggle" id="publicMenuToggle" type="button" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="publicNav">
                <span></span><span></span>
            </button>
            <nav class="public-nav" id="publicNav" aria-label="Navigation principale">
                <a href="#pharmacie">La pharmacie</a>
                <a href="#engagements">Nos engagements</a>
                <a href="#services">Nos services</a>
                <a href="#localisation">Nous trouver</a>
                <?php if ($adminConnecte): ?>
                    <a class="public-nav-account" href="admin/dashboard.php">Tableau de bord <svg aria-hidden="true"><use href="#i-arrow-up"/></svg></a>
                <?php elseif ($clientConnecte): ?>
                    <a class="public-nav-account" href="client/profil.php">Mon espace <svg aria-hidden="true"><use href="#i-arrow-up"/></svg></a>
                <?php else: ?>
                    <a class="public-nav-account" href="client/login.php">Connexion <svg aria-hidden="true"><use href="#i-arrow-up"/></svg></a>
                    <a class="public-nav-account" href="client/inscription.php">Créer un compte</a>
                <?php endif; ?>
                <a class="public-nav-contact" href="#contact">Nous contacter <svg aria-hidden="true"><use href="#i-arrow-up"/></svg></a>
            </nav>
        </div>
    </header>

    <main id="contenu-principal">
        <section class="public-hero">
            <div class="public-hero-grid"></div>
            <div class="public-hero-inner">
                <div class="public-hero-copy">
                    <span class="public-eyebrow"><span class="public-eyebrow-line"></span> BIENVENUE CHEZ <?= e(mb_strtoupper($nomPharmacie, 'UTF-8')) ?></span>
                    <h1>La santé se vit<br>mieux <span>ensemble.</span></h1>
                    <p class="public-hero-text">Un lieu d’écoute et de conseil, pensé pour vous accompagner avec attention dans votre quotidien.</p>
                    <div class="public-hero-actions">
                        <a class="public-button public-button-primary" href="#pharmacie">Découvrir la pharmacie <svg aria-hidden="true"><use href="#i-arrow-right"/></svg></a>
                        <a class="public-button public-button-quiet" href="#localisation"><span class="public-pin-mini"><svg aria-hidden="true"><use href="#i-pin"/></svg></span> Nous trouver</a>
                    </div>
                    <div class="public-hero-note"><span class="public-hero-note-icon"><svg aria-hidden="true"><use href="#i-spark"/></svg></span><span><?= e($slogan) ?></span></div>
                </div>
                <div class="public-hero-visual" aria-label="Illustration d'une pharmacie">
                    <div class="public-hero-orbit public-hero-orbit-one"></div>
                    <div class="public-hero-orbit public-hero-orbit-two"></div>
                    <div class="hero-art-glow"></div>
                    <div class="hero-card hero-card-back"><span class="hero-card-line"></span><span class="hero-card-line short"></span></div>
                    <div class="hero-pharmacy-window">
                        <div class="hero-window-sun"></div>
                        <div class="hero-window-cross"><span></span><span></span></div>
                        <div class="hero-window-canopy"></div>
                        <div class="hero-window-frame">
                            <div class="hero-window-inside"><span></span><span></span><span></span></div>
                            <div class="hero-window-door"></div>
                        </div>
                        <div class="hero-window-plant"><span></span><span></span><span></span><i></i></div>
                    </div>
                    <div class="hero-note-card">
                        <span class="hero-note-icon"><svg aria-hidden="true"><use href="#i-heart"/></svg></span>
                        <span><strong>À votre écoute</strong><small>Une attention, à chaque visite.</small></span>
                    </div>
                    <span class="hero-visual-caption">PRENDRE SOIN, AVEC ATTENTION</span>
                </div>
            </div>
            <a class="hero-scroll-cue" href="#pharmacie"><span></span> DÉCOUVRIR <span class="hero-scroll-arrow">↓</span></a>
        </section>

        <section class="public-trust-strip" aria-label="Les valeurs de la pharmacie">
            <div><span class="trust-index">01</span><span>Écoute attentive</span></div>
            <div><span class="trust-index">02</span><span>Conseils accessibles</span></div>
            <div><span class="trust-index">03</span><span>Accompagnement humain</span></div>
        </section>

        <section class="public-section public-story" id="pharmacie">
            <div class="public-section-inner public-story-grid">
                <div class="public-section-label"><span class="public-eyebrow"><span class="public-eyebrow-line"></span> QUI SOMMES-NOUS</span><span class="public-section-index">01 / LA PHARMACIE</span></div>
                <div class="public-story-content">
                    <h2>Un conseil juste commence par <span>une vraie écoute.</span></h2>
                    <div class="public-story-bottom">
                        <p>La pharmacie est un point de repère dans le parcours de santé. Nous souhaitons en faire un espace accueillant où chacun peut poser ses questions, mieux comprendre ses traitements et trouver une information attentive.</p>
                        <p class="public-story-secondary">Notre approche privilégie la clarté, la disponibilité et le respect de chaque personne. Vous avez une question ? L’équipe vous accueille et vous oriente vers le bon interlocuteur.</p>
                    </div>
                    <a class="public-text-link" href="#contact">Entrer en contact <svg aria-hidden="true"><use href="#i-arrow-right"/></svg></a>
                </div>
                <div class="public-story-visual" aria-hidden="true">
                    <div class="story-photo-shape">
                        <div class="story-photo-circle"></div>
                        <div class="story-photo-arc"></div>
                        <div class="story-photo-bottle story-bottle-one"><span></span></div>
                        <div class="story-photo-bottle story-bottle-two"><span></span></div>
                        <div class="story-photo-leaf story-leaf-one"></div>
                        <div class="story-photo-leaf story-leaf-two"></div>
                        <div class="story-photo-leaf story-leaf-three"></div>
                        <span class="story-photo-cross"><i></i><i></i></span>
                        <span class="story-photo-caption">À VOTRE RYTHME</span>
                    </div>
                    <span class="story-side-note">PRENDRE LE TEMPS<br>DE VOUS ÉCOUTER</span>
                </div>
            </div>
        </section>

        <section class="public-commitments" id="engagements">
            <div class="public-section-inner">
                <div class="commitments-heading">
                    <div><span class="public-eyebrow"><span class="public-eyebrow-line"></span> NOTRE PHILOSOPHIE</span><h2>La proximité, <span>en toute simplicité.</span></h2></div>
                    <p>Des repères simples pour un accueil qui fait de la place à chacun.</p>
                </div>
                <div class="commitments-grid">
                    <article class="commitment-card">
                        <span class="commitment-number">01</span>
                        <span class="commitment-icon"><svg aria-hidden="true"><use href="#i-consultation"/></svg></span>
                        <h3>Vous accueillir</h3>
                        <p>Vous recevoir avec attention, dans un espace où les questions sont les bienvenues.</p>
                        <span class="commitment-bottom">L’HUMAIN D’ABORD</span>
                    </article>
                    <article class="commitment-card commitment-card-featured">
                        <span class="commitment-number">02</span>
                        <span class="commitment-icon"><svg aria-hidden="true"><use href="#i-heart"/></svg></span>
                        <h3>Vous informer</h3>
                        <p>Partager des explications claires pour vous aider à mieux comprendre votre parcours de santé.</p>
                        <span class="commitment-bottom">DES ÉCHANGES CLAIRS</span>
                    </article>
                    <article class="commitment-card">
                        <span class="commitment-number">03</span>
                        <span class="commitment-icon"><svg aria-hidden="true"><use href="#i-spark"/></svg></span>
                        <h3>Vous accompagner</h3>
                        <p>Être un interlocuteur de proximité et vous orienter vers le professionnel adapté si besoin.</p>
                        <span class="commitment-bottom">UN ACCOMPAGNEMENT ATTENTIF</span>
                    </article>
                </div>
            </div>
        </section>

        <section class="public-section public-services" id="services">
            <div class="public-section-inner">
                <div class="services-heading">
                    <div><span class="public-eyebrow"><span class="public-eyebrow-line"></span> À VOTRE SERVICE</span><h2>Les services de la pharmacie.</h2></div>
                    <p>Un accompagnement adapté à vos besoins. Contactez-nous pour connaître les services disponibles et leurs modalités.</p>
                </div>
                <?php if ($services === []): ?>
                    <div class="services-empty"><span class="services-empty-icon"><svg aria-hidden="true"><use href="#i-prescription"/></svg></span><div><strong>Les services proposés seront précisés ici.</strong><span>Les prestations seront ajoutées après confirmation des services réellement proposés par la pharmacie.</span></div></div>
                <?php else: ?>
                    <div class="services-grid">
                        <?php foreach ($services as $index => $service):
                            if (!is_array($service)) {
                                continue;
                            }
                            $titreService = is_string($service['titre'] ?? null) ? trim($service['titre']) : '';
                            $descriptionService = is_string($service['description'] ?? null) ? trim($service['description']) : '';
                            $iconeService = is_string($service['icone'] ?? null) && in_array($service['icone'], $iconesServicesAutorisees, true)
                                ? $service['icone']
                                : 'i-consultation';
                            if ($titreService === '' || $descriptionService === '') {
                                continue;
                            }
                        ?>
                            <article class="service-card">
                                <span class="service-icon"><svg aria-hidden="true"><use href="#<?= e($iconeService) ?>"/></svg></span>
                                <span class="service-number"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                                <h3><?= e($titreService) ?></h3>
                                <p><?= e($descriptionService) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <p class="services-clarification">La disponibilité des services dépend de l’organisation de la pharmacie. N’hésitez pas à nous contacter avant votre visite.</p>
            </div>
        </section>

        <section class="public-location" id="localisation">
            <div class="public-section-inner location-grid">
                <div class="location-copy">
                    <span class="public-eyebrow public-eyebrow-light"><span class="public-eyebrow-line"></span> NOUS TROUVER</span>
                    <h2>Un point de repère,<br><span>près de vous.</span></h2>
                    <p>Préparez votre visite et vérifiez notre adresse ainsi que nos horaires.</p>
                    <div class="location-details">
                        <div class="location-detail"><span class="location-detail-icon"><svg aria-hidden="true"><use href="#i-pin"/></svg></span><div><span>ADRESSE<?= $adresseExemple ? ' · EXEMPLE À PERSONNALISER' : '' ?></span><?php if ($adresseComplete !== ''): ?><strong><?= e($adresseComplete) ?></strong><?php else: ?><strong>Adresse à renseigner</strong><small>Ces coordonnées seront ajoutées après confirmation.</small><?php endif; ?></div></div>
                        <div class="location-detail"><span class="location-detail-icon"><svg aria-hidden="true"><use href="#i-clock"/></svg></span><div><span>HORAIRES</span><?php if ($horaires !== []): foreach ($horaires as $jour => $plage): if (is_string($plage) && trim($plage) !== ''): ?><strong><?= e($jour) ?> <span class="location-hours-value"><?= e($plage) ?></span></strong><?php endif; endforeach; else: ?><strong>Horaires à confirmer</strong><small>Appelez-nous pour connaître les horaires.</small><?php endif; ?></div></div>
                    </div>
                    <?php if ($lienCarte !== '' && $adresseComplete !== ''): ?>
                        <a class="public-button location-button" href="<?= e($lienCarte) ?>" target="_blank" rel="noopener noreferrer">Itinéraire <svg aria-hidden="true"><use href="#i-arrow-up"/></svg></a>
                    <?php else: ?>
                        <span class="location-availability"><?= $adresseExemple ? 'Exemple de localisation uniquement : remplacez ces indications avant de communiquer l’adresse aux visiteurs.' : 'Aucun lien d’itinéraire confirmé pour le moment.' ?></span>
                    <?php endif; ?>
                </div>
                <div class="location-visual">
                    <div class="map-card" aria-label="Carte de localisation">
                        <div class="map-grid"></div>
                        <span class="map-road map-road-one"></span><span class="map-road map-road-two"></span>
                        <span class="map-park"><span></span></span><span class="map-block map-block-one"></span><span class="map-block map-block-two"></span><span class="map-block map-block-three"></span><span class="map-block map-block-four"></span>
                        <span class="map-location-pin"><svg aria-hidden="true"><use href="#i-pin"/></svg></span>
                        <div class="map-location-label"><span class="map-label-dot"></span><span><strong><?= e($nomPharmacie) ?></strong><small><?= $lieuFormate !== '' ? e($lieuFormate) : 'Localisation à renseigner' ?></small></span></div>
                        <span class="map-distance" aria-hidden="true">PHARMACIE</span>
                        <span class="map-caption">APERÇU INDICATIF — PAS UNE CARTE DE NAVIGATION</span>
                    </div>
                    <span class="location-visual-note">LES INFORMATIONS PRATIQUES<br>SONT CONFIRMÉES PAR L’ÉQUIPE</span>
                </div>
            </div>
        </section>

        <section class="public-contact" id="contact">
            <div class="public-section-inner contact-grid">
                <div class="contact-heading">
                    <span class="public-eyebrow"><span class="public-eyebrow-line"></span> PRENONS CONTACT</span>
                    <h2>Une question ?<br><span>Nous sommes à votre écoute.</span></h2>
                    <p>Pour confirmer un service, vérifier nos horaires ou obtenir une information, contactez l’équipe de la pharmacie.</p>
                </div>
                <div class="contact-card">
                    <?php if ($telephone !== '' && $telephoneLien !== ''): ?>
                        <a class="contact-item" href="tel:<?= e($telephoneLien) ?>"><span class="contact-item-icon"><svg aria-hidden="true"><use href="#i-phone"/></svg></span><span><small>TÉLÉPHONE</small><strong><?= e($telephone) ?></strong></span><svg class="contact-item-arrow" aria-hidden="true"><use href="#i-arrow-up"/></svg></a>
                    <?php elseif ($telephone !== '' && $telephoneExemple): ?>
                        <div class="contact-item contact-item-disabled"><span class="contact-item-icon"><svg aria-hidden="true"><use href="#i-phone"/></svg></span><span><small>TÉLÉPHONE · EXEMPLE À PERSONNALISER</small><strong><?= e($telephone) ?></strong></span></div>
                    <?php else: ?>
                        <div class="contact-item contact-item-disabled"><span class="contact-item-icon"><svg aria-hidden="true"><use href="#i-phone"/></svg></span><span><small>TÉLÉPHONE</small><strong>Numéro à renseigner</strong></span></div>
                    <?php endif; ?>
                    <?php if ($emailContact !== ''): ?>
                        <a class="contact-item" href="mailto:<?= e($emailContact) ?>"><span class="contact-item-icon"><svg aria-hidden="true"><use href="#i-mail"/></svg></span><span><small>E-MAIL</small><strong><?= e($emailContact) ?></strong></span><svg class="contact-item-arrow" aria-hidden="true"><use href="#i-arrow-up"/></svg></a>
                    <?php else: ?>
                        <div class="contact-item contact-item-disabled"><span class="contact-item-icon"><svg aria-hidden="true"><use href="#i-mail"/></svg></span><span><small>E-MAIL</small><strong>Adresse e-mail à renseigner</strong></span></div>
                    <?php endif; ?>
                    <div class="contact-card-note"><span class="contact-note-dot"></span>Nous ne traitons pas de demande médicale urgente par e-mail.</div>
                    <a class="developer-about-link" href="developer.php">
                        <span class="developer-about-icon" aria-hidden="true">i</span>
                        <span><strong>À propos du développeur</strong><small>Découvrir le profil de Sea Creed</small></span>
                        <svg aria-hidden="true"><use href="#i-arrow-up"/></svg>
                    </a>
                </div>
            </div>
        </section>

        <section class="public-final-cta">
            <div class="public-section-inner final-cta-inner"><span>UN LIEN DE CONFIANCE, AU QUOTIDIEN.</span><h2>Faisons connaissance.</h2><a href="#contact">Parlons de vos besoins <svg aria-hidden="true"><use href="#i-arrow-right"/></svg></a></div>
            <div class="final-cta-cross" aria-hidden="true"><span></span><span></span></div>
        </section>
    </main>

    <footer class="public-footer">
        <div class="public-section-inner public-footer-main">
            <div class="public-footer-brand">
                <a class="public-brand public-brand-footer" href="#accueil"><span class="public-brand-mark" aria-hidden="true"><span></span><span></span></span><span class="public-brand-name"><?= e($nomPharmacie) ?><small>VOTRE PHARMACIE</small></span></a>
                <p><?= e($slogan) ?></p>
            </div>
            <div class="public-footer-links"><span>EXPLORER</span><a href="#pharmacie">La pharmacie</a><a href="#engagements">Nos engagements</a><a href="#services">Nos services</a></div>
            <div class="public-footer-links"><span>INFORMATIONS</span><a href="#localisation">Nous trouver</a><a href="#contact">Nous contacter</a><a href="developer.php">À propos du développeur</a><a href="client/login.php">Connexion / inscription</a><a href="admin/login.php">Accès équipe</a></div>
            <div class="public-footer-contact"><span>NOUS JOINDRE</span><?php if ($telephone !== '' && $telephoneLien !== ''): ?><a href="tel:<?= e($telephoneLien) ?>"><?= e($telephone) ?></a><?php elseif ($telephone !== '' && $telephoneExemple): ?><span><?= e($telephone) ?> · exemple à personnaliser</span><?php else: ?><span>Coordonnées à renseigner</span><?php endif; ?><?php if ($adresseComplete !== ''): ?><small><?= e($adresseComplete) ?><?= $adresseExemple ? ' · exemple' : '' ?></small><?php endif; ?></div>
        </div>
        <div class="public-section-inner public-footer-bottom">
            <span>© <?= e(date('Y')) ?> <?= e($nomPharmacie) ?>. Tous droits réservés.</span>
            <span>Pour toute urgence, contactez les services d’urgence de votre pays.</span>
            <?php if ($adminConnecte): ?><a href="admin/dashboard.php">Espace administrateur <svg aria-hidden="true"><use href="#i-arrow-up"/></svg></a><?php else: ?><a href="admin/login.php">Accès équipe <svg aria-hidden="true"><use href="#i-arrow-up"/></svg></a><?php endif; ?>
        </div>
    </footer>

    <a class="public-back-top" href="#accueil" aria-label="Retour en haut de page"><svg aria-hidden="true"><use href="#i-arrow-up"/></svg></a>
</body>
</html>

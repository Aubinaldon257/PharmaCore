<?php
declare(strict_types=1);

return [
    'nom' => 'PharmaCore',
    'slogan' => 'La santé mérite une attention particulière.',
    'adresse' => 'Avenue Mewezi Gisabo',
    'code_postal' => '',
    'ville' => 'Bujumbura, Burundi',
    'telephone' => '+257 00000000',
    'telephone_exemple' => true,
    'adresse_exemple' => false,
    'email' => 'PharmaCore@gmail.com',
    'lien_carte' => '',
    'horaires' => [
        'Tous les jours' => '08 h 30 – 20 h 30',
    ],
    'services' => [
        [
            'titre' => 'Médicaments sur ordonnance',
            'description' => 'Délivrance sur présentation d’une ordonnance valide, dans le respect de la réglementation et des disponibilités.',
            'icone' => 'i-prescription',
        ],
        [
            'titre' => 'Conseils pharmaceutiques',
            'description' => 'Des réponses aux questions courantes sur la bonne utilisation des médicaments et les précautions à connaître.',
            'icone' => 'i-consultation',
        ],
        [
            'titre' => 'Accompagnement des traitements',
            'description' => 'Un échange avec l’équipe pour mieux comprendre votre traitement et savoir quand demander conseil à un professionnel de santé.',
            'icone' => 'i-heart',
        ],
        [
            'titre' => 'Prévention et bien-être',
            'description' => 'Des conseils de prévention et, selon les disponibilités, une sélection de produits de parapharmacie et de bien-être.',
            'icone' => 'i-spark',
        ],
    ],
];

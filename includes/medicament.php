<?php
declare(strict_types=1);

function validerMedicament(array $donnees): array
{
    $nom = trim(is_string($donnees['nom'] ?? null) ? $donnees['nom'] : '');
    $categorie = trim(is_string($donnees['categorie'] ?? null) ? $donnees['categorie'] : '');
    $description = trim(is_string($donnees['description'] ?? null) ? $donnees['description'] : '');
    $prixBrut = $donnees['prix'] ?? null;
    $quantiteBrute = $donnees['quantite'] ?? null;
    $expirationBrute = trim(is_string($donnees['date_expiration'] ?? null) ? $donnees['date_expiration'] : '');

    if ($nom === '' || mb_strlen($nom, 'UTF-8') > 150) {
        throw new InvalidArgumentException('Le nom est obligatoire et ne doit pas dépasser 150 caractères.');
    }
    if (mb_strlen($categorie, 'UTF-8') > 100) {
        throw new InvalidArgumentException('La catégorie ne doit pas dépasser 100 caractères.');
    }
    if (mb_strlen($description, 'UTF-8') > 65535) {
        throw new InvalidArgumentException('La description est trop longue.');
    }
    if (!is_string($prixBrut) && !is_int($prixBrut)) {
        throw new InvalidArgumentException('Saisissez un prix valide en BIF.');
    }
    $prixBrut = trim((string) $prixBrut);
    if (!preg_match('/^(0|[1-9]\d{0,7})$/', $prixBrut)) {
        throw new InvalidArgumentException('Le prix doit être un nombre entier positif en BIF (maximum 99 999 999).');
    }

    $quantite = filter_var($quantiteBrute, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($quantite === false) {
        throw new InvalidArgumentException('La quantité doit être un entier positif ou nul.');
    }

    $expiration = null;
    if ($expirationBrute !== '') {
        $dateExpiration = DateTimeImmutable::createFromFormat('!Y-m-d', $expirationBrute);
        $erreursDate = DateTimeImmutable::getLastErrors();
        if (
            $dateExpiration === false
            || ($erreursDate !== false && ($erreursDate['warning_count'] > 0 || $erreursDate['error_count'] > 0))
            || $dateExpiration->format('Y-m-d') !== $expirationBrute
        ) {
            throw new InvalidArgumentException('La date d’expiration est invalide.');
        }
        $expiration = $expirationBrute;
    }

    return [
        'nom' => $nom,
        'categorie' => $categorie === '' ? null : $categorie,
        'description' => $description === '' ? null : $description,
        'prix' => (int) $prixBrut,
        'quantite' => $quantite,
        'date_expiration' => $expiration,
    ];
}

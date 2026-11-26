<?php
declare(strict_types=1);

function connexionPDO(): PDO
{
    static $connexion = null;

    if ($connexion instanceof PDO) {
        return $connexion;
    }

    $hote = getenv('PHARMACORE_DB_HOST') ?: '127.0.0.1';
    $port = getenv('PHARMACORE_DB_PORT') ?: '3307';
    $base = getenv('PHARMACORE_DB_NAME') ?: 'pharmacore_db';
    $utilisateur = getenv('PHARMACORE_DB_USER') ?: 'root';
    $motDePasse = getenv('PHARMACORE_DB_PASSWORD');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $hote, $port, $base);
    $connexion = new PDO($dsn, $utilisateur, $motDePasse === false ? '' : $motDePasse, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connexion;
}

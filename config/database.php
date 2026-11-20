<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('MUFADDHOL_DB_HOST') ?: '127.0.0.1';
    $port = getenv('MUFADDHOL_DB_PORT') ?: '3306';
    $name = getenv('MUFADDHOL_DB_NAME') ?: 'mufaddhol_portfolio';
    $user = getenv('MUFADDHOL_DB_USER') ?: 'root';
    $pass = getenv('MUFADDHOL_DB_PASS') ?: '';

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}

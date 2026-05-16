<?php
$conn = new PDO(
    "pgsql:host=" . (getenv('DB_HOST') ?: 'postgres') .
    ";port="      . (getenv('DB_PORT') ?: '5432') .
    ";dbname="    . (getenv('DB_NAME') ?: 'educacao_financeira'),
    getenv('DB_USER') ?: 'edufinance',
    getenv('DB_PASS') ?: 'edufinance123',
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
);

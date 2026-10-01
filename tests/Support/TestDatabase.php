<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Database\Database;

/**
 * Base PostgreSQL dédiée aux tests, réinitialisée à partir de blissim.sql : les tests
 * s'exécutent sur le vrai schéma livré (clés étrangères, contraintes, triggers).
 */
final class TestDatabase
{
    /**
     * Crée la base de test si besoin, la réinitialise (schéma + jeu de données) et renvoie une connexion dessus.
     */
    public static function reset(): Database
    {
        $dsn = self::env('DATABASE_DSN');
        $user = self::env('DATABASE_USER');
        $password = self::env('DATABASE_PASSWORD');

        // Garde-fou : le script supprime toutes les tables, il ne doit viser qu'une base de test
        if (!preg_match('/dbname=(\w+_test)\b/', $dsn, $matches)) {
            throw new \LogicException(sprintf('Les tests doivent utiliser une base dont le nom finit par "_test" (DSN : %s).', $dsn));
        }

        self::createDatabaseIfMissing($dsn, $user, $password, $matches[1]);

        // Le script entier en un appel : PostgreSQL accepte plusieurs instructions sans paramètres
        $pdo = new \PDO($dsn, $user, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec((string) file_get_contents(\dirname(__DIR__, 2).'/blissim.sql'));

        return new Database($dsn, $user, $password);
    }

    /**
     * PostgreSQL n'a pas de CREATE DATABASE IF NOT EXISTS : on passe par la base de maintenance "postgres".
     */
    private static function createDatabaseIfMissing(string $dsn, string $user, string $password, string $databaseName): void
    {
        $pdo = new \PDO(
            (string) preg_replace('/dbname=\w+/', 'dbname=postgres', $dsn),
            $user,
            $password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );

        $statement = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = :name');
        $statement->execute(['name' => $databaseName]);

        if (false === $statement->fetchColumn()) {
            // Nom validé par le garde-fou de reset() (\w+_test)
            $pdo->exec(sprintf('CREATE DATABASE %s', $databaseName));
        }
    }

    /**
     * Recule created_at et updated_at d'une ligne d'une heure. Les colonnes TIMESTAMP(0) sont à la seconde :
     * sans ça, une modification faite dans la même seconde que la création serait invisible.
     */
    public static function moveDatesBackOneHour(Database $database, string $table, int $id): void
    {
        if (!preg_match('/^\w+$/', $table)) {
            throw new \InvalidArgumentException('Nom de table invalide.');
        }

        $database->execute(
            sprintf("UPDATE %s SET created_at = created_at - INTERVAL '1 hour', updated_at = updated_at - INTERVAL '1 hour' WHERE id = :id", $table),
            ['id' => $id],
        );
    }

    private static function env(string $name): string
    {
        return (string) ($_ENV[$name] ?? $_SERVER[$name] ?? getenv($name));
    }
}

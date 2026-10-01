<?php

declare(strict_types=1);

namespace App\Database;

use App\Exception\DatabaseException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Fournit la connexion PDO de l'application.
 *
 * La connexion est ouverte au premier usage seulement : une requête qui n'a pas
 * besoin de la base (ex. une page d'erreur) fonctionne même si PostgreSQL est arrêté.
 */
final class Database
{
    private ?\PDO $pdo = null;

    public function __construct(
        #[Autowire(env: 'DATABASE_DSN')]
        private readonly string $dsn,
        #[Autowire(env: 'DATABASE_USER')]
        private readonly ?string $user = null,
        #[Autowire(env: 'DATABASE_PASSWORD')]
        private readonly ?string $password = null,
    ) {
    }

    public function pdo(): \PDO
    {
        return $this->pdo ??= $this->connect();
    }

    /**
     * Prépare et exécute une requête paramétrée.
     * Les valeurs ne sont jamais concaténées au SQL : elles sont liées avec leur type.
     *
     * @param array<string, scalar|null> $parameters
     *
     * @throws DatabaseException
     */
    public function execute(string $sql, array $parameters = []): \PDOStatement
    {
        try {
            $statement = $this->pdo()->prepare($sql);

            foreach ($parameters as $name => $value) {
                $statement->bindValue(':'.$name, $value, \is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }

            $statement->execute();

            return $statement;
        } catch (\PDOException $e) {
            throw new DatabaseException('Erreur lors de l\'accès à la base de données.', 0, $e);
        }
    }

    private function connect(): \PDO
    {
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_TIMEOUT => 3,
        ];

        try {
            return new \PDO($this->dsn, $this->user, $this->password, $options);
        } catch (\PDOException $e) {
            // On ne propage pas le message brut : il peut contenir l'hôte ou l'utilisateur
            throw new DatabaseException('Connexion à la base de données impossible.', 0, $e);
        }
    }
}

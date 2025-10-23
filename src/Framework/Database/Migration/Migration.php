<?php

declare(strict_types=1);

namespace Framework\Database\Migration;

use PDO;

abstract class Migration
{
    /**
     * The PDO instance.
     *
     * @var \PDO
     */
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Exécute une requête SQL
     */
    protected function execute(string $sql): void
    {
        $this->pdo->exec(trim($sql));
    }

    /**
     * Méthode pour appliquer la migration
     */
    abstract public function up(): void;

    /**
     * Méthode pour annuler la migration
     */
    abstract public function down(): void;
}

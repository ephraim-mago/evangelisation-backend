<?php

declare(strict_types=1);

namespace Framework\Database\Migration;

use PDO;
use Exception;

class MigrationManager
{
    /**
     * The PDO instance.
     *
     * @var \PDO
     */
    protected PDO $pdo;

    /**
     * The migrations folder path.
     *
     * @var string
     */
    protected string $migrationsPath;

    /**
     * The migrations table name.
     *
     * @var string
     */
    protected string $migrationsTable = 'migrations';

    /**
     * Create a Migration Manager instance.
     *
     * @param string $databasePath
     * @param string $migrationsPath
     */
    public function __construct(string $databasePath, string $migrationsPath)
    {
        $this->pdo = new PDO("sqlite:$databasePath");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->migrationsPath = rtrim($migrationsPath, '/');

        $this->createMigrationsTable();
    }

    /**
     * Crée la table des migrations si elle n'existe pas
     */
    private function createMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration VARCHAR(255) UNIQUE NOT NULL,
            executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";

        $this->pdo->exec($sql);
    }

    /**
     * Génère un nouveau fichier de migration.
     *
     * @param string $name
     * @return string
     */
    public function createMigration(string $name): string
    {
        $timestamp = date('Y_m_d_His');
        $className = $this->toCamelCase($name);
        $filename = "{$timestamp}_{$name}.php";
        $filepath = "{$this->migrationsPath}/{$filename}";

        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }

        $template = $this->getMigrationTemplate($className);
        file_put_contents($filepath, $template);

        echo "Migration créée: {$filename}\n";

        return $filename;
    }

    /**
     * Template pour les nouvelles migrations
     */
    private function getMigrationTemplate(string $className): string
    {
        return <<<PHP
<?php

use \Framework\Database\Migration\Migration;

class {$className} extends Migration
{
    public function up(): void
    {
        // Votre code SQL pour appliquer la migration
        \$this->execute("CREATE TABLE table_name (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(): void
    {
        // Votre code SQL pour annuler la migration
        \$this->execute("DROP TABLE IF EXISTS table_name");
    }
}
PHP;
    }

    /**
     * Exécute toutes les migrations en attente
     */
    public function migrate(): void
    {
        $pendingMigrations = $this->getPendingMigrations();

        if (empty($pendingMigrations)) {
            echo "Aucune migration en attente.\n";

            return;
        }

        $this->pdo->beginTransaction();

        try {
            foreach ($pendingMigrations as $migration) {
                $this->runMigration($migration, 'up');
                $this->markAsExecuted($migration);

                echo "Migration exécutée: {$migration}\n";
            }

            $this->pdo->commit();

            echo "Toutes les migrations ont été appliquées avec succès.\n";
        } catch (Exception $e) {
            $this->pdo->rollBack();

            throw new Exception("Erreur lors de la migration: " . $e->getMessage());
        }
    }

    /**
     * Annule la dernière migration
     */
    public function rollback(int $steps = 1): void
    {
        $executedMigrations = $this->getExecutedMigrations();

        if (empty($executedMigrations)) {
            echo "Aucune migration à annuler.\n";

            return;
        }

        $migrationsToRollback = array_slice($executedMigrations, -$steps);

        $this->pdo->beginTransaction();

        try {
            foreach (array_reverse($migrationsToRollback) as $migration) {
                $this->runMigration($migration, 'down');
                $this->markAsNotExecuted($migration);

                echo "Migration annulée: {$migration}\n";
            }

            $this->pdo->commit();

            echo "Rollback terminé avec succès.\n";
        } catch (Exception $e) {
            $this->pdo->rollBack();

            throw new Exception("Erreur lors du rollback: " . $e->getMessage());
        }
    }

    /**
     * Affiche le statut des migrations
     */
    public function status(): void
    {
        $allMigrations = $this->getAllMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();

        echo "Statut des migrations:\n";
        echo str_repeat("-", 50) . "\n";

        foreach ($allMigrations as $migration) {
            $status = in_array(
                $migration,
                $executedMigrations
            ) ? "✓ Exécutée" : "✗ En attente";

            echo "{$migration} - {$status}\n";
        }
    }

    /**
     * Récupère les migrations en attente
     */
    private function getPendingMigrations(): array
    {
        $allMigrations = $this->getAllMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();

        return array_diff($allMigrations, $executedMigrations);
    }

    /**
     * Récupère toutes les migrations exécutées
     */
    private function getExecutedMigrations(): array
    {
        $stmt = $this->pdo->query(
            "SELECT migration FROM {$this->migrationsTable} ORDER BY id"
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Récupère tous les fichiers de migration
     */
    private function getAllMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob("{$this->migrationsPath}/*.php");
        $migrations = [];

        foreach ($files as $file) {
            $migrations[] = basename($file, '.php');
        }

        sort($migrations);

        return $migrations;
    }

    /**
     * Exécute une migration
     */
    private function runMigration(string $migrationName, string $direction): void
    {
        $filepath = "{$this->migrationsPath}/{$migrationName}.php";

        if (!file_exists($filepath)) {
            throw new Exception("Fichier de migration non trouvé: {$filepath}");
        }

        require_once $filepath;

        $className = $this->extractClassName($migrationName);
        $migration = new $className($this->pdo);

        if ($direction === 'up') {
            $migration->up();
        } else {
            $migration->down();
        }
    }

    /**
     * Marque une migration comme exécutée
     */
    private function markAsExecuted(string $migration): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->migrationsTable} (migration) VALUES (?)"
        );

        $stmt->execute([$migration]);
    }

    /**
     * Marque une migration comme non exécutée
     */
    private function markAsNotExecuted(string $migration): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->migrationsTable} WHERE migration = ?");

        $stmt->execute([$migration]);
    }

    /**
     * Extrait le nom de classe d'un nom de migration
     */
    private function extractClassName(string $migrationName): string
    {
        // Supprime le timestamp au début (format: YYYY_MM_DD_HHMMSS_name)
        $parts = explode('_', $migrationName);
        $nameParts = array_slice($parts, 4); // Supprime les 4 premières parties (date/heure)
        $name = implode('_', $nameParts);

        return $this->toCamelCase($name);
    }

    /**
     * Convertit snake_case en CamelCase
     */
    private function toCamelCase(string $string): string
    {
        return str_replace(
            ' ',
            '',
            ucwords(str_replace('_', ' ', $string))
        );
    }
}

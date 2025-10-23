<?php

use \Framework\Database\Migration\Migration;

class CreatePersonalAccessTokensTable extends Migration
{
    public function up(): void
    {
        // Votre code SQL pour appliquer la migration
        $this->execute("CREATE TABLE personal_access_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tokenable_type TEXT NOT NULL,
            tokenable_id TEXT NOT NULL,
            name TEXT NOT NULL,
            token TEXT UNIQUE NOT NULL,
            abilities TEXT NULL,
            last_used_at DATETIME NULL,
            expires_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(): void
    {
        // Votre code SQL pour annuler la migration
        $this->execute("DROP TABLE IF EXISTS personal_access_tokens");
    }
}

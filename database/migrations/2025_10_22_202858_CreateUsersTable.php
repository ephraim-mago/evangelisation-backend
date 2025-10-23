<?php

use \Framework\Database\Migration\Migration;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        // Votre code SQL pour appliquer la migration
        $this->execute("CREATE TABLE users (
            id TEXT PRIMARY KEY,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            phone TEXT,
            role TEXT NOT NULL CHECK (role IN ('admin', 'winner', 'framer')),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(): void
    {
        // Votre code SQL pour annuler la migration
        $this->execute("DROP TABLE IF EXISTS users");
    }
}

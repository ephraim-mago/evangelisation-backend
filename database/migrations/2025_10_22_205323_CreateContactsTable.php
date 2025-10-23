<?php

use \Framework\Database\Migration\Migration;

class CreateContactsTable extends Migration
{
    public function up(): void
    {
        // Votre code SQL pour appliquer la migration
        $this->execute("CREATE TABLE contacts (
            id TEXT PRIMARY KEY,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            gender TEXT NOT NULL CHECK (gender IN ('F', 'M')),
            phone TEXT NOT NULL,
            email TEXT UNIQUE NULL,
            address TEXT NULL,
            notes TEXT NULL,
            meetingAt DATETIME DEFAULT CURRENT_TIMESTAMP,
            status TEXT NOT NULL CHECK (status IN ('new', 'favorable', 'not_favorable', 'continued')),
            source TEXT NOT NULL CHECK (source IN ('evangelization', 'invitation')),
            invitedBy TEXT NULL,
            winnerId TEXT NULL,
            framerId TEXT NULL,
            campaignId TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down(): void
    {
        // Votre code SQL pour annuler la migration
        $this->execute("DROP TABLE IF EXISTS contacts");
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // raw_token_data is cast as encrypted:array on the model, producing a cipher string.
        // The column must be text, not json/jsonb, to accept the encrypted value.
        DB::statement('ALTER TABLE social_connections ALTER COLUMN raw_token_data TYPE text USING raw_token_data::text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE social_connections ALTER COLUMN raw_token_data TYPE jsonb USING raw_token_data::jsonb');
    }
};

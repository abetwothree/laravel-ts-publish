<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Use raw SQL to create columns without explicit types (SQLite allows this).
        // This produces columns with an empty type string in the schema, which
        // exercises the ModelTransformer unknown-type fallback path.
        DB::statement('CREATE TABLE untyped_columns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            accessor_col,
            cast_col,
            nullable_accessor_col
        )');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS untyped_columns');
    }
};

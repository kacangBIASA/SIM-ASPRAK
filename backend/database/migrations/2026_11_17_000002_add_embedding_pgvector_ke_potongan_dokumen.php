<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        $dim = (int) config('services.gemini.dimensi');
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement("ALTER TABLE potongan_dokumen ADD COLUMN embedding vector($dim)");
        DB::statement('UPDATE potongan_dokumen SET embedding = (vektor::text)::vector');
        DB::statement('CREATE INDEX potongan_dokumen_embedding_idx ON potongan_dokumen USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE potongan_dokumen DROP COLUMN embedding');
        }
    }
};

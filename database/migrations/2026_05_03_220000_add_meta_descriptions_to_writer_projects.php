<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `meta_descriptions` (json list) so the wizard's Strategy step
 * can present multiple meta-description candidates to choose from,
 * mirroring the `seo_titles` UX. The chosen one is still persisted
 * to `meta_description` (single string) which is what gets written
 * into _ebq_description on Save as draft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('writer_projects', function (Blueprint $table): void {
            $table->json('meta_descriptions')->nullable()->after('meta_description');
        });
    }

    public function down(): void
    {
        Schema::table('writer_projects', function (Blueprint $table): void {
            $table->dropColumn('meta_descriptions');
        });
    }
};

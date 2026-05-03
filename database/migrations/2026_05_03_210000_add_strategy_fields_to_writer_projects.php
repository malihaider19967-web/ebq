<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand the Blog Post Wizard with locale + audience + tone + strategy
 * outputs. Step 1 of the wizard (Topic) gains country / language /
 * tone / audience selectors so every downstream LLM call honours
 * them. A new Strategy step surfaces SEO titles, meta tags, FAQs,
 * keyword suggestions, and link suggestions before the user
 * generates the full draft. The chosen meta_* fields are written
 * into _ebq_title / _ebq_description / _ebq_og_* on the WP post when
 * the user saves the draft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('writer_projects', function (Blueprint $table): void {
            // Locale + voice — drives brief and full-article generation.
            $table->string('country', 2)->nullable()->after('additional_keywords');
            $table->string('language', 8)->nullable()->after('country');
            $table->string('tone', 32)->nullable()->after('language');
            $table->string('audience', 32)->nullable()->after('tone');

            // Strategy outputs — populated when the user runs the
            // Strategy step. Each is independently regenerable.
            $table->json('seo_titles')->nullable()->after('chat_history');
            $table->string('meta_title', 200)->nullable()->after('seo_titles');
            $table->string('meta_description', 320)->nullable()->after('meta_title');
            $table->string('og_title', 200)->nullable()->after('meta_description');
            $table->string('og_description', 320)->nullable()->after('og_title');
            $table->json('faqs')->nullable()->after('og_description');
            $table->json('keyword_suggestions')->nullable()->after('faqs');
            $table->json('link_suggestions')->nullable()->after('keyword_suggestions');
        });
    }

    public function down(): void
    {
        Schema::table('writer_projects', function (Blueprint $table): void {
            $table->dropColumn([
                'country', 'language', 'tone', 'audience',
                'seo_titles', 'meta_title', 'meta_description',
                'og_title', 'og_description',
                'faqs', 'keyword_suggestions', 'link_suggestions',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original `websites` unique was (user_id, ga_property_id, gsc_site_url),
 * from when every site connected GA + GSC. Since onboarding now lets clients
 * skip both sources, a domain-only site stores BOTH as '' — so a user's second
 * sourceless site collided on ('', '') ("Duplicate entry '9--'").
 *
 * Replace it with the real natural key, unique (user_id, domain) — exactly what
 * WebsitesList's updateOrCreate already keys on. Idempotent: MySQL DDL isn't
 * transactional, so this guards each step (a prior partial run already dropped
 * the old unique on production). We intentionally KEEP the non-unique
 * (user_id, domain) index — it backs the user_id foreign key, so it can't be
 * dropped, and the extra unique alongside it is harmless. Index-only; no row
 * data touched. Verified zero duplicate (user_id, domain) rows first.
 */
return new class extends Migration {
    public function up(): void
    {
        $names = collect(Schema::getIndexes('websites'))->pluck('name')->all();

        Schema::table('websites', function (Blueprint $table) use ($names): void {
            if (in_array('websites_user_id_ga_property_id_gsc_site_url_unique', $names, true)) {
                $table->dropUnique(['user_id', 'ga_property_id', 'gsc_site_url']);
            }
            if (! in_array('websites_user_id_domain_unique', $names, true)) {
                $table->unique(['user_id', 'domain']);
            }
        });
    }

    public function down(): void
    {
        $names = collect(Schema::getIndexes('websites'))->pluck('name')->all();

        Schema::table('websites', function (Blueprint $table) use ($names): void {
            if (in_array('websites_user_id_domain_unique', $names, true)) {
                $table->dropUnique(['user_id', 'domain']);
            }
            if (! in_array('websites_user_id_ga_property_id_gsc_site_url_unique', $names, true)) {
                $table->unique(['user_id', 'ga_property_id', 'gsc_site_url']);
            }
        });
    }
};

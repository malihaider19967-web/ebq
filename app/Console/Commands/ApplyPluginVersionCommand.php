<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\WordPressPluginSourceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;

class ApplyPluginVersionCommand extends Command
{
    protected $signature = 'ebq:apply-plugin-version
                            {version : Version string, e.g. 2.3.0 or 2.3.0-beta}
                            {--package : Also run ebq:package-plugin to public/downloads/ebq-seo.zip}';

    protected $description = 'Update Version + EBQ_SEO_VERSION in ebq-seo-wp/ebq-seo.php (run as a user who can write that file, e.g. deploy over SSH).';

    public function handle(WordPressPluginSourceService $source): int
    {
        $version = (string) $this->argument('version');

        try {
            $source->setVersionInSource($version);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Updated ebq-seo.php to version '.$version.'.');

        if ($this->option('package')) {
            $code = Artisan::call('ebq:package-plugin', ['--output' => 'public/downloads/ebq-seo.zip']);
            if ($code !== 0) {
                $this->error('ebq:package-plugin failed (exit code '.$code.').');

                return self::FAILURE;
            }
            $this->info('Packaged plugin to public/downloads/ebq-seo.zip.');
        }

        return self::SUCCESS;
    }
}

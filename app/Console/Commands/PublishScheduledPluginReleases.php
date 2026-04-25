<?php

namespace App\Console\Commands;

use App\Services\ClientActivityLogger;
use App\Services\PluginReleaseResolver;
use Illuminate\Console\Command;

class PublishScheduledPluginReleases extends Command
{
    protected $signature = 'ebq:publish-scheduled-plugin-releases';

    protected $description = 'Publish scheduled WordPress plugin releases';

    public function handle(PluginReleaseResolver $resolver, ClientActivityLogger $logger): int
    {
        $count = $resolver->publishScheduled();
        if ($count > 0) {
            $logger->log('plugin_release.scheduled_published', meta: ['count' => $count], actorUserId: null);
        }

        $this->info("Published {$count} scheduled plugin release(s).");

        return self::SUCCESS;
    }
}

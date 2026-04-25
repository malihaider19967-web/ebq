<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;

class WordPressPluginSourceService
{
    private const VERSION_PATTERN = '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/';

    public function pluginMainFile(): string
    {
        return base_path('ebq-seo-wp/ebq-seo.php');
    }

    public function readCurrentVersion(): ?string
    {
        $file = $this->pluginMainFile();
        if (! is_file($file)) {
            return null;
        }

        $contents = (string) file_get_contents($file, false, null, 0, 8192);

        if (preg_match('/^\s*\*\s*Version:\s*(.+?)\s*$/mi', $contents, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Updates the plugin header Version line and EBQ_SEO_VERSION in ebq-seo.php, then runs packaging.
     *
     * @throws InvalidArgumentException
     */
    public function syncVersionAndPackage(string $version): void
    {
        $this->setVersionInSource($version);
        $code = Artisan::call('ebq:package-plugin', ['--output' => 'public/downloads/ebq-seo.zip']);
        if ($code !== 0) {
            throw new InvalidArgumentException('Failed to package plugin (ebq:package-plugin exited '.$code.').');
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setVersionInSource(string $version): void
    {
        $version = trim($version);
        if (! preg_match(self::VERSION_PATTERN, $version)) {
            throw new InvalidArgumentException('Version must look like 1.2.3 or 1.2.3-beta (letters, numbers, dots, hyphens after patch).');
        }

        $path = $this->pluginMainFile();
        if (! is_file($path)) {
            throw new InvalidArgumentException('Plugin main file missing: '.$path);
        }

        $contents = (string) file_get_contents($path);
        $updated = preg_replace('/^\s*\*\s*Version:\s*.+$/m', ' * Version:           '.$version, $contents, 1, $headerCount);
        if ($headerCount === 0) {
            throw new InvalidArgumentException('Could not find Version header in ebq-seo.php.');
        }

        $updated = preg_replace(
            "/define\s*\(\s*'EBQ_SEO_VERSION'\s*,\s*'[^']*'\s*\)/",
            "define('EBQ_SEO_VERSION', '".$version."')",
            $updated,
            1,
            $defineCount
        );
        if ($defineCount === 0) {
            throw new InvalidArgumentException("Could not find EBQ_SEO_VERSION define in ebq-seo.php.");
        }

        if (file_put_contents($path, $updated) === false) {
            throw new InvalidArgumentException('Failed to write ebq-seo.php.');
        }
    }
}

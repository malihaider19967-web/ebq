<?php

declare(strict_types=1);

namespace App\Support;

final class Timezones
{
    /**
     * @return array<string, list<string>>
     */
    public static function groupedIdentifiers(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $groups = [];
        foreach (timezone_identifiers_list() as $id) {
            if (str_starts_with($id, 'Etc/')) {
                continue;
            }
            $parts = explode('/', $id, 2);
            $region = count($parts) === 2 ? $parts[0] : 'Other';
            $groups[$region][] = $id;
        }
        ksort($groups);
        foreach ($groups as &$ids) {
            sort($ids);
        }

        return $cached = $groups;
    }
}

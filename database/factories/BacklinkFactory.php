<?php

namespace Database\Factories;

use App\Enums\BacklinkType;
use App\Models\Backlink;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Backlink>
 */
class BacklinkFactory extends Factory
{
    protected $model = Backlink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = fake()->domainName();

        return [
            'website_id' => Website::factory(),
            'tracked_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'referring_page_url' => 'https://example.com/'.fake()->slug(),
            'target_page_url' => 'https://'.$domain.'/'.fake()->slug(),
            'domain_authority' => fake()->optional()->numberBetween(1, 100),
            'spam_score' => fake()->optional()->numberBetween(0, 100),
            'anchor_text' => fake()->optional()->words(3, true),
            'type' => fake()->randomElement(BacklinkType::cases()),
            'is_dofollow' => fake()->boolean(80),
        ];
    }
}

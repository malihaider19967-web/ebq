<?php

namespace Database\Factories;

use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Website>
 */
class WebsiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain' => fake()->domainName(),
            'ga_property_id' => 'properties/'.fake()->numberBetween(100000, 999999),
            'gsc_site_url' => 'sc-domain:'.fake()->domainName(),
        ];
    }
}

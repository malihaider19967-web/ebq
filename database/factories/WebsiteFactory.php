<?php

namespace Database\Factories;

use App\Models\GoogleAccount;
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
     * Default leaves the per-source account FKs null (the source pickers
     * set them in real flows). Use the states below when a test needs a
     * website that reads as GA/GSC-connected via {@see Website::hasGa()} /
     * {@see Website::hasGsc()}.
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

    /**
     * Fully connected: both GA and GSC backed by one Google account.
     */
    public function withBothSources(): static
    {
        return $this->afterCreating(function (Website $website): void {
            $account = GoogleAccount::factory()->create(['user_id' => $website->user_id]);
            $website->forceFill([
                'ga_google_account_id' => $account->id,
                'gsc_google_account_id' => $account->id,
            ])->save();
        });
    }

    /**
     * GA connected, Search Console absent.
     */
    public function withGaOnly(): static
    {
        return $this->state(['gsc_site_url' => ''])->afterCreating(function (Website $website): void {
            $account = GoogleAccount::factory()->create(['user_id' => $website->user_id]);
            $website->forceFill(['ga_google_account_id' => $account->id])->save();
        });
    }

    /**
     * Search Console connected, GA absent.
     */
    public function withGscOnly(): static
    {
        return $this->state(['ga_property_id' => ''])->afterCreating(function (Website $website): void {
            $account = GoogleAccount::factory()->create(['user_id' => $website->user_id]);
            $website->forceFill(['gsc_google_account_id' => $account->id])->save();
        });
    }

    /**
     * Onboarded with neither source (the "skip / PageSpeed-only" path).
     */
    public function withNoSources(): static
    {
        return $this->state([
            'ga_property_id' => '',
            'gsc_site_url' => '',
            'ga_google_account_id' => null,
            'gsc_google_account_id' => null,
        ]);
    }
}

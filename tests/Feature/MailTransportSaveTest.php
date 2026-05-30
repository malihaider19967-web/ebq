<?php

namespace Tests\Feature;

use App\Livewire\Settings\MailTransport;
use App\Models\MailTransport as MailTransportModel;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MailTransportSaveTest extends TestCase
{
    use RefreshDatabase;

    private function whitelabelUser(): User
    {
        Plan::create([
            'slug' => 'agency',
            'name' => 'Agency',
            'display_order' => 3,
            'is_active' => true,
            'plan_features' => ['report_whitelabel' => true],
        ]);

        return User::factory()->create(['current_plan_slug' => 'agency']);
    }

    public function test_saving_with_ebq_default_provider_succeeds(): void
    {
        // Regression: an empty provider ("EBQ default") used to fail the
        // `required` rule, so Save silently did nothing.
        $user = $this->whitelabelUser();

        Livewire::actingAs($user)
            ->test(MailTransport::class)
            ->set('provider', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true);
    }

    public function test_saving_ebq_default_removes_an_existing_transport(): void
    {
        $user = $this->whitelabelUser();
        MailTransportModel::create([
            'user_id' => $user->id,
            'website_id' => null,
            'provider' => 'smtp',
            'from_address' => 'old@example.com',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
        ]);

        Livewire::actingAs($user)
            ->test(MailTransport::class)
            ->set('provider', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true);

        $this->assertDatabaseMissing('mail_transports', ['user_id' => $user->id]);
    }

    public function test_saving_smtp_transport_persists_a_row(): void
    {
        $user = $this->whitelabelUser();

        Livewire::actingAs($user)
            ->test(MailTransport::class)
            ->set('provider', 'smtp')
            ->set('from_address', 'reports@acme.test')
            ->set('display_name', 'Acme Reports')
            ->set('smtp_host', 'smtp.acme.test')
            ->set('smtp_port', 587)
            ->set('smtp_encryption', 'tls')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true);

        $this->assertDatabaseHas('mail_transports', [
            'user_id' => $user->id,
            'provider' => 'smtp',
            'from_address' => 'reports@acme.test',
            'smtp_host' => 'smtp.acme.test',
        ]);
    }
}

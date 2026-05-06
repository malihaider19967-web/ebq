<?php

namespace App\Livewire\Onboarding;

use App\Models\Research\Niche;
use App\Models\Website;
use App\Services\Research\Niche\NicheClassificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Step 3 of onboarding (post Google-connect, post first GSC sync).
 *
 * Runs NicheClassificationService::classify synchronously on the user's
 * primary website and lets the user accept / reweight / remove the
 * detected niches before persisting them with source='hybrid'.
 *
 * The component is a no-op (renders nothing) when:
 *   - the user has no website yet, or
 *   - niches have already been confirmed manually (last_classified_at set
 *     by the user, source='hybrid' or 'user'), or
 *   - the website has no GSC rows yet (so classification would be empty
 *     and the user has nothing useful to confirm).
 */
class ConfirmNiches extends Component
{
    public bool $visible = false;
    public ?int $websiteId = null;

    /** @var array<int, array{niche_id:int, name:string, weight:float, is_primary:bool, keep:bool}> */
    public array $assignments = [];

    public string $status = '';

    public function mount(NicheClassificationService $service): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $website = Website::query()->where('user_id', $user->id)->where('domain', '!=', '')->orderBy('id')->first();
        if ($website === null) {
            return;
        }

        $hasUserConfirmed = DB::table('website_niche_map')
            ->where('website_id', $website->id)
            ->whereIn('source', ['hybrid', 'user'])
            ->exists();
        if ($hasUserConfirmed) {
            return;
        }

        $hasGscData = DB::table('search_console_data')->where('website_id', $website->id)->exists();
        if (! $hasGscData) {
            return;
        }

        $this->websiteId = $website->id;
        $this->visible = true;

        $detected = $service->classify($website);
        if ($detected->isEmpty()) {
            $this->visible = false;

            return;
        }

        $names = Niche::query()
            ->whereIn('id', $detected->pluck('niche_id'))
            ->pluck('name', 'id');

        $this->assignments = $detected->map(fn ($row) => [
            'niche_id' => $row['niche_id'],
            'name' => (string) ($names[$row['niche_id']] ?? 'Niche #'.$row['niche_id']),
            'weight' => $row['weight'],
            'is_primary' => $row['is_primary'],
            'keep' => true,
        ])->all();
    }

    public function save(): void
    {
        if ($this->websiteId === null) {
            return;
        }

        $kept = array_values(array_filter($this->assignments, fn ($a) => ! empty($a['keep'])));
        if ($kept === []) {
            $this->status = 'Pick at least one niche.';

            return;
        }

        $totalWeight = array_sum(array_column($kept, 'weight'));
        if ($totalWeight <= 0) {
            $totalWeight = count($kept);
            foreach ($kept as &$row) {
                $row['weight'] = 1 / count($kept);
            }
            unset($row);
        } else {
            foreach ($kept as &$row) {
                $row['weight'] = round((float) $row['weight'] / $totalWeight, 4);
            }
            unset($row);
        }

        usort($kept, fn ($a, $b) => $b['weight'] <=> $a['weight']);

        $now = Carbon::now();
        $payload = [];
        foreach ($kept as $i => $row) {
            $payload[(int) $row['niche_id']] = [
                'weight' => (float) $row['weight'],
                'is_primary' => $i === 0,
                'source' => 'hybrid',
                'confidence' => (float) $row['weight'],
                'last_classified_at' => $now,
            ];
        }

        Website::query()->whereKey($this->websiteId)->first()?->niches()->sync($payload);

        $this->visible = false;
        $this->status = 'Saved.';
    }

    public function render()
    {
        return view('livewire.onboarding.confirm-niches');
    }
}

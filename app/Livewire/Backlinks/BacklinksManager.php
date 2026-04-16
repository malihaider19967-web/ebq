<?php

namespace App\Livewire\Backlinks;

use App\Enums\BacklinkType;
use App\Models\Backlink;
use App\Services\BacklinkAuditService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Validation\Rule;

class BacklinksManager extends Component
{
    use WithPagination;

    public int $websiteId = 0;

    public ?string $tracked_date = null;

    public string $referring_page_url = '';

    public string $target_page_url = '';

    public ?int $domain_authority = null;

    public ?int $spam_score = null;

    public string $anchor_text = '';

    public string $type = '';

    public bool $is_dofollow = true;

    public bool $sheetOpen = false;

    public ?string $sheetDate = null;

    /** @var list<array{id: ?int, referring_page_url: string, target_page_url: string, domain_authority: ?int, spam_score: ?int, anchor_text: string, type: string, is_dofollow: bool}> */
    public array $sheetRows = [];

    /** @var list<int> */
    public array $sheetLoadedIds = [];

    public string $search = '';

    public ?string $from = null;

    public ?string $to = null;

    public ?string $typeFilter = null;

    public ?string $followFilter = null;

    public ?string $daMin = null;

    public ?string $daMax = null;

    public ?string $spamMin = null;

    public ?string $spamMax = null;

    public string $sortBy = 'tracked_date';

    public string $sortDir = 'desc';

    public ?int $expandedAuditId = null;

    /** @var list<int> */
    public array $auditingIds = [];

    public ?string $auditFilter = null;

    public function mount(): void
    {
        $this->websiteId = (int) session('current_website_id', 0);
        $this->tracked_date = now()->toDateString();
        $this->sheetDate = now()->toDateString();
        $this->type = BacklinkType::Other->value;
    }

    #[On('website-changed')]
    public function switchWebsite(int $websiteId): void
    {
        $this->websiteId = $websiteId;
        $this->resetPage();
        $this->sheetOpen = false;
        $this->sheetRows = [];
        $this->sheetLoadedIds = [];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'from', 'to', 'typeFilter', 'followFilter', 'daMin', 'daMax', 'spamMin', 'spamMax', 'auditFilter'], true)) {
            $this->resetPage();
        }
    }

    public function updatedSheetDate(): void
    {
        if ($this->sheetOpen && $this->userCanAccessWebsite()) {
            $this->loadSheetRows();
        }
    }

    public function sort(string $column): void
    {
        $allowed = [
            'tracked_date',
            'referring_page_url',
            'target_page_url',
            'domain_authority',
            'spam_score',
            'anchor_text',
            'type',
            'is_dofollow',
            'created_at',
        ];

        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = $column === 'tracked_date' ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    public function addBacklink(): void
    {
        if (! $this->userCanAccessWebsite()) {
            return;
        }

        $this->validate([
            'tracked_date' => ['required', 'date'],
            'referring_page_url' => ['required', 'string', 'max:5000', 'url'],
            'target_page_url' => ['required', 'string', 'max:5000', 'url'],
            'domain_authority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'spam_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'anchor_text' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::enum(BacklinkType::class)],
            'is_dofollow' => ['boolean'],
        ]);

        Backlink::query()->create([
            'website_id' => $this->websiteId,
            'tracked_date' => $this->tracked_date,
            'referring_page_url' => $this->referring_page_url,
            'target_page_url' => $this->target_page_url,
            'domain_authority' => $this->domain_authority,
            'spam_score' => $this->spam_score,
            'anchor_text' => $this->anchor_text !== '' ? $this->anchor_text : null,
            'type' => BacklinkType::from($this->type),
            'is_dofollow' => $this->is_dofollow,
        ]);

        $this->reset(['referring_page_url', 'target_page_url', 'domain_authority', 'spam_score', 'anchor_text']);
        $this->resetPage();
    }

    public function openSheet(): void
    {
        if (! $this->userCanAccessWebsite()) {
            return;
        }

        $this->validate(['sheetDate' => ['required', 'date']]);
        $this->loadSheetRows();
        $this->sheetOpen = true;
    }

    public function closeSheet(): void
    {
        $this->sheetOpen = false;
    }

    public function openSheetForDate(string $date): void
    {
        $this->sheetDate = $date;
        if (! $this->userCanAccessWebsite()) {
            return;
        }
        $this->loadSheetRows();
        $this->sheetOpen = true;
    }

    public function addSheetRow(): void
    {
        $this->sheetRows[] = $this->emptySheetRow();
    }

    public function removeSheetRow(int $index): void
    {
        unset($this->sheetRows[$index]);
        $this->sheetRows = array_values($this->sheetRows);
        if ($this->sheetRows === []) {
            $this->sheetRows[] = $this->emptySheetRow();
        }
    }

    public function saveSheet(): void
    {
        if (! $this->userCanAccessWebsite()) {
            return;
        }

        $this->validate(['sheetDate' => ['required', 'date']]);

        $rules = [];
        foreach ($this->sheetRows as $i => $row) {
            if ($this->isSheetRowBlank($row)) {
                continue;
            }
            $prefix = "sheetRows.$i";
            $rules["{$prefix}.referring_page_url"] = ['required', 'string', 'max:5000', 'url'];
            $rules["{$prefix}.target_page_url"] = ['required', 'string', 'max:5000', 'url'];
            $rules["{$prefix}.domain_authority"] = ['nullable', 'integer', 'min:0', 'max:100'];
            $rules["{$prefix}.spam_score"] = ['nullable', 'integer', 'min:0', 'max:100'];
            $rules["{$prefix}.anchor_text"] = ['nullable', 'string', 'max:500'];
            $rules["{$prefix}.type"] = ['required', Rule::enum(BacklinkType::class)];
            $rules["{$prefix}.is_dofollow"] = ['boolean'];
        }

        if ($rules === []) {
            DB::transaction(function (): void {
                if ($this->sheetLoadedIds !== []) {
                    Backlink::query()
                        ->where('website_id', $this->websiteId)
                        ->whereDate('tracked_date', $this->sheetDate)
                        ->whereIn('id', $this->sheetLoadedIds)
                        ->delete();
                }
            });
            $this->loadSheetRows();
            $this->resetPage();

            return;
        }

        $this->validate($rules);

        $keptIds = [];
        foreach ($this->sheetRows as $row) {
            if ($this->isSheetRowBlank($row)) {
                continue;
            }
            if (! empty($row['id'])) {
                $keptIds[] = (int) $row['id'];
            }
        }

        $toDelete = array_values(array_diff($this->sheetLoadedIds, $keptIds));

        DB::transaction(function () use ($toDelete): void {
            if ($toDelete !== []) {
                Backlink::query()
                    ->where('website_id', $this->websiteId)
                    ->whereDate('tracked_date', $this->sheetDate)
                    ->whereIn('id', $toDelete)
                    ->delete();
            }

            foreach ($this->sheetRows as $row) {
                if ($this->isSheetRowBlank($row)) {
                    continue;
                }

                $da = $row['domain_authority'] ?? null;
                $spam = $row['spam_score'] ?? null;
                $payload = [
                    'website_id' => $this->websiteId,
                    'tracked_date' => $this->sheetDate,
                    'referring_page_url' => $row['referring_page_url'],
                    'target_page_url' => $row['target_page_url'],
                    'domain_authority' => $da === null || $da === '' ? null : (int) $da,
                    'spam_score' => $spam === null || $spam === '' ? null : (int) $spam,
                    'anchor_text' => ($row['anchor_text'] ?? '') !== '' ? $row['anchor_text'] : null,
                    'type' => BacklinkType::from($row['type']),
                    'is_dofollow' => filter_var($row['is_dofollow'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ];

                if (! empty($row['id'])) {
                    $id = (int) $row['id'];
                    if (! in_array($id, $this->sheetLoadedIds, true)) {
                        continue;
                    }
                    Backlink::query()
                        ->where('website_id', $this->websiteId)
                        ->whereKey($id)
                        ->whereDate('tracked_date', $this->sheetDate)
                        ->update([
                            'referring_page_url' => $payload['referring_page_url'],
                            'target_page_url' => $payload['target_page_url'],
                            'domain_authority' => $payload['domain_authority'],
                            'spam_score' => $payload['spam_score'],
                            'anchor_text' => $payload['anchor_text'],
                            'type' => $payload['type'],
                            'is_dofollow' => $payload['is_dofollow'],
                        ]);
                } else {
                    Backlink::query()->create($payload);
                }
            }
        });

        $this->loadSheetRows();
        $this->resetPage();
    }

    public function auditBacklink(int $id, BacklinkAuditService $auditor): void
    {
        if (! $this->userCanAccessWebsite()) {
            return;
        }

        $backlink = Backlink::query()
            ->where('website_id', $this->websiteId)
            ->whereKey($id)
            ->first();

        if ($backlink === null) {
            return;
        }

        $this->auditingIds = array_values(array_unique([...$this->auditingIds, $id]));

        try {
            $auditor->audit($backlink);
        } finally {
            $this->auditingIds = array_values(array_diff($this->auditingIds, [$id]));
        }

        $this->expandedAuditId = $id;
    }

    public function auditAllOnPage(BacklinkAuditService $auditor): void
    {
        if (! $this->userCanAccessWebsite()) {
            return;
        }

        $ids = $this->visibleBacklinkIds();
        foreach ($ids as $id) {
            $backlink = Backlink::query()
                ->where('website_id', $this->websiteId)
                ->whereKey($id)
                ->first();
            if ($backlink === null) {
                continue;
            }
            $auditor->audit($backlink);
        }
    }

    public function toggleAuditDetails(int $id): void
    {
        $this->expandedAuditId = $this->expandedAuditId === $id ? null : $id;
    }

    public function deleteBacklink(int $id): void
    {
        if (! $this->userCanAccessWebsite()) {
            return;
        }

        Backlink::query()
            ->where('website_id', $this->websiteId)
            ->whereKey($id)
            ->delete();

        $this->resetPage();
    }

    public function render()
    {
        $rows = new LengthAwarePaginator([], 0, 25, 1, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);

        if ($this->websiteId && $this->userCanAccessWebsite()) {
            $sortBy = $this->sortBy;
            $sortDir = $this->sortDir === 'asc' ? 'asc' : 'desc';

            $rows = $this->filteredQuery()
                ->orderBy($sortBy, $sortDir)
                ->paginate(25);
        }

        return view('livewire.backlinks.backlinks-manager', [
            'rows' => $rows,
            'types' => BacklinkType::cases(),
            'canAccessWebsite' => $this->userCanAccessWebsite(),
        ]);
    }

    private function filteredQuery()
    {
        return Backlink::query()
            ->where('website_id', $this->websiteId)
            ->when($this->search !== '', function ($q): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->search).'%';
                $q->where(function ($q2) use ($term): void {
                    $q2->where('referring_page_url', 'like', $term)
                        ->orWhere('target_page_url', 'like', $term)
                        ->orWhere('anchor_text', 'like', $term);
                });
            })
            ->when($this->from, fn ($q) => $q->whereDate('tracked_date', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('tracked_date', '<=', $this->to))
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->followFilter === 'dofollow', fn ($q) => $q->where('is_dofollow', true))
            ->when($this->followFilter === 'nofollow', fn ($q) => $q->where('is_dofollow', false))
            ->when($this->daMin !== null && $this->daMin !== '', fn ($q) => $q->where('domain_authority', '>=', (int) $this->daMin))
            ->when($this->daMax !== null && $this->daMax !== '', fn ($q) => $q->where('domain_authority', '<=', (int) $this->daMax))
            ->when($this->spamMin !== null && $this->spamMin !== '', fn ($q) => $q->where('spam_score', '>=', (int) $this->spamMin))
            ->when($this->spamMax !== null && $this->spamMax !== '', fn ($q) => $q->where('spam_score', '<=', (int) $this->spamMax))
            ->when($this->auditFilter === 'unaudited', fn ($q) => $q->whereNull('audit_status'))
            ->when($this->auditFilter !== null && $this->auditFilter !== '' && $this->auditFilter !== 'unaudited', fn ($q) => $q->where('audit_status', $this->auditFilter));
    }

    /**
     * @return list<int>
     */
    private function visibleBacklinkIds(): array
    {
        $sortDir = $this->sortDir === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) request()->query('page', 1));
        $perPage = 25;

        return $this->filteredQuery()
            ->orderBy($this->sortBy, $sortDir)
            ->forPage($page, $perPage)
            ->pluck('id')
            ->all();
    }

    private function userCanAccessWebsite(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->canViewWebsiteId($this->websiteId);
    }

    private function loadSheetRows(): void
    {
        $rows = Backlink::query()
            ->where('website_id', $this->websiteId)
            ->whereDate('tracked_date', $this->sheetDate)
            ->orderBy('id')
            ->get();

        $this->sheetLoadedIds = $rows->pluck('id')->all();
        $this->sheetRows = $rows->map(fn (Backlink $b) => $this->rowToArray($b))->all();

        if ($this->sheetRows === []) {
            $this->sheetRows[] = $this->emptySheetRow();
        }
    }

    /**
     * @return array{id: ?int, referring_page_url: string, target_page_url: string, domain_authority: ?int, spam_score: ?int, anchor_text: string, type: string, is_dofollow: bool}
     */
    private function rowToArray(Backlink $b): array
    {
        return [
            'id' => $b->id,
            'referring_page_url' => $b->referring_page_url,
            'target_page_url' => $b->target_page_url,
            'domain_authority' => $b->domain_authority,
            'spam_score' => $b->spam_score,
            'anchor_text' => $b->anchor_text ?? '',
            'type' => $b->type->value,
            'is_dofollow' => $b->is_dofollow,
        ];
    }

    /**
     * @return array{id: ?int, referring_page_url: string, target_page_url: string, domain_authority: ?int, spam_score: ?int, anchor_text: string, type: string, is_dofollow: bool}
     */
    private function emptySheetRow(): array
    {
        return [
            'id' => null,
            'referring_page_url' => '',
            'target_page_url' => '',
            'domain_authority' => null,
            'spam_score' => null,
            'anchor_text' => '',
            'type' => BacklinkType::Other->value,
            'is_dofollow' => true,
        ];
    }

    /**
     * @param  array{id?: ?int, referring_page_url?: string, target_page_url?: string, domain_authority?: ?int, spam_score?: ?int, anchor_text?: string, type?: string, is_dofollow?: bool}  $row
     */
    private function isSheetRowBlank(array $row): bool
    {
        $ref = trim((string) ($row['referring_page_url'] ?? ''));
        $tgt = trim((string) ($row['target_page_url'] ?? ''));

        return $ref === '' && $tgt === '';
    }
}

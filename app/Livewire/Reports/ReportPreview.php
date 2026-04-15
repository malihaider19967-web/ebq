<?php

namespace App\Livewire\Reports;

use App\Models\Website;
use Livewire\Component;

class ReportPreview extends Component
{
    public array $report = [];

    public int $websiteId = 0;

    public string $reportType = 'weekly';

    public string $startDate = '';

    public string $endDate = '';

    public function render()
    {
        $website = Website::find($this->websiteId);

        return view('livewire.reports.report-preview', [
            'website' => $website,
        ]);
    }
}

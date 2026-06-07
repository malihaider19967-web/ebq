<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', '')); // '', 'converted', 'pending'
        $search = trim((string) $request->query('q', ''));

        $leads = Lead::query()
            ->with(['user:id,name,email', 'guestPageAudit:id,token,url,keyword'])
            ->when($status === 'converted', fn ($q) => $q->converted())
            ->when($status === 'pending', fn ($q) => $q->pending())
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('email', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.leads.index', [
            'leads' => $leads,
            'filters' => compact('status', 'search'),
            'totalCount' => Lead::query()->count(),
            'convertedCount' => Lead::query()->converted()->count(),
        ]);
    }
}

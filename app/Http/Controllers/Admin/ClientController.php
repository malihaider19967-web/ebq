<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ClientActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $clients = User::query()
            ->when($q !== '', fn ($query) => $query
                ->where('name', 'like', '%'.$q.'%')
                ->orWhere('email', 'like', '%'.$q.'%'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.clients.index', compact('clients', 'q'));
    }

    public function store(Request $request, ClientActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        $client = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_admin' => (bool) ($data['is_admin'] ?? false),
            'is_disabled' => false,
        ]);

        $logger->log('admin.client_created', userId: $client->id, meta: ['email' => $client->email]);

        return back()->with('status', 'Client created.');
    }

    public function update(Request $request, User $user, ClientActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'is_admin' => ['nullable', 'boolean'],
            'is_disabled' => ['nullable', 'boolean'],
        ]);

        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_admin' => (bool) ($data['is_admin'] ?? false),
            'is_disabled' => (bool) ($data['is_disabled'] ?? false),
        ])->save();

        $logger->log('admin.client_updated', userId: $user->id, meta: [
            'is_admin' => $user->is_admin,
            'is_disabled' => $user->is_disabled,
        ]);

        return back()->with('status', 'Client updated.');
    }
}

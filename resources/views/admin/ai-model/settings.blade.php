<x-layouts.app>
    <div class="mx-auto max-w-2xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">AI model</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Picks the default Mistral chat model for every AI feature
                (brief, writer, strategy tools, custom-prompt classifier).
                Per-call overrides still take precedence when a service
                pins itself to a specific model.
            </p>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="POST" action="{{ route('admin.ai-model.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="space-y-5">
                    <div>
                        <label for="model" class="block text-sm font-semibold text-slate-900 dark:text-slate-100">
                            Default model
                        </label>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Currently active: <code class="rounded bg-slate-100 px-1.5 py-px font-mono text-[11px] dark:bg-slate-800 dark:text-slate-200">{{ $currentModel }}</code>
                        </p>
                        <select id="model" name="model"
                            class="mt-2 block w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                            @foreach ($models as $option)
                                <option value="{{ $option['id'] }}" @selected(old('model', $currentModel) === $option['id'])>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            List pulled live from the Mistral
                            <code class="font-mono">/v1/models</code> endpoint and cached
                            for an hour. Use <em>Refresh list</em> below to force a re-fetch
                            after the provider adds a new model.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit"
                        class="inline-flex h-9 items-center rounded-md bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                        Save model
                    </button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.ai-model.settings.refresh') }}" class="mt-4 flex justify-end border-t border-slate-200 pt-4 dark:border-slate-800">
                @csrf
                <button type="submit"
                    class="inline-flex h-8 items-center rounded-md border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    Refresh list from Mistral
                </button>
            </form>
        </div>

        <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
            Tip: <code class="font-mono">mistral-small-latest</code> is the cheapest
            with native JSON-mode and works well for every existing prompt.
            <code class="font-mono">mistral-large-latest</code> produces noticeably
            richer prose but costs ~10x more per token.
        </p>
    </div>
</x-layouts.app>

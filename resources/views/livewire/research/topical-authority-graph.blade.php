<div>
    <div class="mb-4 max-w-md">
        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Niche</label>
        <select wire:model.live="nicheId" class="h-9 w-full rounded-md border border-slate-200 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <option value="">Select…</option>
            @foreach ($niches as $niche)
                <option value="{{ $niche->id }}" @selected($nicheId === $niche->id)>{{ $niche->name }}</option>
            @endforeach
        </select>
    </div>

    @if ($nicheId === null)
        <p class="text-xs text-slate-400">Pick a niche to see its topic-cluster tree and your coverage.</p>
    @else
        @php
            $selectedNiche = $niches->firstWhere('id', $nicheId);
            $treeData = [
                'name' => $selectedNiche?->name ?? 'Niche',
                'children' => $rows->map(function ($row) use ($coveredKeywordIds) {
                    $clusterKeywordIds = $row->cluster?->keywords->pluck('id') ?? collect();
                    $covered = $clusterKeywordIds->intersect($coveredKeywordIds)->count();
                    $total = $clusterKeywordIds->count();
                    return [
                        'name' => $row->topic_name,
                        'priority' => (float) ($row->priority_score ?? 0),
                        'covered' => $covered,
                        'total' => $total,
                        'coverage' => $total > 0 ? $covered / $total : 0,
                    ];
                })->values()->all(),
            ];
        @endphp

        <div wire:ignore class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
            <div id="topical-authority-graph"
                 class="min-h-[420px] w-full"
                 data-tree="{{ json_encode($treeData, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG) }}">
            </div>

            @if ($rows->isEmpty())
                <p class="mt-3 text-xs text-slate-400">No topic clusters yet for this niche. Run <code>ebq:research-cluster-refresh</code> + <code>ebq:niche-aggregates-recompute</code>.</p>
            @endif
        </div>

        @if (! app()->environment('testing'))
            <script src="https://d3js.org/d3.v7.min.js"></script>
        @endif
        <script>
            (function () {
                function render() {
                    var host = document.getElementById('topical-authority-graph');
                    if (!host || typeof d3 === 'undefined') return;

                    var raw = host.getAttribute('data-tree');
                    if (!raw) return;
                    var tree;
                    try { tree = JSON.parse(raw); } catch (e) { return; }
                    if (!tree || !Array.isArray(tree.children) || tree.children.length === 0) {
                        host.innerHTML = '<p class="text-xs text-slate-400">No clusters to chart yet.</p>';
                        return;
                    }

                    host.innerHTML = '';
                    var width = host.clientWidth || 600;
                    var height = Math.max(420, 80 + tree.children.length * 32);

                    var svg = d3.select(host).append('svg')
                        .attr('viewBox', [0, 0, width, height])
                        .attr('width', '100%')
                        .attr('height', height)
                        .style('font', '11px Inter,sans-serif');

                    var root = d3.hierarchy(tree);
                    var layout = d3.tree().size([height - 40, width - 240]);
                    layout(root);

                    var g = svg.append('g').attr('transform', 'translate(120, 20)');

                    g.selectAll('path.link')
                        .data(root.links())
                        .enter().append('path')
                        .attr('class', 'link')
                        .attr('fill', 'none')
                        .attr('stroke', '#cbd5e1')
                        .attr('stroke-width', 1)
                        .attr('d', d3.linkHorizontal().x(function (d) { return d.y; }).y(function (d) { return d.x; }));

                    var node = g.selectAll('g.node')
                        .data(root.descendants())
                        .enter().append('g')
                        .attr('class', 'node')
                        .attr('transform', function (d) { return 'translate(' + d.y + ',' + d.x + ')'; });

                    node.append('circle')
                        .attr('r', function (d) { return d.depth === 0 ? 6 : 4 + Math.min(8, Math.log(1 + (d.data.priority || 1)) * 2); })
                        .attr('fill', function (d) {
                            if (d.depth === 0) return '#6366f1';
                            var c = d.data.coverage || 0;
                            if (c >= 0.66) return '#10b981';
                            if (c >= 0.33) return '#f59e0b';
                            return '#ef4444';
                        });

                    node.append('text')
                        .attr('dx', function (d) { return d.depth === 0 ? -10 : 10; })
                        .attr('dy', 3)
                        .attr('text-anchor', function (d) { return d.depth === 0 ? 'end' : 'start'; })
                        .attr('fill', '#475569')
                        .text(function (d) {
                            if (d.depth === 0) return d.data.name;
                            return d.data.name + ' (' + (d.data.covered || 0) + '/' + (d.data.total || 0) + ')';
                        });
                }

                if (typeof d3 !== 'undefined') {
                    render();
                } else {
                    var attempts = 0;
                    var poll = setInterval(function () {
                        attempts++;
                        if (typeof d3 !== 'undefined') {
                            clearInterval(poll);
                            render();
                        } else if (attempts > 40) {
                            clearInterval(poll);
                        }
                    }, 100);
                }

                document.addEventListener('livewire:navigated', render);
            })();
        </script>
    @endif
</div>

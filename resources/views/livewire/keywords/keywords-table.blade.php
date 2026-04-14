<div>
    <input wire:model.live="search" class="mb-3 w-full rounded border p-2" placeholder="Search query..." />
    <table class="w-full text-sm">
        <thead>
            <tr>
                <th class="text-left">Query</th>
                <th>Clicks</th>
                <th>Impressions</th>
                <th>CTR</th>
                <th>Position</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->query }}</td>
                    <td>{{ $row->clicks }}</td>
                    <td>{{ $row->impressions }}</td>
                    <td>{{ $row->ctr }}</td>
                    <td>{{ $row->position }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    {{ $rows->links() }}
</div>

@php
    $workspaceMemberships = auth()->user()?->activeWorkspaceMemberships()
        ->with('workspace')
        ->get() ?? collect();
    $activeWorkspaceId = (int) session('active_workspace_id');
@endphp

@if ($workspaceMemberships->count() > 1)
    <form method="POST" action="{{ route('workspace.switch') }}" class="flex items-center">
        @csrf
        <label for="active-workspace" class="sr-only">Espacio de trabajo</label>
        <select
            id="active-workspace"
            name="workspace_id"
            onchange="this.form.submit()"
            class="fi-select-input block w-full rounded-lg border-none bg-white/5 py-2 pl-3 pr-8 text-sm text-gray-950 ring-1 ring-gray-950/10 transition duration-75 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
        >
            @foreach ($workspaceMemberships as $membership)
                <option value="{{ $membership->workspace_id }}" @selected($activeWorkspaceId === (int) $membership->workspace_id)>
                    {{ $membership->workspace?->name ?? 'Espacio de trabajo' }}
                </option>
            @endforeach
        </select>
    </form>
@endif

<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceAuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceSwitchController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'integer'],
        ]);

        $membership = $request->user()
            ->activeWorkspaceMemberships()
            ->with('workspace')
            ->where('workspace_id', $data['workspace_id'])
            ->firstOrFail();

        $request->session()->put('active_workspace_id', $membership->workspace_id);

        WorkspaceAuditLog::record(
            $membership->workspace,
            'workspace.context.switched',
            $request->user(),
            $membership,
        );

        return redirect('/admin');
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\Workspaces\AcceptWorkspaceInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceInvitationController extends Controller
{
    public function accept(
        Request $request,
        string $token,
        AcceptWorkspaceInvitation $acceptInvitation,
    ): RedirectResponse {
        if (! $request->user()) {
            $request->session()->put('pending_workspace_invitation_token', $token);

            return redirect('/admin/login');
        }

        $membership = $acceptInvitation->accept($request->user(), $token);

        $request->session()->put('active_workspace_id', $membership->workspace_id);

        return redirect('/admin')->with('status', 'Te has unido al espacio de trabajo.');
    }
}

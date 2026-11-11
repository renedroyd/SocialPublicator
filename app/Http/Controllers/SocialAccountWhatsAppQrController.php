<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SocialAccountWhatsAppQrController extends Controller
{
    public function show(Request $request, int $socialAccount)
    {
        $socialAccount = SocialAccount::query()->findOrFail($socialAccount);
        $this->authorizeAccount($request, $socialAccount);
        abort_unless($socialAccount->platform === SocialAccount::PLATFORM_WHATSAPP, 404);

        return view('social-accounts.whatsapp-qr', [
            'account' => $socialAccount,
            'qrDataUrl' => route('social-accounts.whatsapp.qr.data', $socialAccount),
        ]);
    }

    public function data(Request $request, int $socialAccount): JsonResponse
    {
        $socialAccount = SocialAccount::query()->findOrFail($socialAccount);
        $this->authorizeAccount($request, $socialAccount);
        abort_unless($socialAccount->platform === SocialAccount::PLATFORM_WHATSAPP, 404);

        $fresh = $socialAccount->fresh();

        return response()->json([
            'status' => $fresh->status,
            'qr' => Cache::get($this->qrCacheKey($socialAccount)),
            'display_name' => $fresh->display_name,
            'last_error_message' => $fresh->last_error_message,
        ]);
    }

    private function authorizeAccount(Request $request, SocialAccount $account): void
    {
        $membership = $request->user()->activeWorkspaceMemberships()
            ->where('workspace_id', $account->workspace_id)
            ->first();

        abort_unless($membership && $membership->canUseSocialAccount($account, 'manage'), 403);
    }

    private function qrCacheKey(SocialAccount $account): string
    {
        return 'social-account-whatsapp-qr:'.$account->workspace_id.':'.$account->id;
    }
}

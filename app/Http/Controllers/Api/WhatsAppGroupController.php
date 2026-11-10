<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppGroup;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WhatsAppGroupController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $accountId = $request->input('social_account_id');
        $account = $accountId !== null ? SocialAccount::query()->findOrFail($accountId) : null;

        if ($account !== null) {
            abort_unless($account->platform === SocialAccount::PLATFORM_WHATSAPP && $account->account_type === SocialAccount::TYPE_WHATSAPP, 422);
        }

        $groups = collect($request->input('groups', []))
            ->map(static fn (array $group): array => [
                'whatsapp_id' => $group['whatsapp_id'] ?? $group['id'] ?? null,
                'name' => $group['name'] ?? null,
            ])
            ->values()
            ->all();

        $data = Validator::make(
            ['groups' => $groups],
            [
                'groups' => ['required', 'array'],
                'groups.*.whatsapp_id' => ['required', 'string', 'max:255'],
                'groups.*.name' => ['required', 'string', 'max:255'],
            ]
        )->validate();

        $synchronizedAt = now();
        $receivedIds = [];

        DB::transaction(function () use ($data, $synchronizedAt, $account, &$receivedIds): void {
            foreach ($data['groups'] as $group) {
                $name = trim(preg_replace('/\s+/u', ' ', $group['name']) ?? $group['name']);

                if ($name === '' || ! str_contains($group['whatsapp_id'], '@g.us')) {
                    continue;
                }

                $record = WhatsAppGroup::updateOrCreate(
                    [
                        'social_account_id' => $account?->id,
                        'whatsapp_id' => $group['whatsapp_id'],
                    ],
                    [
                        'name' => $name,
                        'active' => true,
                        'last_synchronized_at' => $synchronizedAt,
                    ],
                );

                $receivedIds[] = $record->id;
            }
        });

        return response()->json([
            'success' => true,
            'total' => count(array_unique($receivedIds)),
            'data' => WhatsAppGroup::query()
                ->whereIn('id', $receivedIds)
                ->orderBy('name')
                ->get(['id', 'whatsapp_id', 'name', 'active', 'last_synchronized_at']),
        ]);
    }
}

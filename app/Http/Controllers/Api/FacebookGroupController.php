<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FacebookGroup;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FacebookGroupController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $rawGroups = $request->input('groups');

        if (! is_array($rawGroups)) {
            $rawGroups = $request->input('grupos', []);
        }

        $accountId = $request->input('social_account_id');
        $account = $accountId !== null ? SocialAccount::query()->findOrFail($accountId) : null;

        if ($account !== null) {
            abort_unless(
                $account->platform === SocialAccount::PLATFORM_FACEBOOK
                    && $account->account_type === SocialAccount::TYPE_FACEBOOK_PROFILE,
                422,
            );
        }

        $groups = collect($rawGroups)
            ->map(static function (array $group): array {
                return [
                    'facebook_id' => $group['facebook_id'] ?? null,
                    'name' => $group['name'] ?? $group['nombre'] ?? null,
                    'url' => $group['url'] ?? null,
                ];
            })
            ->values()
            ->all();

        $data = Validator::make(
            ['groups' => $groups],
            [
                'groups' => ['required', 'array'],
                'groups.*.facebook_id' => ['required', 'string', 'max:255'],
                'groups.*.name' => ['required', 'string', 'max:255'],
                'groups.*.url' => ['nullable', 'string', 'max:2048'],
            ]
        )->validate();

        $synchronizedAt = now();
        $receivedIds = [];
        $ignoredGenericNames = 0;

        DB::transaction(function () use ($data, $synchronizedAt, $account, &$receivedIds, &$ignoredGenericNames): void {
            foreach ($data['groups'] as $group) {
                $existingQuery = FacebookGroup::query()
                    ->where('facebook_id', $group['facebook_id']);

                if ($account !== null) {
                    $existingQuery->where(function ($query) use ($account): void {
                        $query->where('social_account_id', $account->id)
                            ->orWhereNull('social_account_id');
                    });
                } else {
                    $existingQuery->whereNull('social_account_id');
                }

                $existing = $existingQuery->first();

                if ($this->isGenericGroupName($group['name'])) {
                    if (! $existing) {
                        $ignoredGenericNames++;

                        continue;
                    }

                    $existing->forceFill([
                        'social_account_id' => $account?->id ?? $existing->social_account_id,
                        'url' => $group['url'] ?: $existing->url,
                        'active' => true,
                        'last_synchronized_at' => $synchronizedAt,
                    ])->save();

                    $receivedIds[] = $existing->id;
                    $ignoredGenericNames++;

                    continue;
                }

                $record = $existing ?? new FacebookGroup;
                $record->fill([
                    'social_account_id' => $account?->id,
                    'facebook_id' => $group['facebook_id'],
                    'name' => $group['name'],
                    'url' => $group['url'],
                    'active' => true,
                    'last_synchronized_at' => $synchronizedAt,
                ]);
                $record->save();

                $receivedIds[] = $record->id;
            }
        });

        return response()->json([
            'success' => true,
            'total' => count(array_unique($receivedIds)),
            'ignored_generic_names' => $ignoredGenericNames,
            'data' => FacebookGroup::query()
                ->whereIn('id', $receivedIds)
                ->orderBy('name')
                ->get([
                    'id',
                    'facebook_id',
                    'name',
                    'url',
                    'active',
                    'last_synchronized_at',
                ]),
        ]);
    }

    private function isGenericGroupName(string $name): bool
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);

        return preg_match('/^(?:grupo\s+facebook|facebook\s+group)(?:\s+\d+)?$/iu', $normalized) === 1;
    }
}

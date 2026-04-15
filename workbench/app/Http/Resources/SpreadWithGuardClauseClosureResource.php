<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises both bugs simultaneously — the exact pattern from the original
 * ProcessProcessablesResource that triggered the issue:
 *
 * Bug 1: ...parent::toArray() spread (2 items in outer return) + a whenLoaded
 *         closure with more items (5), causing findBestArrayReturn() to pick
 *         the wrong return statement.
 *
 * Bug 2: The closure has a guard clause (`return null;`) before the data array,
 *         causing resolveClosureReturnExpression() to pick null instead of the
 *         data shape.
 *
 * @mixin Order
 */
class SpreadWithGuardClauseClosureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'customer' => $this->whenLoaded('user', function () {
                $user = $this->user;

                if (! $user) {
                    return null;
                }

                return [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'role' => $user->role,
                    'is_premium' => $user->is_premium,
                    'name_titled' => $user->nameTitled(),
                    'morph' => $user::morphValue(),
                ];
            }),
        ];
    }
}

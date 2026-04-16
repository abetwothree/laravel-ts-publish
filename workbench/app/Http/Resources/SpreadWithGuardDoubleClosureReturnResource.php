<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * @mixin Order
 */
class SpreadWithGuardDoubleClosureReturnResource extends JsonResource
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

                if ($user->is_premium) {
                    return [
                        'name' => $user->name,
                        'initials' => $user->initials,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'avatar' => $user->avatar,
                        'role' => $user->role,
                        'is_premium' => $user->is_premium,
                    ];
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

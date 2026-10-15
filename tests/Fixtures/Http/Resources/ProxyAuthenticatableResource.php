<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;

/**
 * A user resource that redeclares `$resource` as an interface its user implements.
 *
 * @mixin User
 */
final class ProxyAuthenticatableResource extends JsonResource
{
    /** @var Authenticatable */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'user' => $this->resource instanceof CanResetPassword ? $this->resource : null,
            'key' => $this->resource instanceof CanResetPassword ? $this->resource->getKey() : null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\EnumWraps\EnumWrapBox;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A test-only resource whose returns send the app's or the CRM's `Status` and `User`, or a value no rule types, under
 * one key each (GH-76): the untyped return keeps each key `unknown`.
 *
 * @property-read EnumWrapBox $resource
 */
final class UntypedReturnBesideSameNamedResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->resource->c) {
            return ['status' => $this->resource->status, 'user' => User::first()];
        }

        if ($this->resource->d) {
            return ['status' => json_decode((string) $request->input('x')), 'user' => json_decode((string) $request->input('y'))];
        }

        return ['status' => $this->resource->crmStatus, 'user' => CrmUser::first()];
    }
}

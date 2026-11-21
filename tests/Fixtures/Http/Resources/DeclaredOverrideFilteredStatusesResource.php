<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\User;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * A test-only resource whose `@return` fills a gap with `Person`, an override spelled `User`, beside a list
 * array_filter() keeps of the app's and the CRM's `Status` and a later app one; the resource re-indexes
 * the list (GH-76).
 *
 * @mixin User
 */
class DeclaredOverrideFilteredStatusesResource extends JsonResource
{
    /**
     * @return array{pair: array{list: list<CrmStatus|Status>, one: Status, a: User, b: Person}}
     */
    public function toArray(Request $request): array
    {
        return [
            'pair' => [
                'list' => array_filter([CrmStatus::Lead, Status::Draft]),
                'one' => Status::Draft,
                'a' => User::first(),
                'b' => json_decode((string) $request->getContent()),
            ],
        ];
    }
}

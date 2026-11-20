<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Requests;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Foundation\Http\FormRequest;

/** A form request whose cast and extends clause, each with an import, only build on a name. */
#[TsCasts(['owner' => ['type' => 'Paginated<UserResource>', 'import' => '@/types/paginated']])]
#[TsExtends('Pick<UserResource, "id">', '@/types/user')]
final class NestedNameCastRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['owner' => ['required', 'array']];
    }
}

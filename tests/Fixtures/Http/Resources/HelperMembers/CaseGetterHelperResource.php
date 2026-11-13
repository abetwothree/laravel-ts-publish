<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\HelperMembers;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\EnumCaseGetterModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;

/** Accessors whose getters return a plain enum case, and wraps over cases, in place and in bare `: array` helpers. */
final class CaseGetterHelperResource extends JsonResource
{
    /** @var EnumCaseGetterModel */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $model = $this->resource;

        return [
            'in_default' => ['v' => $this->default_state, 'n' => 1],
            'helper_default' => $this->withDefaultState(),
            'in_shape' => ['v' => $this->state_shape, 'n' => 1],
            'helper_shape' => $this->withStateShape(),
            'in_given' => ['v' => $model->default_state],
            'helper_given' => $this->withGivenState($this->resource),
            'in_wrapped' => ['s' => EnumResource::make($this->id ? Status::Draft : Status::Published)],
            'helper_wrapped' => $this->withWrappedCase(),
            'in_given_wrap' => ['s' => EnumResource::make($model->default_state)],
            'helper_given_wrap' => $this->withGivenWrap($this->resource),
        ];
    }

    /** The accessor returning one case, beside a number. */
    private function withDefaultState(): array
    {
        return ['v' => $this->default_state, 'n' => 1];
    }

    /** The accessor returning a shape that holds a case, beside a number. */
    private function withStateShape(): array
    {
        return ['v' => $this->state_shape, 'n' => 1];
    }

    /** The accessor read on a model a typed parameter holds. */
    private function withGivenState(EnumCaseGetterModel $model): array
    {
        return ['v' => $model->default_state];
    }

    /** An enum resource over either of two cases. */
    private function withWrappedCase(): array
    {
        return ['s' => EnumResource::make($this->id ? Status::Draft : Status::Published)];
    }

    /** An enum resource over the accessor read on a model a typed parameter holds. */
    private function withGivenWrap(EnumCaseGetterModel $model): array
    {
        return ['s' => EnumResource::make($model->default_state)];
    }
}

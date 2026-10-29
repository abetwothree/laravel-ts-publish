<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\Chart as ChartEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\UiTone;
use Workbench\App\Enums\Visibility;
use Workbench\App\Packages\Ui\Chart;

/**
 * Reads a plain enum's case beside an overridden enum's, a string and a pure enum's case, each through a helper that
 * declares no return type, so each arm types as what PHP sends with no import.
 */
class UiUntypedEnumArmsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'mixed' => $this->mixed(),
            'matched' => $this->matched(),
            'status' => $this->status(),
            'pure' => $this->pure(),
        ];
    }

    /**
     * A plain case or an overridden one, beside a composed class.
     */
    protected function mixed()
    {
        return ['kind' => $this->resource ? ChartEnum::Bar : UiTone::Bright, 'chart' => new Chart];
    }

    /**
     * A plain case or an overridden one, matched.
     */
    protected function matched()
    {
        return ['kind' => match (true) {
            $this->resource !== null => ChartEnum::Bar,
            default => UiTone::Bright,
        }];
    }

    /**
     * An int-backed case or a string.
     */
    protected function status()
    {
        return ['kind' => $this->resource ? Status::Published : 'none'];
    }

    /**
     * A pure enum's case, which json_encode() cannot write.
     */
    protected function pure()
    {
        return ['kind' => Visibility::Public, 'n' => 1];
    }
}

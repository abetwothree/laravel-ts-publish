<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fixture: a model-less resource whose `toArray()` reads untyped array defaults that TagBoardResource writes by
 * element.
 */
class BaseTagBoardResource extends JsonResource
{
    protected $labels = ['a' => 'x'];

    protected $both = ['a'];

    protected $dynamic = ['a' => 1];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'labels' => $this->labels,
            'both' => $this->both,
            'dynamic' => $this->dynamic,
        ];
    }
}

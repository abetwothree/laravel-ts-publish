<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\NameCarrier;
use Workbench\App\ValueObjects\SampleRow;
use Workbench\App\ValueObjects\SampleSheet;

/**
 * Joins where one branch writes a value no rule types, under a `@return` member typing both branches (GH-154).
 *
 * @property-read SampleSheet $resource
 */
class JoinReadingsResource extends JsonResource
{
    /**
     * @return array{
     *     params: list<array{t: string|list<int>}>,
     *     walks: list<array{k: list<int>|string}>,
     *     spreads: list<array{k: list<int>|string, id: int}>,
     *     relays: list<array{k: list<int>|string, id: int}>,
     *     receivers: list<array{name: string|int}>,
     *     named: list<array{name: string|int}>
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'params' => array_map(function (string $t) use ($request) {
                if ($request->has('x')) {
                    $t = cache('k');
                }

                return ['t' => $t];
            }, $this->resource->names),
            'walks' => array_map(function ($r) {
                $d = [];

                if ($r->ok) {
                    $d['k'] = cache('k');
                } else {
                    $d['k'] = (string) $r->n;
                }

                return $d;
            }, $this->resource->rows),
            'spreads' => array_map(fn ($r) => [...$this->split($r), 'id' => $r->id], $this->resource->rows),
            'relays' => array_map(fn ($r) => [...$this->relay($r), 'id' => $r->id], $this->resource->rows),
            'receivers' => array_map(function ($r) {
                $o = $r->item;

                if ($r->ok) {
                    $o = cache('k');
                }

                return ['name' => $o->name];
            }, $this->resource->rows),
            'named' => array_map(function ($r) {
                $o = $r->item;

                if ($r->ok) {
                    /** @var NameCarrier $o */
                    $o = cache('k');
                }

                return ['name' => $o->name];
            }, $this->resource->rows),
        ];
    }

    /**
     * A row's `k` from one of two returns: a value no rule types, or a string.
     */
    private function split(SampleRow $r): array
    {
        if ($r->ok) {
            return ['k' => cache('k')];
        }

        return ['k' => (string) $r->n];
    }

    /**
     * split() whose first branch returns a variable another helper fills.
     */
    private function relay(SampleRow $r): array
    {
        if ($r->ok) {
            $d = $this->untyped($r);

            return $d;
        }

        return ['k' => (string) $r->n];
    }

    /**
     * A `k` no rule types.
     */
    private function untyped(SampleRow $r): array
    {
        return ['k' => cache('k')];
    }
}

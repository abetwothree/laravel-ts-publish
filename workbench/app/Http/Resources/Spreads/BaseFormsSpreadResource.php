<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\MailRecipientResource;
use Workbench\App\ValueObjects\CreditLine;

/**
 * Walk bases over an earlier key, `??=` of a key the walk lacks, resource spreads in merges (GH-161 B2a, B2b, B3, B6).
 *
 * @property-read CreditLine $resource
 */
class BaseFormsSpreadResource extends JsonResource
{
    /**
     * @return array{coalesced_under_exact_return: array{id: int, by: string}}
     */
    public function toArray(Request $request): array
    {
        $coalesced = $this->resource->flag ? ['by' => $this->resource->title] : [];
        $coalesced['by'] ??= 'z';

        $tonedTernary = $this->resource->flag ? ['by' => $this->resource->title] : [];
        $tonedTernary['tone'] = 'warm';

        $tonedMatch = match ($this->resource->pick) {
            'x' => ['by' => $this->resource->title],
            default => [],
        };
        $tonedMatch['tone'] = 'warm';

        $tonedElvis = $this->titledWhenFlagged() ?: ['by' => 'z'];
        $tonedElvis['tone'] = 'warm';

        $credited = ['credited_title' => 1];

        return [
            'ternary_local_coalesced' => ['id' => 0, 'by' => $this->resource->author, ...$coalesced],
            'ternary_local_given_key' => ['id' => 0, 'by' => $this->resource->author, ...$tonedTernary],
            'match_local_given_key' => ['id' => 0, 'by' => $this->resource->author, ...$tonedMatch],
            'elvis_local_given_key' => ['id' => 0, 'by' => $this->resource->author, ...$tonedElvis],
            'elvis_base_helper' => ['id' => 0, 'by' => $this->resource->author, ...$this->elvisBased()],
            'plus_assigned_ternary_helper' => [
                'id' => 0,
                'by' => $this->resource->author,
                ...$this->plusAssignedTernary(),
            ],
            'ternary_base_in_if_helper' => ['id' => 0, 'by' => $this->resource->author, ...$this->ternaryBaseInIf()],
            'merged_ternary_helper' => ['id' => 0, 'by' => $this->resource->author, ...$this->mergedTernary()],
            'copied_ternary_helper' => ['id' => 0, 'by' => $this->resource->author, ...$this->copiedTernary()],
            'nested_coalesced_helper' => [
                'row' => ['id' => 0, 'by' => $this->resource->author, ...$this->coalescedTernary()],
            ],
            'static_coalesced_helper' => [
                'id' => 0,
                'by' => $this->resource->author,
                ...self::coalescedFor($this->resource->flag, $this->resource->title),
            ],
            'flag_then_coalesced_helper' => ['id' => 0, 'by' => $this->resource->flag, ...$this->coalescedTernary()],
            'coalesced_under_exact_return' => [
                'id' => 0,
                'by' => $this->resource->author,
                ...$this->coalescedTernary(),
            ],
            'documented_coalesced_helper' => [
                'id' => 0,
                'by' => $this->resource->author,
                ...$this->documentedCoalesced(),
            ],
            'ternary_only_helper' => ['id' => 0, 'by' => $this->resource->author, ...$this->ternaryOnly()],
            'match_base_helper' => ['id' => 0, 'by' => $this->resource->author, ...$this->matchBased()],
            'returned_ternary_helper' => ['id' => 0, 'by' => $this->resource->author, ...$this->returnedTernary()],
            'old_coalesce_idiom_helper' => ['id' => 0, 'by' => $this->resource->author, ...$this->oldCoalesceIdiom()],
            'plus_default_over_ternary' => [...$this->plusDefaulted()],
            'joined_local_base' => [...$this->joinedBase()],
            'returned_whole_ternary' => [...$this->wholeTernary()],
            'ternary_base_unheld_coalesce' => [
                'id' => 0,
                'by' => $this->resource->author,
                ...$this->ternaryUnheldCoalesced(),
            ],
            'literal_base_unheld_coalesce' => [
                'id' => 0,
                'by' => $this->resource->author,
                ...$this->literalUnheldCoalesced(),
            ],
            'helper_base_unheld_coalesce' => [
                'id' => 0,
                'by' => $this->resource->author,
                ...$this->helperUnheldCoalesced(),
            ],
            'unheld_coalesce' => [...$this->unheldCoalesced()],
            $this->merge([...$credited, ...CreditArmResource::make($this->resource)->resolve($request)]),
            $this->merge(['name' => 1, ...MailRecipientResource::make($this->resource->author)->resolve($request)]),
        ];
    }

    /**
     * A static helper's ternary base, defaulted by `??=`.
     */
    private static function coalescedFor(bool $flag, string $title): array
    {
        $d = $flag ? ['by' => $title] : [];
        $d['by'] ??= 'z';

        return $d;
    }

    /**
     * An if-built base that holds `by` only when the line is flagged.
     */
    private function titledWhenFlagged(): array
    {
        $o = [];

        if ($this->resource->flag) {
            $o['by'] = $this->resource->title;
        }

        return $o;
    }

    /**
     * A `?:` base over an own helper.
     */
    private function elvisBased(): array
    {
        $d = $this->titledWhenFlagged() ?: ['by' => 'z'];

        return $d;
    }

    /**
     * A `+=` of a ternary of literals.
     */
    private function plusAssignedTernary(): array
    {
        $d = ['n' => 1];
        $d += $this->resource->flag ? ['by' => $this->resource->title] : ['by' => 'z'];

        return $d;
    }

    /**
     * A ternary base written whole inside an `if`.
     */
    private function ternaryBaseInIf(): array
    {
        $d = ['n' => 1];

        if ($this->resource->pick !== 'q') {
            $d = $this->resource->flag ? ['by' => $this->resource->title] : ['by' => 'z'];
        }

        return $d;
    }

    /**
     * An `array_merge()` of a literal and a ternary.
     */
    private function mergedTernary(): array
    {
        $d = array_merge(['n' => 1], $this->resource->flag ? ['by' => $this->resource->title] : ['by' => 'z']);

        return $d;
    }

    /**
     * A copy of a ternary local, defaulted by `??=`.
     */
    private function copiedTernary(): array
    {
        $base = $this->resource->flag ? ['by' => $this->resource->title] : [];
        $d = $base;
        $d['by'] ??= 'z';

        return $d;
    }

    /**
     * A ternary base defaulted by `??=`: the title or `'z'`.
     */
    private function coalescedTernary(): array
    {
        $d = $this->resource->flag ? ['by' => $this->resource->title] : [];
        $d['by'] ??= 'z';

        return $d;
    }

    /**
     * The same, under its own `@return`.
     *
     * @return array{by: string}
     */
    private function documentedCoalesced(): array
    {
        $d = $this->resource->flag ? ['by' => $this->resource->title] : [];
        $d['by'] ??= 'z';

        return $d;
    }

    /**
     * A ternary-or-empty base with no default.
     */
    private function ternaryOnly(): array
    {
        $d = $this->resource->flag ? ['by' => $this->resource->title] : [];

        return $d;
    }

    /**
     * A `match` base.
     */
    private function matchBased(): array
    {
        $d = match ($this->resource->pick) {
            'x' => ['by' => $this->resource->title],
            default => [],
        };

        return $d;
    }

    /**
     * A ternary of literals returned whole.
     */
    private function returnedTernary(): array
    {
        return $this->resource->flag ? ['by' => $this->resource->title] : [];
    }

    /**
     * A ternary base defaulted by `$d['by'] ?? 'z'`.
     */
    private function oldCoalesceIdiom(): array
    {
        $d = $this->resource->flag ? ['by' => $this->resource->title] : [];
        $d['by'] = $d['by'] ?? 'z';

        return $d;
    }

    /**
     * A ternary base holding the author, then a `+=` of a string default.
     */
    private function plusDefaulted(): array
    {
        $d = $this->resource->flag ? ['by' => $this->resource->author] : [];
        $d += ['by' => 'z'];

        return $d;
    }

    /**
     * A copy of a local each branch writes whole, given another key.
     */
    private function joinedBase(): array
    {
        if ($this->resource->flag) {
            $b = ['a' => 1];
        } else {
            $b = ['a' => 'x'];
        }

        $d = $b;
        $d['c'] = 1;

        return $d;
    }

    /**
     * A ternary of two literals with no key in common, returned whole.
     */
    private function wholeTernary(): array
    {
        return $this->resource->flag ? ['by' => $this->resource->author] : ['id' => 1];
    }

    /**
     * A ternary base that never holds `by`, then a `??=` of it.
     */
    private function ternaryUnheldCoalesced(): array
    {
        $d = $this->resource->flag ? ['n' => 1] : [];
        $d['by'] ??= 'z';

        return $d;
    }

    /**
     * A literal base, then a `??=` of a key it lacks.
     */
    private function literalUnheldCoalesced(): array
    {
        $d = ['n' => 1];
        $d['by'] ??= 'z';

        return $d;
    }

    /**
     * A helper's base, then a `??=` of a key it lacks.
     */
    private function helperUnheldCoalesced(): array
    {
        $d = $this->numbered();
        $d['by'] ??= 'z';

        return $d;
    }

    /**
     * A base without `by`.
     */
    private function numbered(): array
    {
        return ['n' => 1];
    }

    /**
     * A literal base, then a `??=` of a key it lacks, returned whole.
     */
    private function unheldCoalesced(): array
    {
        $d = ['a' => 1];
        $d['b'] ??= 'z';

        return $d;
    }
}

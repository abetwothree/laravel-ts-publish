<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;
use Throwable;
use Workbench\App\ValueObjects\CreditLine;

/**
 * Locals written and read inside one block, some of which then exit, each in a helper's returns (GH-161 B4, B5).
 *
 * @property-read CreditLine $resource
 */
class BlockReadSpreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'one_return_after_block' => [...$this->oneReturnAfterBlock()],
            'unconditional_write_in_block' => [...$this->unconditionalWriteInBlock()],
            'nested_read_in_block' => [...$this->nestedReadInBlock()],
            'read_in_else' => [...$this->readInElse()],
            'read_in_elseif' => [...$this->readInElseIf()],
            'read_in_switch_case' => [...$this->readInSwitchCase()],
            'read_in_try' => [...$this->readInTry()],
            'read_two_deep' => [...$this->readTwoDeep()],
            'read_in_when_closure' => $this->when($this->resource->flag, function () {
                if ($this->resource->pick === 'x') {
                    $maybe = [];
                    $maybe['by'] = $this->resource->title;

                    return ['by' => $this->resource->author, ...$maybe];
                }

                return ['by' => $this->resource->author];
            }),
            'read_in_loop_return' => [...$this->readInLoopReturn()],
            'read_in_switch_one_path' => [...$this->readInSwitchOnePath()],
            'read_in_try_rethrow' => [...$this->readInTryRethrow()],
            'value_read_in_writing_block' => [...$this->valueReadInWritingBlock()],
            'maybe_local_value_in_block' => [...$this->maybeLocalValueInBlock()],
            'condition_rewrites_local' => [...$this->conditionRewritesLocal()],
            'emptiness_guard_throws' => [...$this->emptinessGuardThrows()],
            'emptiness_guard_returns' => [...$this->emptinessGuardReturns()],
            'conditional_unset_in_block' => [...$this->conditionalUnsetInBlock()],
            'key_write_in_exiting_block' => [...$this->keyWriteInExitingBlock()],
            'exiting_key_write_nested' => [...$this->exitingKeyWriteNested()],
            'exiting_maybe_key_write' => [...$this->exitingMaybeKeyWrite()],
            'whole_write_nested_in_exiting_block' => [...$this->wholeWriteNestedInExitingBlock()],
            'nested_whole_write_then_spread' => [...$this->nestedWholeWriteThenSpread()],
        ];
    }

    /**
     * A local an inner `if` may write, spread inside its block into the one returned local.
     */
    private function oneReturnAfterBlock(): array
    {
        $out = ['n' => 1];

        if ($this->resource->flag) {
            $maybe = [];

            if ($this->resource->pick === 'x') {
                $maybe['by'] = $this->resource->title;
            }

            $out = ['by' => $this->resource->author, ...$maybe];
        }

        return $out;
    }

    /**
     * A local its block always writes, spread in a return there.
     */
    private function unconditionalWriteInBlock(): array
    {
        if ($this->resource->flag) {
            $maybe = [];
            $maybe['by'] = $this->resource->title;

            return ['by' => $this->resource->author, ...$maybe];
        }

        return ['n' => 1];
    }

    /**
     * A local an inner `if` may write, spread in a nested literal its block returns.
     */
    private function nestedReadInBlock(): array
    {
        if ($this->resource->flag) {
            $maybe = [];

            if ($this->resource->pick === 'x') {
                $maybe['by'] = $this->resource->title;
            }

            return ['row' => ['by' => $this->resource->author, ...$maybe]];
        }

        return ['n' => 1];
    }

    /**
     * The same, in an `else` branch.
     */
    private function readInElse(): array
    {
        if (! $this->resource->flag) {
            return ['n' => 1];
        } else {
            $maybe = [];

            if ($this->resource->pick === 'x') {
                $maybe['by'] = $this->resource->title;
            }

            return ['by' => $this->resource->author, ...$maybe];
        }
    }

    /**
     * The same, in an `elseif` branch.
     */
    private function readInElseIf(): array
    {
        if ($this->resource->pick === 'r') {
            return ['n' => 1];
        } elseif ($this->resource->flag) {
            $maybe = [];

            if ($this->resource->pick === 'x') {
                $maybe['by'] = $this->resource->title;
            }

            return ['by' => $this->resource->author, ...$maybe];
        }

        return ['n' => 2];
    }

    /**
     * The same, in a `switch` case.
     */
    private function readInSwitchCase(): array
    {
        switch ($this->resource->pick) {
            case 'x':
                $maybe = [];

                if ($this->resource->flag) {
                    $maybe['by'] = $this->resource->title;
                }

                return ['by' => $this->resource->author, ...$maybe];
            default:
                return ['n' => 1];
        }
    }

    /**
     * The same, in a `try` body.
     */
    private function readInTry(): array
    {
        try {
            $maybe = [];

            if ($this->resource->pick === 'x') {
                $maybe['by'] = $this->resource->title;
            }

            $this->resource->audit();

            return ['by' => $this->resource->author, ...$maybe];
        } catch (Throwable) {
            return ['n' => 1];
        }
    }

    /**
     * A key write and its spread one `if` deeper.
     */
    private function readTwoDeep(): array
    {
        if ($this->resource->flag) {
            $maybe = [];

            if ($this->resource->pick === 'x') {
                $maybe['by'] = $this->resource->title;

                return ['by' => $this->resource->author, ...$maybe];
            }

            return ['n' => 2];
        }

        return ['n' => 1];
    }

    /**
     * The same, in a `foreach` body that returns from the loop.
     */
    private function readInLoopReturn(): array
    {
        foreach ($this->resource->rows as $r) {
            $maybe = [];

            if ($r['done']) {
                $maybe['by'] = $this->resource->title;
            }

            if ($this->resource->flag) {
                return ['by' => $this->resource->author, ...$maybe];
            }
        }

        return ['n' => 1];
    }

    /**
     * The same, in the one `switch` case that returns; the default throws.
     */
    private function readInSwitchOnePath(): array
    {
        switch ($this->resource->pick) {
            case 'x':
            case 's':
                $maybe = [];

                if ($this->resource->flag) {
                    $maybe['by'] = $this->resource->title;
                }

                return ['by' => $this->resource->author, ...$maybe];
            default:
                throw new RuntimeException('pick');
        }
    }

    /**
     * The same, in a `try` body whose `catch` rethrows.
     */
    private function readInTryRethrow(): array
    {
        try {
            $maybe = [];

            if ($this->resource->pick === 'x') {
                $maybe['by'] = $this->resource->title;
            }

            return ['by' => $this->resource->author, ...$maybe];
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * A local given a key and read as a value inside the block that writes it.
     */
    private function valueReadInWritingBlock(): array
    {
        if ($this->resource->flag) {
            $m = ['a' => 1];
            $m['b'] = 2;

            return ['early' => $m];
        }

        return ['n' => 1];
    }

    /**
     * A local an inner `if` may write, read as a value inside its block.
     */
    private function maybeLocalValueInBlock(): array
    {
        if ($this->resource->flag) {
            $maybe = [];

            if ($this->resource->pick === 'x') {
                $maybe['by'] = $this->resource->title;
            }

            return ['row' => $maybe];
        }

        return ['n' => 1];
    }

    /**
     * An inner `if` whose condition rewrites the local before its branch reads it.
     */
    private function conditionRewritesLocal(): array
    {
        if ($this->resource->flag) {
            $m = ['a' => 1];

            if ($m = $this->resource->extras()) {
                $m['z'] = 1;

                return ['v' => $m];
            }
        }

        return ['n' => 1];
    }

    /**
     * A map past an exact emptiness guard that throws, read as a value in the block.
     */
    private function emptinessGuardThrows(): array
    {
        if ($this->resource->flag) {
            $counts = $this->resource->maybeCounts ?? [];

            if ($counts === []) {
                throw new RuntimeException('counts');
            }

            return ['row' => $counts];
        }

        return ['n' => 1];
    }

    /**
     * The same past a guard that returns another key.
     */
    private function emptinessGuardReturns(): array
    {
        if ($this->resource->flag) {
            $counts = $this->resource->maybeCounts ?? [];

            if ($counts === []) {
                return ['n' => 0];
            }

            return ['row' => $counts];
        }

        return ['n' => 1];
    }

    /**
     * A literal whose second key an inner `if` unsets, read as a value in its block.
     */
    private function conditionalUnsetInBlock(): array
    {
        if ($this->resource->flag) {
            $d = ['id' => 1, 'secret' => $this->resource->title];

            if ($this->resource->pick === 'x') {
                unset($d['secret']);
            }

            return ['row' => $d];
        }

        return ['n' => 1];
    }

    /**
     * A local given `by` in a block that spreads it over an earlier `by` and exits.
     */
    private function keyWriteInExitingBlock(): array
    {
        $m = [];

        if ($this->resource->flag) {
            $m['by'] = $this->resource->title;

            return ['by' => $this->resource->author, ...$m];
        }

        return ['n' => 1];
    }

    /**
     * The same, spread in a nested literal.
     */
    private function exitingKeyWriteNested(): array
    {
        $m = [];

        if ($this->resource->flag) {
            $m['by'] = $this->resource->title;

            return ['row' => ['by' => $this->resource->author, ...$m]];
        }

        return ['n' => 1];
    }

    /**
     * The same, with the key write conditional inside the exiting block.
     */
    private function exitingMaybeKeyWrite(): array
    {
        $m = [];

        if ($this->resource->flag) {
            if ($this->resource->pick === 'x') {
                $m['by'] = $this->resource->title;
            }

            return ['by' => $this->resource->author, ...$m];
        }

        return ['n' => 1];
    }

    /**
     * A whole write nested in a block that exits, with no key write anywhere.
     */
    private function wholeWriteNestedInExitingBlock(): array
    {
        $m = ['a' => 1];

        if ($this->resource->flag) {
            if ($this->resource->pick === 'x') {
                $m = ['z' => 1];
            }

            return ['early' => $m];
        }

        return ['late' => $m];
    }

    /**
     * The same, spread over an earlier `by`.
     */
    private function nestedWholeWriteThenSpread(): array
    {
        $m = [];

        if ($this->resource->flag) {
            if ($this->resource->pick === 'x') {
                $m = ['by' => $this->resource->title];
            }

            return ['by' => $this->resource->author, ...$m];
        }

        return ['n' => 1];
    }
}

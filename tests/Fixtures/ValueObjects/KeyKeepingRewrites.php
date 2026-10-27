<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use Illuminate\Support\Arr;

/**
 * Helpers that rewrite a local, or the array they are passed, inside an `if` or a loop in a way that keeps every key
 * it holds, and return it.
 */
final class KeyKeepingRewrites
{
    /**
     * @param  list<int>  $rows
     * @param  array<string, int>  $extra
     */
    public function __construct(public bool $rewrite = false, public array $rows = [], public array $extra = ['e' => 1]) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'merge_if' => $this->mergeIf(),
            'merge_loop' => $this->mergeLoop(),
            'merge_recursive_if' => $this->mergeRecursiveIf(),
            'merge_recursive_loop' => $this->mergeRecursiveLoop(),
            'replace_if' => $this->replaceIf(),
            'replace_loop' => $this->replaceLoop(),
            'replace_recursive_if' => $this->replaceRecursiveIf(),
            'replace_recursive_loop' => $this->replaceRecursiveLoop(),
            'arr_add_if' => $this->arrAddIf(),
            'arr_add_loop' => $this->arrAddLoop(),
            'arr_set_if' => $this->arrSetIf(),
            'arr_set_loop' => $this->arrSetLoop(),
            'plus_if' => $this->plusIf(),
            'plus_loop' => $this->plusLoop(),
            'plus_assign_if' => $this->plusAssignIf(),
            'plus_assign_loop' => $this->plusAssignLoop(),
            'ref_foreach_if' => $this->refForeachIf(),
            'ref_foreach_loop' => $this->refForeachLoop(),
            'walk_if' => $this->walkIf(),
            'walk_loop' => $this->walkLoop(),
            'walk_recursive_if' => $this->walkRecursiveIf(),
            'walk_recursive_loop' => $this->walkRecursiveLoop(),
            'plus_inline_if' => $this->plusInlineIf(),
            'merge_other_if' => $this->mergeOtherIf(),
            'plus_other_if' => $this->plusOtherIf(),
            'param_merge_if' => $this->paramMergeIf(['id' => 1]),
            'param_walk_if' => $this->paramWalkIf(['id' => 1]),
        ];
    }

    /**
     * Builds a local, then merges another array into it when asked to.
     *
     * @return array<string, mixed>
     */
    private function mergeIf(): array
    {
        $data = ['id' => 1];

        if ($this->rewrite) {
            $data = array_merge($data, ['m' => 1]);
        }

        return $data;
    }

    /**
     * Builds a local, then merges another array into it once per row.
     *
     * @return array<string, mixed>
     */
    private function mergeLoop(): array
    {
        $data = ['id' => 1];

        foreach ($this->rows as $row) {
            $data = array_merge($data, ['m' => 1]);
        }

        return $data;
    }

    /**
     * Builds a local, then merges another array into it recursively when asked to.
     *
     * @return array<string, mixed>
     */
    private function mergeRecursiveIf(): array
    {
        $data = ['id' => 1];

        if ($this->rewrite) {
            $data = array_merge_recursive($data, ['m' => 1]);
        }

        return $data;
    }

    /**
     * Builds a local, then merges another array into it recursively once per row.
     *
     * @return array<string, mixed>
     */
    private function mergeRecursiveLoop(): array
    {
        $data = ['id' => 1];

        foreach ($this->rows as $row) {
            $data = array_merge_recursive($data, ['m' => 1]);
        }

        return $data;
    }

    /**
     * Builds a local, then replaces its values from another array when asked to.
     *
     * @return array<string, mixed>
     */
    private function replaceIf(): array
    {
        $data = ['id' => 1];

        if ($this->rewrite) {
            $data = array_replace($data, ['id' => 2]);
        }

        return $data;
    }

    /**
     * Builds a local, then replaces its values from another array once per row.
     *
     * @return array<string, mixed>
     */
    private function replaceLoop(): array
    {
        $data = ['id' => 1];

        foreach ($this->rows as $row) {
            $data = array_replace($data, ['id' => 2]);
        }

        return $data;
    }

    /**
     * Builds a local, then replaces its values from another array recursively when asked to.
     *
     * @return array<string, mixed>
     */
    private function replaceRecursiveIf(): array
    {
        $data = ['id' => 1];

        if ($this->rewrite) {
            $data = array_replace_recursive($data, ['id' => 2]);
        }

        return $data;
    }

    /**
     * Builds a local, then replaces its values from another array recursively once per row.
     *
     * @return array<string, mixed>
     */
    private function replaceRecursiveLoop(): array
    {
        $data = ['id' => 1];

        foreach ($this->rows as $row) {
            $data = array_replace_recursive($data, ['id' => 2]);
        }

        return $data;
    }

    /**
     * Builds a local, then adds a key through Arr::add() when asked to.
     *
     * @return array<string, mixed>
     */
    private function arrAddIf(): array
    {
        $data = ['id' => 1];

        if ($this->rewrite) {
            $data = Arr::add($data, 'k', 1);
        }

        return $data;
    }

    /**
     * Builds a local, then adds a key through Arr::add() once per row.
     *
     * @return array<string, mixed>
     */
    private function arrAddLoop(): array
    {
        $data = ['id' => 1];

        foreach ($this->rows as $row) {
            $data = Arr::add($data, 'k', 1);
        }

        return $data;
    }

    /**
     * Builds a local, then sets a key through Arr::set() when asked to.
     *
     * @return array<string, mixed>
     */
    private function arrSetIf(): array
    {
        $data = ['id' => 1];

        if ($this->rewrite) {
            Arr::set($data, 'k', 1);
        }

        return $data;
    }

    /**
     * Builds a local, then sets a key through Arr::set() once per row.
     *
     * @return array<string, mixed>
     */
    private function arrSetLoop(): array
    {
        $data = ['id' => 1];

        foreach ($this->rows as $row) {
            Arr::set($data, 'k', 1);
        }

        return $data;
    }

    /**
     * Builds a local, then unions another array onto it when asked to.
     *
     * @return array<string, mixed>
     */
    private function plusIf(): array
    {
        $data = ['id' => 1];

        if ($this->rewrite) {
            $data = $data + ['p' => 1];
        }

        return $data;
    }

    /**
     * Builds a local, then unions another array onto it once per row.
     *
     * @return array<string, mixed>
     */
    private function plusLoop(): array
    {
        $data = ['id' => 1];

        foreach ($this->rows as $row) {
            $data = $data + ['p' => 1];
        }

        return $data;
    }

    /**
     * Builds a local, then unions an array it cannot read onto it in place when asked to.
     *
     * @return array<string, mixed>
     */
    private function plusAssignIf(): array
    {
        $data = ['id' => 1];

        if ($this->rewrite) {
            $data += $this->extra;
        }

        return $data;
    }

    /**
     * Builds a local, then unions an array it cannot read onto it in place once per row.
     *
     * @return array<string, mixed>
     */
    private function plusAssignLoop(): array
    {
        $data = ['id' => 1];

        foreach ($this->rows as $row) {
            $data += $this->extra;
        }

        return $data;
    }

    /**
     * Builds a local, then doubles each value through a by-reference foreach when asked to.
     *
     * @return array<string, mixed>
     */
    private function refForeachIf(): array
    {
        $data = ['id' => 1];

        if ($this->rewrite) {
            foreach ($data as &$value) {
                $value = $value * 2;
            }
        }

        unset($value);

        return $data;
    }

    /**
     * Builds a local, then doubles each value through a by-reference foreach once per row.
     *
     * @return array<string, mixed>
     */
    private function refForeachLoop(): array
    {
        $data = ['id' => 1];

        foreach ($this->rows as $row) {
            foreach ($data as &$value) {
                $value = $value * 2;
            }
        }

        unset($value);

        return $data;
    }

    /**
     * Builds a local, then doubles each value through array_walk() when asked to.
     *
     * @return array<string, mixed>
     */
    private function walkIf(): array
    {
        $data = ['id' => 1];

        if ($this->rewrite) {
            array_walk($data, function (int &$value): void {
                $value = $value * 2;
            });
        }

        return $data;
    }

    /**
     * Builds a local, then doubles each value through array_walk() once per row.
     *
     * @return array<string, mixed>
     */
    private function walkLoop(): array
    {
        $data = ['id' => 1];

        foreach ($this->rows as $row) {
            array_walk($data, function (int &$value): void {
                $value = $value * 2;
            });
        }

        return $data;
    }

    /**
     * Builds a local, then doubles each value through array_walk_recursive() when asked to.
     *
     * @return array<string, mixed>
     */
    private function walkRecursiveIf(): array
    {
        $data = ['id' => 1];

        if ($this->rewrite) {
            array_walk_recursive($data, function (int &$value): void {
                $value = $value * 2;
            });
        }

        return $data;
    }

    /**
     * Builds a local, then doubles each value through array_walk_recursive() once per row.
     *
     * @return array<string, mixed>
     */
    private function walkRecursiveLoop(): array
    {
        $data = ['id' => 1];

        foreach ($this->rows as $row) {
            array_walk_recursive($data, function (int &$value): void {
                $value = $value * 2;
            });
        }

        return $data;
    }

    /**
     * Builds a local, then unions an array it cannot read onto it inside another assignment when asked to.
     *
     * @return array<string, mixed>
     */
    private function plusInlineIf(): array
    {
        $data = ['id' => 1];

        if ($this->rewrite) {
            $merged = ($data += $this->extra);
        }

        return $data;
    }

    /**
     * Builds a local, then replaces it with a merge of two other arrays when asked to.
     *
     * @return array<string, mixed>
     */
    private function mergeOtherIf(): array
    {
        $data = ['id' => 1];

        if ($this->rewrite) {
            $data = array_merge($this->extra, ['m' => 1]);
        }

        return $data;
    }

    /**
     * Builds a local, then replaces it with a union of two other arrays when asked to.
     *
     * @return array<string, mixed>
     */
    private function plusOtherIf(): array
    {
        $data = ['id' => 1];

        if ($this->rewrite) {
            $data = $this->extra + ['p' => 1];
        }

        return $data;
    }

    /**
     * Merges another array into the array it is passed when asked to, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function paramMergeIf(array $data): array
    {
        if ($this->rewrite) {
            $data = array_merge($data, ['m' => 1]);
        }

        $data['x'] = 1;

        return $data;
    }

    /**
     * Doubles each value of the array it is passed through array_walk() when asked to, then adds a key.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function paramWalkIf(array $data): array
    {
        if ($this->rewrite) {
            array_walk($data, function (int &$value): void {
                $value = $value * 2;
            });
        }

        $data['x'] = 1;

        return $data;
    }
}

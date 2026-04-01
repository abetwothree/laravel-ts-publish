<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Concerns;

trait IncludesConditionalData
{
    /**
     * Base array with conditional additions via dim assignment.
     *
     * @return array{baseKey: string}
     */
    protected function includeConditionalData(): array
    {
        $data = [
            'baseKey' => strtoupper('base'),
        ];

        if ($this->resource !== null) {
            $data['conditionalKey'] = strtolower('conditional');
        }

        return $data;
    }

    /**
     * Empty array with dim-only assignments, some conditional.
     */
    protected function includeDimAssigned(): array
    {
        $data = [];
        $data['always'] = strtoupper('present');

        if ($this->resource !== null) {
            $data['sometimes'] = strtolower('maybe');
        }

        return $data;
    }

    /**
     * Tests if/elseif/else branches — all properties should be optional.
     */
    protected function includeMultiBranch(): array
    {
        $data = [];

        if ($this->resource !== null) {
            $data['ifBranch'] = strtoupper('if');
        } elseif (method_exists($this->resource, 'getTable')) {
            $data['elseifBranch'] = strtolower('elseif');
        } else {
            $data['elseBranch'] = strtolower('else');
        }

        return $data;
    }

    /**
     * Method that returns a method call result — not an array literal or variable.
     * Exercises the else fallback branch in analyzeThisMethodSpread.
     */
    protected function includeFromMethodCall(): array
    {
        return $this->includeNonAnalyzable();
    }

    /**
     * @return array<string, string>
     */
    protected function includeNonAnalyzable(): array
    {
        return ['dynamic' => 'value'];
    }

    /**
     * Conditional base array assignment inside an if block.
     * Exercises isConditional + Array_ base assignment branch.
     */
    protected function includeConditionalBase(): array
    {
        $data = [];

        if ($this->resource !== null) {
            $data = [
                'conditionalBaseKey' => strtoupper('hello'),
            ];
        }

        return $data;
    }

    /**
     * Handle assignments inside a foreach loop
     */
    protected function returnsFromForEach(): array
    {
        $data = [];

        foreach (['a', 'b', 'c'] as $item) {
            if ($item === 'b') {
                $data['foundB'] = true;
            } else {
                $data[$item] = false;
            }
        }

        return $data;
    }

    /**
     * Simple foreach with string-keyed dim assignments and variable return.
     */
    protected function returnsFromSimpleForEach(): array
    {
        $data = [];

        foreach (['a', 'b'] as $item) {
            $data['foreachKey'] = strtolower($item);
        }

        return $data;
    }

    /**
     * Handle assignments inside a for loop.
     */
    protected function returnsFromForLoop(): array
    {
        $data = [];

        for ($i = 0; $i < 3; $i++) {
            $data['forKey'] = strtoupper('value');
        }

        return $data;
    }

    /**
     * Handle assignments inside a while loop.
     */
    protected function returnsFromWhileLoop(): array
    {
        $data = [];

        while ($this->resource !== null) {
            $data['whileKey'] = strtolower('loop');

            break;
        }

        return $data;
    }

    /**
     * Handle assignments inside a do-while loop.
     */
    protected function returnsFromDoWhile(): array
    {
        $data = [];

        do {
            $data['doWhileKey'] = strtoupper('once');
        } while (false);

        return $data;
    }

    /**
     * Duplicate key: unconditional then conditional override.
     * The key should appear once and be NOT optional.
     */
    protected function includesDuplicateKey(): array
    {
        $data = [];
        $data['status'] = strtolower('active');

        if ($this->resource !== null) {
            $data['status'] = strtoupper('inactive');
        }

        return $data;
    }
}

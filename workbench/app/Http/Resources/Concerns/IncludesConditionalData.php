<?php

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
}

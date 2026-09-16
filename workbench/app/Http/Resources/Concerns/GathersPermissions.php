<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;

trait GathersPermissions
{
    /**
     * A guard branch returns nothing, so both keys are absent on that path.
     *
     * @return array{permissions?: array<string, bool>, links?: array{self: string, related: array<string, array{name: string}>}}
     */
    public function gatherPermissions(): array
    {
        if (! $this->resource instanceof Model) {
            return [];
        }

        return ['permissions' => $this->opaque(), 'links' => $this->opaque()];
    }

    /**
     * Every value shares one declared type, which only the docblock states.
     *
     * @return array<string, string>
     */
    public function gatherLabels(): array
    {
        $data = [];
        $data['main_label'] = $this->opaque();

        if ($this->resource->exists) {
            $data['extra_label'] = $this->opaque();
        }

        return $data;
    }

    /** Deliberately untyped so only the docblocks above can type what it returns. */
    protected function opaque()
    {
        return $this->resource->getAttribute('title');
    }
}

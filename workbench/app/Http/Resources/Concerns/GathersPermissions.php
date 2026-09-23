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

    /**
     * A key built from literal text around a loop variable, not a fixed set of names.
     *
     * @return array<string, string>
     */
    public function gatherChannelLabels(): array
    {
        $data = ['primary_label' => 'Primary'];

        foreach (['email', 'sms'] as $name) {
            $data["{$name}_label"] = 'Channel';
        }

        return $data;
    }

    /**
     * The `.` concatenation form of an interpolated key, pinned separately from the encapsed one.
     *
     * @return array<string, string>
     */
    public function gatherRegionLabels(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data[$name.'_region'] = 'Region';
        }

        return $data;
    }

    /**
     * An interpolated key whose value only the docblock types.
     *
     * @return array<string, string>
     */
    public function gatherOpaqueTags(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_tag"] = $this->opaque();
        }

        return $data;
    }

    /**
     * A key whose literal text holds a backslash: unless it is written `\\`, TypeScript reads `\u` as a Unicode escape.
     *
     * @return array<string, string>
     */
    public function gatherEscapedUnits(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}\\unit"] = 'Unit';
        }

        return $data;
    }

    /** Deliberately untyped so only the docblocks above can type what it returns. */
    protected function opaque()
    {
        return $this->resource->getAttribute('title');
    }
}

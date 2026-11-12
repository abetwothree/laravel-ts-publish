<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Runners;

use Illuminate\View\Engines\CompilerEngine;
use Override;

/**
 * The Blade engine a run renders with: it compiles each view from its source the first time it renders it, however
 * new the view's compiled copy is, and reuses that compile for the rest of the run.
 *
 * @internal
 */
final class SourceCompilerEngine extends CompilerEngine
{
    /**
     * Compile the view on its first render, then render it as Blade does.
     *
     * @param  string  $path
     * @param  array<array-key, mixed>  $data
     */
    #[Override]
    public function get($path, array $data = []): string
    {
        if (! isset($this->compiledOrNotExpired[$path])) {
            $this->compiler->compile($path);
            // Skips Blade's own mtime check, which would compile again when the source is as new as the compile.
            $this->compiledOrNotExpired[$path] = true;
        }

        return parent::get($path, $data);
    }
}

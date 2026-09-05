<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Generators\CoreGenerator;
use AbeTwoThree\LaravelTsPublish\Writers\BarrelWriter;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Override;

/** A barrel_writer_class that changes the barrel format, so it overrides both modular entry points. */
final class HeaderedBarrelWriter extends BarrelWriter
{
    public const string HEADER = '// custom header';

    /**
     * Write per-namespace barrels in this writer's own format.
     *
     * @param  Collection<int, CoreGenerator>  $generators
     * @return array<string, string>
     */
    #[Override]
    public function writeModular(Collection $generators, ?string $outputBase = null): array
    {
        return $this->writeHeaderedBarrels($generators, $outputBase, null);
    }

    /**
     * Write per-namespace barrels in this writer's own format, keeping the exports $keepExisting approves.
     *
     * @param  Collection<int, CoreGenerator>  $generators
     * @param  Closure(string): bool  $keepExisting
     * @return array<string, string>
     */
    #[Override]
    public function writeModularPreserving(Collection $generators, Closure $keepExisting, ?string $outputBase = null): array
    {
        return $this->writeHeaderedBarrels($generators, $outputBase, $keepExisting);
    }

    /**
     * Reuse the inherited merge, then re-emit each barrel with the header.
     *
     * @param  Collection<int, CoreGenerator>  $generators
     * @param  (Closure(string): bool)|null  $keepExisting
     * @return array<string, string>
     */
    private function writeHeaderedBarrels(Collection $generators, ?string $outputBase, ?Closure $keepExisting): array
    {
        $barrels = $this->writeModularBarrels($generators, $outputBase, $keepExisting);

        $base = is_string($outputBase) && $outputBase !== ''
            ? $outputBase
            : Config::string('ts-publish.output_directory');

        foreach ($barrels as $namespacePath => $content) {
            $barrels[$namespacePath] = self::HEADER."\n".$content;

            if (Config::boolean('ts-publish.output_to_files')) {
                $this->putIfChanged("$base/$namespacePath/index.ts", $barrels[$namespacePath]);
            }
        }

        return $barrels;
    }
}

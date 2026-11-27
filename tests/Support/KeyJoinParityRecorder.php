<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Support;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeObserver;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use ReflectionProperty;

/**
 * Records, per test, each analysis the walk, the fold and a join hand on: its rows, the gap ids they spell, the gap
 * ledger with each id's subject, site and place, and every other field, so two runs of the suite compare join by join.
 * Bound by tests/Pest.php only while KEY_JOIN_PARITY names its directory; it hands each analysis on to $inner.
 */
final class KeyJoinParityRecorder implements ShapeObserver
{
    /**
     * Record into one file per test under $directory, handing each analysis on to $inner.
     */
    public function __construct(
        private readonly string $directory,
        private readonly string $test,
        public readonly ShapeObserver $inner,
    ) {
        if (! is_dir($this->directory)) {
            @mkdir($this->directory, 0777, true);
        }

        @unlink($this->file());
    }

    /**
     * Record one analysis, then hand it on.
     */
    public function observed(MethodAnalysis $analysis): void
    {
        $gaps = [];

        foreach ($analysis->properties as $row) {
            array_push($gaps, ...VisibleGaps::ids($row['type']));
        }

        $line = json_encode([
            'rows' => $analysis->properties,
            'gaps' => $gaps,
            'ledger' => DroppedUnionArms::gapsSince(0),
            'mark' => DroppedUnionArms::gapMark(),
            'keys' => array_flip((new ReflectionProperty(DroppedUnionArms::class, 'gapIds'))->getValue()),
            'fields' => self::fields($analysis),
        ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        file_put_contents($this->file(), $line."\n", FILE_APPEND);
        $this->inner->observed($analysis);
    }

    /**
     * Every field of the analysis but its rows, an object one by its class.
     *
     * @return array<string, mixed>
     */
    private static function fields(MethodAnalysis $analysis): array
    {
        $fields = [];

        foreach (RoundTripShapeObserver::fields($analysis::class) as $field) {
            if ($field->getName() !== 'properties') {
                $value = $field->getValue($analysis);
                $fields[$field->getName()] = is_object($value) ? $value::class : $value;
            }
        }

        return $fields;
    }

    /**
     * This test's record file.
     */
    private function file(): string
    {
        return $this->directory.'/'.md5($this->test).'.jsonl';
    }
}

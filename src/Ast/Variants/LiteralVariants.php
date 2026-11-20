<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Variants;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold\LiteralWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\IndexSignatureReconciler;

/**
 * The groups one array literal's fold holds, beside the writes they were read from, whether `filter()` lifts the
 * literal's merge calls, and every row and cast the fold wrote, which a later rewrite or cast no longer matches.
 *
 * @phpstan-import-type RowFingerprints from VariantGroup
 * @phpstan-import-type ReplayedValues from LiteralWrites
 * @phpstan-import-type CastMap from MethodAnalysis
 *
 * @internal
 */
final class LiteralVariants
{
    /** The most objects a literal prints: a group whose variants would pass it stays one row in every object. */
    private const int MOST_OBJECTS = 16;

    /** @var list<MethodAnalysis>|null each object the literal prints, replayed once */
    private ?array $objects = null;

    /**
     * The groups of one literal.
     *
     * @param  list<VariantGroup>  $groups  in the order of their discriminants
     * @param  RowFingerprints  $fingerprint  every row the fold wrote
     * @param  CastMap  $casts  every cast the fold wrote, which the replayed objects carry too
     */
    public function __construct(
        public readonly array $groups,
        public readonly LiteralWrites $writes,
        public readonly bool $liftsMerges,
        public readonly array $fingerprint,
        public readonly array $casts,
    ) {}

    /**
     * Whether an analysis still holds every row and cast the fold wrote, and no other, so its objects describe what it
     * sends: a cast laid after the fold never reaches them, so they would drop its import and class.
     */
    public function holdsFor(MethodAnalysis $analysis): bool
    {
        $rows = VariantGroup::fingerprint($analysis, array_column($analysis->writtenProperties(), 'name'));

        return $rows === $this->fingerprint && $analysis->casts === $this->casts;
    }

    /**
     * The literal's fold replayed once per combination of its groups' variants, the first group's outermost: each
     * object sends a discriminant's literal and its dependents' arms. Empty where every group would pass MOST_OBJECTS.
     *
     * @return list<MethodAnalysis>
     */
    public function objects(): array
    {
        return $this->objects ??= array_map($this->replayedObject(...), $this->combinations());
    }

    /**
     * What each object's grouped keys send, in group-then-variant order; none where no group fits under MOST_OBJECTS.
     *
     * @return list<ReplayedValues>
     */
    private function combinations(): array
    {
        $combinations = [[]];
        $split = false;

        foreach ($this->groups as $group) {
            if (count($combinations) * count($group->variants) > self::MOST_OBJECTS) {
                continue;
            }

            $split = true;
            $combinations = array_merge(...array_map(
                static fn (array $values): array => array_map(
                    static fn (Variant $variant): array => array_replace($values, $group->sent($variant)),
                    $group->variants,
                ),
                $combinations,
            ));
        }

        return $split ? $combinations : [];
    }

    /**
     * The literal's fold with each grouped key sending the given value.
     *
     * @param  ReplayedValues  $values
     */
    private function replayedObject(array $values): MethodAnalysis
    {
        $object = new MethodAnalysis;
        $this->writes->replayed($values)->applyTo($object, $this->liftsMerges);
        resolve(IndexSignatureReconciler::class)->reconcile($object);

        return $object;
    }
}

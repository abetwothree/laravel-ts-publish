<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassQueueGuard;

/**
 * One generated body: a shape and its arms in written order.
 */
final readonly class GuardCase
{
    private const string NAMESPACE = 'AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassQueueGuard\Generated';

    /**
     * @param  non-empty-list<ArmKind>  $arms
     */
    public function __construct(
        public BodyShape $shape,
        public array $arms,
    ) {}

    /**
     * The statements of the body's toArray().
     */
    public function body(): string
    {
        return $this->shape->body(array_map(static fn (ArmKind $arm): string => $arm->expression(), $this->arms));
    }

    /**
     * The resource class the body publishes from, named $class, as the stub templates it.
     *
     * @return array{class-string, string} The class's FQCN and its source.
     */
    public function source(string $class): array
    {
        $source = strtr((string) file_get_contents(__DIR__.'/GuardResource.php.stub'), [
            '{{ namespace }}' => self::NAMESPACE,
            '{{ class }}' => $class,
            '{{ body }}' => $this->body(),
        ]);

        /** @var class-string $fqcn */
        $fqcn = self::NAMESPACE.'\\'.$class;

        return [$fqcn, $source];
    }

    /**
     * The members the union must publish, in order: one per distinct arm, first written first, and `null` last, where
     * every union hoists it, an untyped arm left out; or `unknown` alone where returns write one. A filtered list's
     * members come first, last written first and with no null. Each member is the classes its tokens name.
     *
     * @return list<list<class-string>>
     */
    public function expected(): array
    {
        // A strict join of returns cannot say what the untyped branch sends, and a dropping join leaves it out.
        if ($this->shape === BodyShape::Returns && in_array(ArmKind::Untyped, $this->arms, true)) {
            return [[]];
        }

        $distinct = [];

        foreach (array_filter($this->arms, static fn (ArmKind $arm): bool => $arm !== ArmKind::Untyped) as $arm) {
            $distinct[$arm->value] = $arm->classes();
        }

        $null = array_key_exists(ArmKind::Null->value, $distinct) ? [ArmKind::Null->classes()] : [];
        unset($distinct[ArmKind::Null->value]);
        $filtered = [];
        $listed = $this->shape === BodyShape::AfterFiltered ? array_reverse($this->arms) : [];

        foreach (array_filter($listed, static fn (ArmKind $arm): bool => $arm !== ArmKind::Null) as $arm) {
            $filtered[$arm->value] = $arm->classes();
        }

        return [...array_values($filtered), ...array_values($distinct), ...$null];
    }

    /**
     * The same body with the arm at $index left out; null where it is the only arm.
     */
    public function without(int $index): ?self
    {
        $arms = $this->arms;
        array_splice($arms, $index, 1);

        return $arms === [] ? null : new self($this->shape, $arms);
    }

    /**
     * The shape and arms, as a failure names them.
     */
    public function label(): string
    {
        return $this->shape->value.' of ['.implode(', ', array_map(
            static fn (ArmKind $arm): string => $arm->value,
            $this->arms,
        )).']';
    }
}

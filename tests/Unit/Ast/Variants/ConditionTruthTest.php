<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\AstParser;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ReadPath;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\ConditionTruth;
use AbeTwoThree\LaravelTsPublish\Ast\Variants\DiscriminantValue;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Expression;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\PostResource;

/**
 * Parse one PHP expression, names resolved as the analyzer resolves them, with the workbench's Status and Priority
 * imported.
 */
function conditionTruthExpr(string $php): Expr
{
    $statements = new AstParser()->parseSource("<?php use Workbench\\App\\Enums\\{Priority, Status};\n{$php};");
    $statement = end($statements);
    assert($statement instanceof Expression);

    return $statement->expr;
}

/**
 * What ConditionTruth decides a guard as, with the read sending the value.
 */
function conditionTruthOf(string $read, string $guard, bool|int|string|UnitEnum|null $value): ?bool
{
    $path = ReadPath::of(conditionTruthExpr($read));
    assert($path !== null);

    return ConditionTruth::decide(
        conditionTruthExpr($guard),
        $path,
        new DiscriminantValue($value, JsEmitter::toJsLiteral($value)),
        new AnalysisScope(new ReflectionClass(PostResource::class)),
    );
}

it('decides a guard by PHP\'s own operators on the value the read sends', function (
    string $read,
    string $guard,
    bool|int|string|UnitEnum|null $value,
    ?bool $holds,
) {
    expect(conditionTruthOf($read, $guard, $value))->toBe($holds);
})->with([
    'the read, true' => ['$this->is_pinned', '$this->is_pinned', true, true],
    'the read, false' => ['$this->is_pinned', '$this->is_pinned', false, false],
    'the read, null' => ['$this->is_pinned', '$this->is_pinned', null, false],
    'the read, an enum case' => ['$this->status', '$this->status', Status::Draft, true],
    'negated' => ['$this->is_pinned', '! $this->is_pinned', true, false],
    'negated, false' => ['$this->is_pinned', '! $this->is_pinned', false, true],
    'identical to true' => ['$this->is_pinned', '$this->is_pinned === true', true, true],
    'identical to true, false' => ['$this->is_pinned', '$this->is_pinned === true', false, false],
    'false identical to the read' => ['$this->is_pinned', 'false === $this->is_pinned', false, true],
    'false identical to the read, true' => ['$this->is_pinned', 'false === $this->is_pinned', true, false],
    'loosely equal to 1, as true == 1' => ['$this->is_pinned', '$this->is_pinned == 1', true, true],
    'loosely unequal to 1' => ['$this->is_pinned', '$this->is_pinned != 1', false, true],
    'xor' => ['$this->is_pinned', '$this->is_pinned xor true', true, false],
    'a case, the same' => ['$this->status', '$this->status === Status::Draft', Status::Draft, true],
    'a case, another' => ['$this->status', '$this->status === Status::Draft', Status::Published, false],
    'the case\'s value' => ['$this->status', '$this->status->value === 0', Status::Draft, true],
    'the case\'s value, another' => ['$this->status', '$this->status->value === 0', Status::Published, false],
    'the case\'s name' => ['$this->status', '$this->status?->name === \'Published\'', Status::Published, true],
    'not the case' => ['$this->status', '$this->status !== Status::Draft', Status::Draft, false],
    'not the case, another' => ['$this->status', '$this->status !== Status::Draft', Status::Published, true],
    'a case against its scalar' => ['$this->status', '$this->status === 0', Status::Draft, false],
    'a case against another\'s scalar' => ['$this->status', '$this->status === 0', Status::Published, false],
    'identical to null' => ['$this->priority', '$this->priority === null', null, true],
    'identical to null, a case' => ['$this->priority', '$this->priority === null', Priority::High, false],
    'is_null()' => ['$this->priority', 'is_null($this->priority)', null, true],
    'is_null(), a case' => ['$this->priority', 'is_null($this->priority)', Priority::Low, false],
    'isset()' => ['$this->priority', 'isset($this->priority)', null, false],
    'isset(), a case' => ['$this->priority', 'isset($this->priority)', Priority::Low, true],
    'isset() of a member of null' => ['$this->priority', 'isset($this->priority->value)', null, false],
    'empty()' => ['$this->priority', 'empty($this->priority)', null, true],
    'empty(), a case' => ['$this->priority', 'empty($this->priority)', Priority::Low, false],
    'in_array(), strict' => ['$this->priority', 'in_array($this->priority, [Priority::High, Priority::Critical], true)', Priority::High, true],
    'in_array(), strict, another case' => ['$this->priority', 'in_array($this->priority, [Priority::High, Priority::Critical], true)', Priority::Low, false],
    'in_array(), strict, null' => ['$this->priority', 'in_array($this->priority, [Priority::High, Priority::Critical], true)', null, false],
    'in_array(), loose' => ['$this->is_pinned', 'in_array($this->is_pinned, [0, \'\'])', false, true],
    'in_array() by name' => ['$this->priority', 'in_array(haystack: [Priority::Low], needle: $this->priority)', Priority::Low, true],
    '&& on false' => ['$this->is_pinned', '$this->is_pinned && $this->title !== \'\'', false, false],
    '&& on true' => ['$this->is_pinned', '$this->is_pinned && $this->title !== \'\'', true, null],
    'and on true, then true' => ['$this->is_pinned', '$this->is_pinned and true', true, true],
    'or on false, then true' => ['$this->is_pinned', '$this->is_pinned or true', false, true],
    '|| on true' => ['$this->is_pinned', '$this->is_pinned || $this->x', true, true],
    '|| on false' => ['$this->is_pinned', '$this->is_pinned || $this->x', false, null],
    'an unknown read before the decided one' => ['$this->is_pinned', '$this->x && $this->is_pinned', false, null],
    'another read' => ['$this->is_pinned', '$this->title === \'x\'', true, null],
    'a method call' => ['$this->is_pinned', '$this->isFeatured()', true, null],
    'a method on the case' => ['$this->status', '$this->status->label() === \'x\'', Status::Draft, null],
    'an operator outside the list' => ['$this->priority', '$this->priority->value > 1', Priority::High, null],
    'in_array() over the read' => ['$this->is_pinned', 'in_array(true, $this->is_pinned)', true, null],
    'in_array() with a spread' => ['$this->is_pinned', 'in_array(...$this->args())', true, null],
    'in_array() with a strict flag that is no bool' => ['$this->is_pinned', 'in_array($this->is_pinned, [1], 1)', true, null],
    'is_null() as a first-class callable' => ['$this->is_pinned', 'is_null(...)', true, null],
    'isset() of two, the first unset' => ['$this->priority', 'isset($this->priority, $this->x)', null, false],
    'isset() of two, the second unknown' => ['$this->priority', 'isset($this->priority, $this->x)', Priority::Low, null],
    'a constant nothing defines' => ['$this->is_pinned', '$this->is_pinned === NOT_DEFINED_ANYWHERE', true, null],
    'a constant alone' => ['$this->is_pinned', 'Status::Draft->value === 0', false, true],
]);

/**
 * Seeded guards over the read `$this->k`, each beside the value it sends: the read, its `->value` and `->name` when it
 * sends a backed case, an unknown read `$this->x`, and constants, joined by `!`, `&&`, `||`, `===` and `==`.
 *
 * @return list<array{string, mixed}>
 */
function conditionTruthCases(int $seed, int $cases): array
{
    $random = new Randomizer(new Xoshiro256StarStar($seed));
    $values = conditionTruthDomain();

    return array_map(static function () use ($random, $values): array {
        $value = $values[$random->getInt(0, count($values) - 1)];

        return [conditionTruthGuard($random, $value, $random->getInt(1, 3)), $value];
    }, range(1, $cases));
}

/**
 * The values a seeded read sends and a seeded constant names.
 *
 * @return list<mixed>
 */
function conditionTruthDomain(): array
{
    return [true, false, null, 0, 1, '0', '', Status::Draft];
}

/**
 * One seeded guard of at most $depth operators.
 */
function conditionTruthGuard(Randomizer $random, mixed $value, int $depth): string
{
    // The unknown read is one leaf in eight, or ten beside a backed case, so most guards decide, and some decide only
    // by short-circuiting past it.
    $leaf = static function () use ($random, $value): string {
        $leaves = ['$this->k', '$this->k', '$this->k', '$this->x', ...array_fill(0, 4, conditionTruthConstant($random))];

        if ($value instanceof BackedEnum) {
            array_push($leaves, '$this->k->value', '$this->k?->name');
        }

        return $leaves[$random->getInt(0, count($leaves) - 1)];
    };

    if ($depth === 0) {
        return $leaf();
    }

    $operand = static fn (): string => conditionTruthGuard($random, $value, $depth - 1);

    return match ($random->getInt(0, 5)) {
        0 => $leaf(),
        1 => '! ('.$operand().')',
        2 => '('.$operand().') && ('.$operand().')',
        3 => '('.$operand().') || ('.$operand().')',
        4 => '('.$operand().') === ('.$operand().')',
        default => '('.$operand().') == ('.$operand().')',
    };
}

/**
 * One seeded constant, spelled in PHP.
 */
function conditionTruthConstant(Randomizer $random): string
{
    $values = conditionTruthDomain();
    $value = $values[$random->getInt(0, count($values) - 1)];

    return $value instanceof UnitEnum ? '\\'.$value::class.'::'.$value->name : var_export($value, true);
}

/**
 * What PHP evaluates a guard as, with `$this->k` sending $k and `$this->x` sending $x.
 */
function conditionTruthByPhp(string $guard, mixed $k, mixed $x): bool
{
    $subject = new class
    {
        public mixed $k = null;

        public mixed $x = null;
    };
    $subject->k = $k;
    $subject->x = $x;

    return (bool) Closure::bind(eval('return fn (): mixed => '.$guard.';'), $subject, $subject)();
}

it('agrees with PHP over seeded guards', function (string $guard, mixed $value) {
    $decided = conditionTruthOf('$this->k', $guard, $value);

    expect($decided !== null || str_contains($guard, '$this->x'))->toBeTrue("{$guard} undecided");

    foreach ($decided === null ? [] : conditionTruthDomain() as $x) {
        expect($decided)->toBe(conditionTruthByPhp($guard, $value, $x), "{$guard} with \$this->x as ".var_export($x, true));
    }
})->with(fn (): array => conditionTruthCases(157, 300));

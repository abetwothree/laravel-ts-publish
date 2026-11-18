# Variants

A **discriminant** is a key of one array literal whose value is a plain read with a finite set of values, such
as `$this->is_pinned` or `$this->status`. A **dependent** is another key of that literal whose ternary, `match`,
`when()` or `unless()` the discriminant's read decides for every value, taking arms that differ between two
values. A **variant** is the set of values for which every dependent takes the same arm. A nested literal holding
a group prints one object per variant, and an API resource's top level prints `{ shared } & ( | … | … )`. The
groups are extra facts on [`MethodAnalysis::$variants`][`MethodAnalysis`]: every key's flat row is read as
before, and only the print changes. Users read
[API resources § Discriminated unions](https://tolki.abe.dev/ts/api-resources.html#discriminated-unions).

## Where things live

| Class | Owns |
| --- | --- |
| [`ReadPath`] | The variable or property chain a read names, compared step by step, never by printed text |
| [`DiscriminantValues`], [`DiscriminantValue`] | The finite values a row sends, read off its published type, each beside the literal it prints |
| [`ConditionTruth`] | Whether a guard holds for one value of one read |
| [`SplitsOnCondition`], [`ConditionalArms`], [`ConditionalArm`] | The arms `TernaryHandler`, `MatchHandler` and `ConditionalMethodHandler` hand over, each typed by the code that types the whole expression, through `ExpressionDispatcher::conditionalArms()` |
| [`VariantReader`] | A literal's groups, read once its fold is written |
| [`VariantGroup`], [`Variant`], [`LiteralVariants`] | One discriminant with its dependents and variants, and one literal's groups with its replayed objects |
| [`VariantUnions`] | A resource's top level, each group replaced by one variant row |
| [`VariantRows`] | The reserved row name, and a resource's rows split into shared keys and variants |
| [`ResourceDeclaration`] | How the `resource` and `globals` templates open and close a resource around its shared keys and arms |

## How a group is read

`ResourceAstAnalyzer::analyzeReturnArrayItems()` calls `VariantReader::read()` for every literal, top level and
nested, after `LiteralWrites::applyTo()` wrote the fold. The reader applies these rules:

1. **Discriminant.** A required key that one keyed item alone writes (`LiteralWrites::soleWriter()`), whose value
   is a plain read ([`ReadPath`]) or an enum read's `->value` or `->name`, and whose row has a finite set of values
   ([`DiscriminantValues`]). A wrapped enum has none. A scalar literal arm may stand for a case, so on a key that
   reads an enum, or under `AnalysisScope::$casesAsLiterals`, it leaves the row with none.
2. **Guards.** [`ConditionTruth`] decides a guard with PHP's own operators on the value the read sends. A guard that
   reads anything but that read and constants stays undecided.
3. **Arms.** A ternary with a middle operand, a `match`, and `$this->when()` or `unless()`, with or without a
   default, hand over arms. A selected arm whose body splits again is selected again, up to
   `VariantReader::LEVELS` deep, and a level that cannot decide keeps the arm above it. An arm that throws, a
   `match` with no arm for the value and a default closure Laravel cannot call leave that value out of the group,
   since PHP sends nothing for it.
4. **Dependents and variants.** A key depends on the discriminant when two sent values take arms that differ in
   type, presence or the classes behind their tokens (`VariantReader::armFingerprint()`). An `EnumResource` wrap
   and a direct read of one enum differ only where `enums.use_tolki_package` prints them apart. The first
   discriminant whose group forms owns its dependents. Values whose arms agree for every dependent make one
   variant, in first-value order.
5. **Declines.** See [below](#where-a-group-declines).
6. **Nested print.** `InlineObjectResult::of()` prints `LiteralVariants::objects()`: the fold replayed through
   `LiteralWrites::replayed()` once per variant, the discriminant sending its literal and each dependent its arm,
   unioned through `ValueResult::unionResults()`. That is the text, token order and import path a ternary of two
   literals already took. Two groups print their product, the first group outermost, up to
   `LiteralVariants::MOST_OBJECTS`. A group that would pass it stays flat in every object.
7. **Top-level print, resources only.** In `ResourceTransformer::runAstAnalysis()`, before the reconcile,
   [`VariantUnions`] replaces each group with one variant row while `publishesVariants()` holds: the class's own
   `#[TsResource(discriminated:)]`, which PHP never hands to a subclass, else `resources.discriminated_unions`.
   `splitVariants()` moves the row into `$variants` last, so a template published before variants existed never
   meets its reserved name ([`VariantRows`]). Such a template prints only the shared keys, so the resource and
   globals writers warn once of a template outside the package's views that never mentions `variants`
   ([`WarnsOfTemplatesWithoutVariants`]). A key an arm removes prints `key?: never`, so an unnarrowed read
   still compiles.
8. **Where the top level stays flat.** Only `ResourceTransformer` applies a group at the top level. Page props
   and shared data stay one object because Vue's `defineProps<T>()` needs an object type, a broadcast event keeps
   its payload's field list, and `AstEngine::analyze()` keeps `AnalysisResult`'s shape. A nested literal splits
   everywhere.
9. **Literals.** A value prints as `true`, `false`, `null` or its scalar through `JsEmitter::toJsLiteral()`, and
   an enum case as its alias's literal (`EnumValueTypes::caseLiterals()`), in `DiscriminantValues` order. A
   variant holding both booleans prints `boolean` (`Variant::literal()`).
10. **The flat reading is untouched.** Each grouped row resolves as it did before groups existed. The arm reads
    run inside `DroppedUnionArms::provisional(…, fn () => false)`, so no drop, warning or audit entry is recorded
    twice.

## Where a group declines

A declined group publishes the literal's flat rows, the type it published before groups existed.
`VariantReader` declines a literal with fewer than two keyed items, no item that splits, or an index signature,
and a group where:

- an arm a sent value takes is `unknown`, holds a [visible gap](ast-engine.md#visible-gaps), or left an arm out,
  since `DroppedUnionArms::dropped()` grew while it was read ([Dropped union arms](ast-engine.md#dropped-union-arms));
- an item from the group's first key to its last assigns, increments or decrements the discriminant's read, or a
  read it is a member of.

A group stays unprinted where its rows changed after the fold. Each group fingerprints its rows
(`VariantGroup::holdsFor()`), and a nested literal its whole fold (`LiteralVariants::holdsFor()`), so a `@return`
fill, a method `#[TsCasts]`, `MethodAnalysis::stripGaps()` or a rename voids it. A class or model `#[TsCasts]` on
a grouped key declines the group at the top level, through `VariantUnions::apply()`'s `$declinedKeys`.

## Where groups are carried and dropped

Groups are held on [`MethodAnalysis::$variants`][`MethodAnalysis`]. `replaceWith()` carries them, and so does
`BranchJoin::joinInto()` over one analysis. `merge()` and a join of two or more drop them, since no fold read the
whole. So return branches, a returned variable's walk, a `mergeWhen()` and a spread of a parent's `toArray()`
publish flat. `LiteralVariants` holds the fold's `LiteralWrites`, which holds the scope's reflection and AST
nodes, so never keep an analysis on a transformer, whose state `SnapshotsTransformerState` serializes.

## The read-back copy

A split nested literal's result also carries `unsplit`: the one object its rows print, honored only while the
result's `type` is still the union printed beside it (`ValueResult::isSplit()`). A reader that parses a printed
object back into keys reads it through `ValueResult::unsplit()` or `unsplitType()`, as `ObjectMembers`,
`ReadsCollectedItems`, `ResponseExtras`, `OffsetReadHandler`, `VariableHandler` and `InlineArrayHandler`'s spreads
do, since keys follow one object, not each variant. A row keeps `splitOf`, and a page's or shared data's prop is
`split` while `MethodAnalysis::isSplit()` holds for its row, so `DottedTopLevelKeys` writes a dotted key under it into
the whole union, `(A | B) & { extra }`. A new reader of printed keys goes through `unsplit()` too, or it reads the
union.

## Tests and gates

[`VariantReaderTest`](../../tests/Unit/Ast/Variants/VariantReaderTest.php),
[`LiteralVariantsTest`](../../tests/Unit/Ast/Variants/LiteralVariantsTest.php) and
[`ResourceVariantsTest`](../../tests/Unit/Transformers/ResourceVariantsTest.php) pin the rules.
[`DiscriminatedNarrowingTest`](../../tests/Feature/DiscriminatedNarrowingTest.php) compiles a narrowing read of
the probe resources with `tsc --strict`. The workbench fixtures live in
`workbench/app/Http/Resources/Discriminated/`, where each `*_kept` key of `DiscriminantFormsResource` is a
control that must stay flat. The regression and redundant-arm gates read a split resource's shared rows and arms
([Type inference gates](../testing/type-inference-gates.md#how-properties-are-keyed)).

[`ConditionTruth`]: ../../src/Ast/Variants/ConditionTruth.php
[`ConditionalArm`]: ../../src/Ast/Variants/ConditionalArm.php
[`ConditionalArms`]: ../../src/Ast/Variants/ConditionalArms.php
[`DiscriminantValue`]: ../../src/Ast/Variants/DiscriminantValue.php
[`DiscriminantValues`]: ../../src/Ast/Variants/DiscriminantValues.php
[`LiteralVariants`]: ../../src/Ast/Variants/LiteralVariants.php
[`MethodAnalysis`]: ../../src/Ast/Analysis/MethodAnalysis.php
[`ReadPath`]: ../../src/Ast/Parsing/ReadPath.php
[`ResourceDeclaration`]: ../../src/Support/ResourceDeclaration.php
[`SplitsOnCondition`]: ../../src/Ast/Contracts/SplitsOnCondition.php
[`Variant`]: ../../src/Ast/Variants/Variant.php
[`VariantGroup`]: ../../src/Ast/Variants/VariantGroup.php
[`VariantReader`]: ../../src/Ast/Variants/VariantReader.php
[`VariantRows`]: ../../src/Support/VariantRows.php
[`VariantUnions`]: ../../src/Ast/Variants/VariantUnions.php
[`WarnsOfTemplatesWithoutVariants`]: ../../src/Writers/Concerns/WarnsOfTemplatesWithoutVariants.php

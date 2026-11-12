<?php

declare(strict_types=1);

// Every entry is pinned on purpose and none of them is a published loss: each fixture's arm is deliberately
// untypable. A new entry is a real gap — type the arm instead of adding it here.

return [
    // Deliberate (GH-154): the parent's arm has no type, and BoundaryReadingsResource's `@return` types it.
    ['subject' => 'Workbench\\App\\Http\\Resources\\RowTagsResource', 'line' => 20, 'expression' => 'cache(\'k\')', 'site' => 'ternary-narrowed'],
    // Deliberate: the left arm is an undefined model attribute, as that fixture's own comment says.
    ['subject' => 'Workbench\\App\\Http\\Resources\\ClassConstantResource', 'line' => 43, 'expression' => '$this->totally_unmapped_field', 'site' => 'coalesce-left'],
    // Deliberate (GH-154): each arm is a value with no type that the resource's `@return` docblock types.
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredGapsResource', 'line' => 37, 'expression' => '$sheet->hint', 'site' => 'ternary-narrowed'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredGapsResource', 'line' => 41, 'expression' => '$sheet->hint', 'site' => 'ternary-narrowed'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredGapsResource', 'line' => 55, 'expression' => '$sheet->hint', 'site' => 'ternary-narrowed'],
    // Deliberate: each arm is the value the engine cannot type that the local's inline `@var` types instead, which is
    // the fixture's point. Where the arm leaves only `null`, its drop marks the assigned value's reading as vague.
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredPrecedenceResource', 'line' => 33, 'expression' => 'json_decode(\'"x"\')', 'site' => 'closure-union'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredPrecedenceResource', 'line' => 36, 'expression' => 'json_decode(\'"x"\')', 'site' => 'closure-union'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredPrecedenceResource', 'line' => 39, 'expression' => 'json_decode(\'"x"\')', 'site' => 'closure-union'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredPrecedenceResource', 'line' => 42, 'expression' => 'json_decode(\'"x"\')', 'site' => 'closure-union'],
    // Deliberate: arms the same fixture drops beside a reading that is not only `null`, which stands over the tag.
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredPrecedenceResource', 'line' => 87, 'expression' => 'json_decode(\'"x"\')', 'site' => 'closure-union'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredPrecedenceResource', 'line' => 92, 'expression' => 'json_decode(\'"x"\')', 'site' => 'closure-union'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredPrecedenceResource', 'line' => 98, 'expression' => 'json_decode(\'"x"\')', 'site' => 'closure-union'],
    // Deliberate (GH-154): each arm has no type in an undocumented helper whose caller's `@return` types it.
    ['subject' => 'Workbench\\App\\Http\\Resources\\HelperReadingsResource', 'line' => 34, 'expression' => 'cache(\'k\')', 'site' => 'coalesce-right'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\HelperReadingsResource', 'line' => 42, 'expression' => 'cache(\'k\')', 'site' => 'ternary-narrowed'],
    // Deliberate (GH-154): each is a join's branch writing a value with no type that the `@return` member types.
    ['subject' => 'Workbench\\App\\Http\\Resources\\JoinReadingsResource', 'line' => 35, 'expression' => 'cache(\'k\')', 'site' => 'binding-join'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\JoinReadingsResource', 'line' => 44, 'expression' => 'cache(\'k\')', 'site' => 'walk-branch-join'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\JoinReadingsResource', 'line' => 81, 'expression' => 'cache(\'k\')', 'site' => 'merge-branch-join'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\JoinReadingsResource', 'line' => 57, 'expression' => 'cache(\'k\')', 'site' => 'binding-join'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\JoinReadingsResource', 'line' => 67, 'expression' => 'cache(\'k\')', 'site' => 'binding-join'],
    // Deliberate: each default reads opaqueTotal(), untyped so that a merge call's join leaves that side out, as that
    // fixture's own comments say.
    ['subject' => 'Workbench\\App\\Http\\Resources\\MergeDefaultResource', 'line' => 63, 'expression' => '$this->opaqueTotal()', 'site' => 'merge-branch-join'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\MergeDefaultResource', 'line' => 66, 'expression' => '$this->opaqueTotal()', 'site' => 'merge-branch-join'],
    // Deliberate (GH-154): each arm has no type and leaves an empty list or a null the `@return` member types.
    ['subject' => 'Workbench\\App\\Http\\Resources\\PlaceholderReadingsResource', 'line' => 27, 'expression' => 'cache(\'k\')', 'site' => 'ternary-narrowed'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\PlaceholderReadingsResource', 'line' => 28, 'expression' => '$r->hint', 'site' => 'ternary-narrowed'],
    // Deliberate: moneyValue() returns OpaqueHandle, which the acceptor rejects because no published
    // file exists to import it from — the sibling key `money_value` publishes unknown for that reason.
    ['subject' => 'Workbench\\App\\Http\\Resources\\StaticCallResource', 'line' => 67, 'expression' => '\\Workbench\\App\\Services\\UrlService::moneyValue()', 'site' => 'coalesce-left'],
    // Deliberate: baseModel() declares the framework's Model, which no generated file exports.
    ['subject' => 'Workbench\\App\\Http\\Resources\\StaticCallResource', 'line' => 69, 'expression' => '\\Workbench\\App\\Services\\UrlService::baseModel()', 'site' => 'ternary-narrowed'],
    // Deliberate (GH-154): the accessor's arm has no type, and TaggedImageResource's `@return` types its key.
    ['subject' => 'Workbench\\App\\Models\\TaggedImage', 'line' => 21, 'expression' => 'cache(\'t\')', 'site' => 'closure-union'],
    // Deliberate: keys per recording site, each dropping `$this->opaqueValue()`, a method whose return type is
    // deliberately absent. These eleven stay permanently — they prove each site fires.
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 29, 'expression' => '$this->opaqueValue()', 'site' => 'closure-union'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 30, 'expression' => '$this->opaqueValue()', 'site' => 'closure-union'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 32, 'expression' => '$this->opaqueValue()', 'site' => 'ternary-narrowed'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 33, 'expression' => '$this->opaqueValue()', 'site' => 'data-get-default'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 34, 'expression' => '$this->opaqueValue()', 'site' => 'conditional-default'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 36, 'expression' => '$this->opaqueValue()', 'site' => 'match-arm'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 38, 'expression' => '$this->opaqueValue()', 'site' => 'coalesce-right'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 40, 'expression' => '$this->opaqueValue()', 'site' => 'binding-join'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 50, 'expression' => '$this->opaqueValue()', 'site' => 'binding-join'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 59, 'expression' => '$this->opaqueValue()', 'site' => 'walk-branch-join'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 67, 'expression' => '$this->opaqueValue()', 'site' => 'merge-branch-join'],
];

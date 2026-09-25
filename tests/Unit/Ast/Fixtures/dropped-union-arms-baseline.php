<?php

declare(strict_types=1);

// Every entry is pinned on purpose and none of them is a published loss. Two kinds live here: fixtures
// whose arm is deliberately untypable, and one incidental drop whose property the surviving operand
// still types. A new entry is a real gap — type the arm instead of adding it here.

return [
    // Deliberate: the left arm is an undefined model attribute, as that fixture's own comment says.
    ['subject' => 'Workbench\\App\\Http\\Resources\\ClassConstantResource', 'line' => 43, 'expression' => '$this->totally_unmapped_field', 'site' => 'coalesce-left'],
    // Incidental: `$this->status ?? OrderStatus::Pending` still publishes OrderStatusType from the left
    // operand. The dropped arm is an enum-case fetch, which no rule types yet — a candidate repair.
    ['subject' => 'Workbench\\App\\Http\\Resources\\CoalesceChannelResource', 'line' => 30, 'expression' => '\\Workbench\\App\\Enums\\OrderStatus::Pending', 'site' => 'coalesce-right'],
    // Deliberate: each arm is the value the engine cannot type that the local's inline `@var` types instead, which is
    // the fixture's point. Where the arm leaves only `null`, its drop marks the assigned value's reading as vague.
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredPrecedenceResource', 'line' => 33, 'expression' => 'json_decode(\'"x"\')', 'site' => 'closure-union'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredPrecedenceResource', 'line' => 36, 'expression' => 'json_decode(\'"x"\')', 'site' => 'closure-union'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredPrecedenceResource', 'line' => 39, 'expression' => 'json_decode(\'"x"\')', 'site' => 'closure-union'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredPrecedenceResource', 'line' => 42, 'expression' => 'json_decode(\'"x"\')', 'site' => 'closure-union'],
    // Deliberate: arms the same fixture drops beside a reading that is not only `null`, which stands over the tag.
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredPrecedenceResource', 'line' => 81, 'expression' => 'json_decode(\'"x"\')', 'site' => 'closure-union'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredPrecedenceResource', 'line' => 86, 'expression' => 'json_decode(\'"x"\')', 'site' => 'closure-union'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredPrecedenceResource', 'line' => 92, 'expression' => 'json_decode(\'"x"\')', 'site' => 'closure-union'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\DeclaredPrecedenceResource', 'line' => 96, 'expression' => '\\Workbench\\App\\Enums\\Status::Draft', 'site' => 'coalesce-right'],
    // Deliberate: moneyValue() returns OpaqueHandle, which the acceptor rejects because no published
    // file exists to import it from — the sibling key `money_value` publishes unknown for that reason.
    ['subject' => 'Workbench\\App\\Http\\Resources\\StaticCallResource', 'line' => 67, 'expression' => '\\Workbench\\App\\Services\\UrlService::moneyValue()', 'site' => 'coalesce-left'],
    // Deliberate: one key per recording site, each dropping `$this->opaqueValue()`, a method whose
    // return type is deliberately absent. These four stay permanently — they prove each site fires.
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 28, 'expression' => '$this->opaqueValue()', 'site' => 'closure-union'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 29, 'expression' => '$this->opaqueValue()', 'site' => 'closure-union'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 31, 'expression' => '$this->opaqueValue()', 'site' => 'ternary-narrowed'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 32, 'expression' => '$this->opaqueValue()', 'site' => 'data-get-default'],
];

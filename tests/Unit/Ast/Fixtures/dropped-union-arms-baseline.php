<?php

declare(strict_types=1);

// Every entry is UnionHonestyResource, the fixture that drops arms on purpose: one key per recording
// site, each dropping `$this->opaqueValue()`, a method whose return type is deliberately absent.
// These four stay permanently. Any other entry is a real gap — type that arm instead of pinning it here.

return [
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 28, 'expression' => '$this->opaqueValue()'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 29, 'expression' => '$this->opaqueValue()'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 31, 'expression' => '$this->opaqueValue()'],
    ['subject' => 'Workbench\\App\\Http\\Resources\\UnionHonestyResource', 'line' => 32, 'expression' => '$this->opaqueValue()'],
];

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Database\Eloquent\Model;

/** Casts two resource signatures under both spellings; each single-backslash one has a flag or import of its own. */
#[TsCasts([
    '[key: `${string}\\\\_v`]' => 'number',
    '[key: `${string}\\_v`]' => ['type' => 'boolean', 'optional' => true],
    '[key: `${string}\\\\_w`]' => 'string',
    '[key: `${string}\\_w`]' => ['type' => 'Money', 'import' => '@/types/money'],
])]
final class SignatureCastModel extends Model
{
    protected $table = 'posts';
}

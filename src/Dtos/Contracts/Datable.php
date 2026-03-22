<?php

namespace AbeTwoThree\LaravelTsPublish\Dtos\Contracts;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * @phpstan-type TypesImportMap = array<string, list<string>>
 * @phpstan-type ValuesImportMap = array<string, list<string>>
 *
 * @extends Arrayable<string, mixed>
 */
interface Datable extends Arrayable, Jsonable, JsonSerializable {}

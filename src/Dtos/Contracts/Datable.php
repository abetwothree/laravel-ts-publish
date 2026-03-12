<?php

namespace AbeTwoThree\LaravelTsPublish\Dtos\Contracts;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * @extends Arrayable<string, mixed>
 */
interface Datable extends Arrayable, Jsonable, JsonSerializable {}

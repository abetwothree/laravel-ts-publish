<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Laravel13Attributes;

/**
 * Declares no toArray() over a model whose table, `$hidden` column and appended accessor come from Laravel 13's
 * #[Table], #[Hidden] and #[Appends] attributes.
 *
 * @mixin Laravel13Attributes
 */
final class Laravel13AttributesSummaryResource extends JsonResource {}

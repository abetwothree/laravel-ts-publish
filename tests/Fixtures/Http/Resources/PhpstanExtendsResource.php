<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A resource that names its model only in `@phpstan-extends`.
 *
 * @phpstan-extends JsonResource<Post>
 */
class PhpstanExtendsResource extends JsonResource {}

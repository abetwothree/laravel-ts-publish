<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Services\UrlService;
use Workbench\App\ValueObjects\PostStats;

/** @mixin Post */
final class ReceiverProbeResource extends JsonResource
{
    /** Wraps a post, with a stats object the resource declares itself. */
    public function __construct(Post $resource, private readonly ?PostStats $stats = null)
    {
        parent::__construct($resource);
    }

    /** A subject-declared method, so `$this->urls()` resolves on the resource before the model. */
    public function urls(): UrlService
    {
        return new UrlService;
    }
}

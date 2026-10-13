<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\PublishChecks;

/** @mixin Comment */
final class HeldReceiversCommentResource extends JsonResource
{
    /** Wraps a comment, holding a post and an optional value object the comment model knows nothing of. */
    public function __construct(Comment $resource, private Post $other, private readonly ?PublishChecks $checks = null)
    {
        parent::__construct($resource);
    }
}

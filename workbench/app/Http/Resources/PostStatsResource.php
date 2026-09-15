<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\PostStats;

/**
 * A resource that carries a value next to its model through a promoted constructor property.
 *
 * @mixin Post
 */
final class PostStatsResource extends JsonResource
{
    /** @var list<string> */
    protected array $untypedChannels = ['email', 'sms'];

    /** Collides with Post::$title, a string column, so a published `number` proves D3 end to end. */
    protected int $title = 0;

    public function __construct(Post $resource, private readonly ?PostStats $stats = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'stats' => $this->stats,
            'views' => $this->stats?->views,
            'share_count' => $this->stats?->shares,
        ];
    }
}

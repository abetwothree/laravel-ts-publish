<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose helpers wrap the post's status with no declared return type, each body ending its own way,
 * beside one helper that declares its return type.
 *
 * @mixin Post
 */
class EnumResourceUntypedHelperResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['pinned_status' => $this->pinnedStatus()];
    }

    /**
     * The status as an enum resource for a pinned post; any other post runs off the end and gets null.
     */
    public function pinnedStatus()
    {
        if ($this->is_pinned) {
            return EnumResource::make($this->status);
        }
    }

    /**
     * The status as an enum resource for a pinned post; any other post gets null from a bare return.
     */
    public function pinnedStatusOrNothing()
    {
        if (! $this->is_pinned) {
            return;
        }

        return EnumResource::make($this->status);
    }

    /**
     * The status as an enum resource for a pinned post; any other post throws.
     */
    public function pinnedStatusOrFail()
    {
        if ($this->is_pinned) {
            return EnumResource::make($this->status);
        }

        throw new LogicException('Only a pinned post has a status here.');
    }

    /**
     * The status as an enum resource, with a note after the return.
     */
    public function notedStatus()
    {
        return EnumResource::make($this->status);
        // A note after the return, which the parser keeps as a statement of its own.
    }

    /**
     * The status as an enum resource, beside a callback whose bare return is its own.
     */
    public function statusBesideCallback()
    {
        $title = static function (Post $post) {
            if ($post->title === '') {
                return;
            }

            return $post->title;
        };

        return EnumResource::make($this->status);
    }

    /**
     * The status as an enum resource for a pinned post, else the draft case as one.
     */
    public function statusOrDraft()
    {
        if ($this->is_pinned) {
            return EnumResource::make($this->status);
        } else {
            return EnumResource::make(Status::Draft);
        }
    }

    /**
     * The status as an enum resource for a pinned post; for any other, PHP throws on the declared return type.
     */
    public function declaredPinnedStatus(): EnumResource
    {
        if ($this->is_pinned) {
            return EnumResource::make($this->status);
        }
    }
}

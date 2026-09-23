<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * `instanceof` tests that name a supertype, an interface or a sibling of what the subject already holds. The arm a
 * test proves reads the subject as what the test leaves of its own classes, so a wider test never widens it.
 *
 * @mixin Post
 */
final class NarrowedWiderTestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $post = $this->resource;
        $author = $this->resource->author;

        return [
            'negated_model_title' => ! $post instanceof Model ? null : $post->title,
            'negated_interface_title' => ! $post instanceof JsonSerializable ? null : $post->title,
            'negated_interface_email' => ! $author instanceof MustVerifyEmail ? null : $author->email,
            'negated_resource_title' => ! $this->resource instanceof Model ? null : $this->resource->title,
            'negated_model_author' => ! $post instanceof Model ? null : $post->author,
            'sibling_chain_title' => $post instanceof Post || $post instanceof Comment ? $post->title : null,
            'supertype_chain_title' => $post instanceof Post || $post instanceof Model ? $post->title : null,
            'interface_chain_email' => $author instanceof User || $author instanceof MustVerifyEmail ? $author->email : null,
            'negated_chain_email' => ! ($author instanceof User || $author instanceof MustVerifyEmail) ? null : $author->email,
            'positive_model_title' => $post instanceof Model ? $post->title : null,
        ];
    }
}

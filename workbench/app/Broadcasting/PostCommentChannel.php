<?php

declare(strict_types=1);

namespace Workbench\App\Broadcasting;

use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * Channel class demonstrating 2-model parameter authorization.
 *
 * Both {postId} and {commentId} wildcards are resolved via route model binding.
 * Laravel injects Post and Comment instances (where Comment belongs to Post).
 * The channel name 'post.{postId}.comment.{commentId}' drives the TypeScript
 * output — the join() parameter types are irrelevant to type generation.
 */
class PostCommentChannel
{
    /**
     * Create a new channel instance.
     */
    public function __construct() {}

    /**
     * Authenticate the user's access to the channel.
     *
     * @param  User  $user  The authenticated user.
     * @param  Post  $post  The post resolved via route model binding.
     * @param  Comment  $comment  The comment resolved via route model binding.
     */
    public function join(User $user, Post $post, Comment $comment): bool
    {
        return $user->id === $post->user_id || $user->id === $comment->user_id;
    }
}

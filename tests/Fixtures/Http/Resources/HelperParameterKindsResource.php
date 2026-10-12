<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\ValueObjects\PostStats;

/**
 * One helper per kind of declared parameter, each read spread and as a value, and a typed callable Laravel calls.
 *
 * @mixin Post
 */
final class HelperParameterKindsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->valueObject(new PostStats(1.0, 2)),
            'value_object' => $this->valueObject(new PostStats(1.0, 2)),
            ...$this->scalar(5),
            'scalar' => $this->scalar(5),
            ...$this->nullableModel($this->resource),
            'nullable_model' => $this->nullableModel($this->resource),
            ...$this->union($this->resource),
            'union' => $this->union($this->resource),
            ...$this->variadic($this->resource),
            'variadic' => $this->variadic($this->resource),
            ...$this->byReference($this->resource),
            'by_reference' => $this->byReference($this->resource),
            ...$this->defaulted(),
            'defaulted' => $this->defaulted(),
            ...$this->docblockList([$this->resource]),
            'docblock_list' => $this->docblockList([$this->resource]),
            ...$this->phpstanParam(['a']),
            'phpstan_param' => $this->phpstanParam(['a']),
            ...$this->untyped($this->resource),
            'untyped' => $this->untyped($this->resource),
            ...$this->written($this->resource),
            'written' => $this->written($this->resource),
            ...$this->request($request),
            ...$this->relabel($this->resource),
            'relabel' => $this->relabel($this->resource),
            'tr_typed' => $this->transform($this->title, $this->wrapTyped(...)),
            'has_typed' => $this->whenHas('title', $this->wrapTyped(...)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function valueObject(PostStats $stats): array
    {
        return ['vo_stats' => $stats, 'vo_views' => $stats->views];
    }

    /**
     * @return array<string, mixed>
     */
    private function scalar(int $count): array
    {
        return ['count' => $count];
    }

    /**
     * @return array<string, mixed>
     */
    private function nullableModel(?Post $post): array
    {
        return ['nm_title' => $post?->title, 'nm_post' => $post];
    }

    /**
     * @return array<string, mixed>
     */
    private function union(Post|User $owner): array
    {
        return ['un_id' => $owner->id, 'un_owner' => $owner];
    }

    /**
     * @return array<string, mixed>
     */
    private function variadic(Post ...$posts): array
    {
        return ['va_posts' => $posts];
    }

    /**
     * @return array<string, mixed>
     */
    private function byReference(Post &$post): array
    {
        return ['br_title' => $post->title];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaulted(int $limit = 5, ?Post $post = null): array
    {
        return ['df_limit' => $limit, 'df_title' => $post?->title];
    }

    /**
     * @param  list<Post>  $posts
     * @return array<string, mixed>
     */
    private function docblockList(array $posts): array
    {
        return ['dl_posts' => $posts];
    }

    /**
     * @param  array<mixed>  $tags
     *
     * @phpstan-param  list<string>  $tags
     *
     * @return array<string, mixed>
     */
    private function phpstanParam(array $tags): array
    {
        return ['pp_tags' => $tags];
    }

    /**
     * @return array<string, mixed>
     */
    private function untyped($post): array
    {
        return ['ut_title' => $post->title];
    }

    /**
     * @return array<string, mixed>
     */
    private function written(Post $post): array
    {
        $post = $post->author;

        return ['wr_name' => $post->name];
    }

    /**
     * @return array<string, mixed>
     */
    private function request(Request $request): array
    {
        return ['rq_has' => $request->has('x')];
    }

    /**
     * A post replaced by its author, read on both sides of the write.
     *
     * @return array<string, mixed>
     */
    private function relabel(Post $p): array
    {
        $p = $p->author;

        return ['rl_name' => $p->name, 'rl_title' => $p->title];
    }

    /**
     * The value a callable is called with, by its declared type.
     *
     * @return array<string, mixed>
     */
    private function wrapTyped(string $t): array
    {
        return ['t' => $t];
    }
}

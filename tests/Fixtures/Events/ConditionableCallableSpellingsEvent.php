<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ConditionableBox;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Traits\Conditionable;
use Workbench\App\Models\Post;

/** A test-only event whose payload hands Conditionable's when() a callable a join, a branch or a member holds. */
final class ConditionableCallableSpellingsEvent implements ShouldBroadcast
{
    use Conditionable;

    /** A static method's name, held by a constant. */
    private const string LABEL = 'AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ConditionableBox::label';

    /** A static method's name, held by a property. */
    public string $sizer = 'AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ConditionableBox::label';

    /**
     * A static method's name, or a pair a method writes.
     *
     * @var string|array{class-string, string}
     */
    public string|array $measure = 'AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ConditionableBox::label';

    public function __construct(public Post $post) {}

    /** A box's size, as a static method a pair names. */
    public static function sized(ConditionableBox $box, mixed $value): int
    {
        return 3;
    }

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('conditionable-callable-spellings');
    }

    /**
     * The payload: each key a callable a property, a key, a ternary, a constant, a method, a helper, a parameter or a
     * twice-written local holds.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $pinned = (bool) $this->post->is_pinned;
        $box = new ConditionableBox;
        $two = [$box, 'size'];

        if ($this->post->id > 5) {
            $two = [$box, 'size'];
        }

        $map = ['a' => [$box, 'size']];

        return [
            'two_write_pair' => $box->when($pinned, $two),
            'ternary_pair' => $box->when($pinned, $pinned ? [$box, 'size'] : [ConditionableBox::class, 'label']),
            'offset_pair' => $box->when($pinned, $map['a']),
            'property_string' => $box->when($pinned, $this->sizer),
            'written_property' => $box->when($pinned, $this->measure),
            'const_string' => $box->when($pinned, self::LABEL),
            'method_string' => $box->when($pinned, $this->labelName()),
            'helper_pair' => $box->when($pinned, $this->pairFor($box)),
            'param_string' => $this->viaParam($box, $pinned, 'AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ConditionableBox::label'),
        ];
    }

    /** Measure by the box's size. */
    public function measureSize(): void
    {
        $this->measure = [self::class, 'sized'];
    }

    /** A static method's name, as a literal every return spells. */
    private function labelName(): string
    {
        return 'AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ConditionableBox::label';
    }

    /**
     * A pair naming the box's size.
     *
     * @return array{ConditionableBox, string}
     */
    private function pairFor(ConditionableBox $box): array
    {
        return [$box, 'size'];
    }

    /** The box's when(), given its callable as a parameter. */
    private function viaParam(ConditionableBox $box, bool $pinned, string $callback): mixed
    {
        return $box->when($pinned, $callback);
    }
}

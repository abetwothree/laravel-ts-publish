<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;
use Workbench\App\Enums\Priority;
use Workbench\App\Models\Post;
use Workbench\App\Support\PinWriter;

/**
 * Literals whose discriminant a write changes between two of its reads, where the write check does not see it yet: a
 * by-reference argument, a callable a helper calls, a write before a key that drops a value, a call inside an arm, a
 * get hook, the model handed to a function, helpers that write two levels down or through the model, a closure a
 * helper calls, the model handed to another class's static method, and a local closure's callable a helper calls.
 * PHP can send a value no arm admits, so each literal should publish flat (GH-174).
 *
 * @mixin Post
 */
class HiddenWritesResource extends JsonResource
{
    public bool $pinned = true;

    public ?bool $maybe = null;

    public bool $flag {
        get {
            $this->flip = ! $this->flip;

            return $this->flip;
        }
    }

    private bool $flip = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $clear = $this->clearPin(...);

        return [
            'by_reference' => [
                'pinned' => $this->pinned,
                'reset' => $this->clearFlag($this->pinned),
                'detail' => $this->pinned ? $this->title : null,
            ],
            'by_reference_in_helper' => [
                'pinned' => $this->pinned,
                'reset' => $this->resetPinned(),
                'detail' => $this->pinned ? $this->title : null,
            ],
            'callable_in_when' => [
                'is_pinned' => $this->is_pinned,
                'cleared' => $this->when(true, $this->clearPin(...)),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'write_before_dropper' => [
                'maybe' => $this->maybe,
                'detail' => match ($this->maybe) {
                    true => 'title',
                    false => null,
                    default => 'unset',
                },
                'reset' => ($this->maybe = true) === true,
                'check' => match ($this->maybe) {
                    null => throw new LogicException('unset'),
                    default => 'ok',
                },
            ],
            'write_before_dropper_enum' => [
                'priority' => $this->priority,
                'detail' => match ($this->priority) {
                    Priority::Low => 'low',
                    Priority::Medium => null,
                    default => 'other',
                },
                'reset' => ($this->priority = Priority::Low) === Priority::Low,
                'check' => match ($this->priority) {
                    null => throw new LogicException('unset'),
                    default => 'ok',
                },
            ],
            'call_in_arm' => [
                'is_pinned' => $this->is_pinned,
                'changed' => $this->is_pinned ? $this->clearPinAndLabel() : null,
                'detail' => $this->is_pinned ? 'title' : null,
            ],
            'hooked' => [
                'flag' => $this->flag,
                'detail' => $this->flag ? 'on' : 0,
            ],
            'handed_model' => [
                'is_pinned' => $this->is_pinned,
                'reset' => data_set($this->resource, 'is_pinned', false) !== null,
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'two_helpers_down' => [
                'is_pinned' => $this->is_pinned,
                'reset' => $this->clearPinTwice(),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'helper_fills_model' => [
                'is_pinned' => $this->is_pinned,
                'reset' => $this->fillPin(),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'closure_in_when' => [
                'is_pinned' => $this->is_pinned,
                'cleared' => $this->when(true, function (): bool {
                    $this->resource->is_pinned = false;

                    return true;
                }),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'static_writer' => [
                'is_pinned' => $this->is_pinned,
                'reset' => PinWriter::unpin($this->resource),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
            'local_callable_in_when' => [
                'is_pinned' => $this->is_pinned,
                'cleared' => (bool) $this->when(true, $clear(...)),
                'detail' => $this->is_pinned ? $this->title : null,
            ],
        ];
    }

    /**
     * Clears the flag it is handed.
     */
    private function clearFlag(bool &$flag): bool
    {
        $flag = false;

        return true;
    }

    /**
     * Clears the resource's own pin through a by-reference argument.
     */
    private function resetPinned(): bool
    {
        return $this->clearFlag($this->pinned);
    }

    /**
     * Clears the pin on the model.
     */
    private function clearPin(): bool
    {
        $this->resource->is_pinned = false;

        return true;
    }

    /**
     * Clears the pin on the model and returns a label.
     */
    private function clearPinAndLabel(): string
    {
        $this->resource->is_pinned = false;

        return 'label';
    }

    /**
     * Clears the pin through another helper.
     */
    private function clearPinTwice(): bool
    {
        return $this->clearPin();
    }

    /**
     * Clears the pin through the model's own fill.
     */
    private function fillPin(): bool
    {
        $this->resource->forceFill(['is_pinned' => false]);

        return true;
    }
}

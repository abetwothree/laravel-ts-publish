<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\NamesakeInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource whose own methods shadow its model's namesakes with declarations too vague to publish.
 *
 * @mixin NamesakeInvoice
 */
final class NamesakeInvoiceResource extends JsonResource
{
    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total' => $this->total(),
            'reference' => $this->reference(),
            'static_total' => $this::total(),
            'model_total' => $this->resource->total(),
            'opaque' => $this->opaque(),
            'untyped_opaque' => $this->untypedOpaque(),
            'summary' => $this->summary(),
            'branchy' => $this->branchy(),
        ];
    }

    /** The total as a number, though the model's namesake formats it as a string. */
    public function total(): mixed
    {
        return (float) $this->resource->total;
    }

    /** Delegates to the model's string namesake. */
    public function reference(): mixed
    {
        return $this->resource->reference();
    }

    /** Reads a member nothing types. */
    public function opaque(): mixed
    {
        return $this->resource->getAttribute('not_a_column');
    }

    /** Reads a member nothing types, with no return type. */
    public function untypedOpaque()
    {
        return $this->resource->getAttribute('not_a_column');
    }

    /** A vague array delegating to the model's shape. */
    public function summary(): array
    {
        return $this->resource->summary();
    }

    /** A string or a number. */
    public function branchy(): mixed
    {
        if ((float) $this->resource->total > 100) {
            return 'large';
        }

        return 1.5;
    }
}

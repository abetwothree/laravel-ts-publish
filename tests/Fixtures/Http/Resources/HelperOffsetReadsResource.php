<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\WrapPicks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\MediaType;

/**
 * Reads one key of an untyped helper's array: a case's value or the kind's own, the CRM's status beside the app's,
 * and the status of whichever array a flag picks (GH-160).
 *
 * @property-read WrapPicks $resource
 */
final class HelperOffsetReadsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'kind' => $this->kinds()['k'],
            'crm' => $this->statuses()['crm'],
            'picked' => $this->picked()['s'],
        ];
    }

    /**
     * An image's value or the kind's own.
     */
    private function kinds()
    {
        return ['k' => $this->resource->c ? MediaType::Image->value : $this->resource->kind->value];
    }

    /**
     * The app's status beside the CRM's.
     */
    private function statuses()
    {
        return ['app' => $this->resource->status, 'crm' => $this->resource->crmStatus];
    }

    /**
     * The app's status or the CRM's, under one key.
     */
    private function picked()
    {
        return $this->resource->c ? ['s' => $this->resource->status] : ['s' => $this->resource->crmStatus];
    }
}

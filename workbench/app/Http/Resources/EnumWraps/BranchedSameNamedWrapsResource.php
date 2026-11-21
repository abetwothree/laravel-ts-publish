<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\EnumWraps;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\EnumWraps\EnumWrapBox;

/**
 * Branches writing a wrap or a read of the app's or the CRM's `Status` into one key (GH-160, GH-76). Each branch sends
 * its own enum, so the key keeps an arm for each, under its own alias.
 *
 * @property-read EnumWrapBox $resource
 */
class BranchedSameNamedWrapsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = ['written' => EnumResource::make($this->resource->status), 'read' => $this->resource->status];

        if ($this->resource->d) {
            $data['written'] = EnumResource::make($this->resource->crmStatus);
            $data['read'] = $this->resource->crmStatus;
        }

        if ($this->resource->c) {
            $data['nested'] = ['w' => EnumResource::make($this->resource->status)];
        } elseif ($this->resource->e) {
            $data['nested'] = ['w' => EnumResource::make($this->resource->status)];
        } else {
            $data['nested'] = ['w' => EnumResource::make($this->resource->crmStatus)];
        }

        return $data;
    }
}

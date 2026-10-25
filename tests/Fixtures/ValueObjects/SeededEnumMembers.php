<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * A helper that writes a key into the array it is passed and returns it, passed enums: two same-named ones side by
 * side, the pair with one a level down, and a wrapped one beside the same array returned alone.
 */
final class SeededEnumMembers
{
    public Status $status = Status::Draft;

    public CrmStatus $crmStatus = CrmStatus::Lead;

    /** @return array<string, mixed> */
    public function pair(): array
    {
        return $this->stamp(['a' => $this->status, 'b' => $this->crmStatus]);
    }

    /** @return array<string, mixed> */
    public function nestedPair(): array
    {
        return $this->stamp(['a' => $this->status, 'inner' => ['b' => $this->crmStatus]]);
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return ['pair' => $this->stamp(['a' => $this->status, 'b' => $this->crmStatus])];
    }

    /** @return array<string, mixed> */
    public function wrapped(): array
    {
        return ['status' => EnumResource::make($this->status)];
    }

    /** @return array<string, mixed> */
    public function seededWrapped(): array
    {
        return $this->stamp(['status' => EnumResource::make($this->status)]);
    }

    /**
     * Marks the array it is passed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stamp(array $data): array
    {
        $data['stamped'] = true;

        return $data;
    }
}

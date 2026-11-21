<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * A test-only resource whose helpers declare shapes alternating two same-named enums over an untyped read, so the
 * declared type alone says which token is whose (GH-81).
 */
final class DeclaredSameNamedEnumsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'shape' => $this->shape(),
            'nested' => $this->nested(),
            'rows' => $this->rows(),
            'nullable' => $this->nullable(),
            'either' => $this->either(),
            'listed' => $this->listed(),
        ];
    }

    /**
     * The app's, the CRM's, then the app's status again.
     *
     * @return array{a: Status, b: CrmStatus, c: Status|null}
     */
    private function shape(): array
    {
        return cache('shape');
    }

    /**
     * The pair a level down, the CRM's first, then the CRM's status.
     *
     * @return array{x: array{a: CrmStatus, b: Status}, c: CrmStatus}
     */
    private function nested(): array
    {
        return cache('nested');
    }

    /**
     * Rows alternating the two.
     *
     * @return list<array{a: Status, b: CrmStatus, c: Status}>
     */
    private function rows(): array
    {
        return cache('rows');
    }

    /**
     * The alternating shape, or null.
     *
     * @return array{a: Status, b: CrmStatus, c: Status}|null
     */
    private function nullable(): ?array
    {
        return cache('nullable');
    }

    /**
     * The CRM's status, or a shape holding the app's then the CRM's.
     *
     * @return CrmStatus|array{a: Status, b: CrmStatus}
     */
    private function either(): CrmStatus|array
    {
        return cache('either');
    }

    /**
     * Rows alternating the two as a member, then the CRM's status.
     *
     * @return array{rows: list<array{a: Status, b: CrmStatus, c: Status}>, d: CrmStatus}
     */
    private function listed(): array
    {
        return cache('listed');
    }
}

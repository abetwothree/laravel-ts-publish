<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\MailRecipient;
use Workbench\App\ValueObjects\RecipientList;

/**
 * Holds the `@var list<MailRecipient>` lists a callback declaring no parameter type maps: its own, the ones its
 * RecipientList declares, and those JsonResource forwards to it, beside an untyped helper a callable names (GH-154).
 *
 * @property-read RecipientList $resource
 */
final class RecipientElementsResource extends JsonResource
{
    /** @var array<string, MailRecipient> */
    public array $byEmail = [];

    /** @var MailRecipient[]|null */
    public ?array $backups = null;

    /** @var list<int> */
    public array $counts = [];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [];
    }

    /** The name of one recipient, read through a parameter declaring no type. */
    public function nameOf($recipient)
    {
        return $recipient->name;
    }
}

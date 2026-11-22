<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\Spreads;

use Workbench\App\Enums\Status;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Typed maps, users and a status the arm-beside-map probes spread beside resource and model arms (GH-121, GH-161).
 */
final class ArmShelf
{
    /** @var array<string, string> a string map */
    public array $labels = [];

    /** @var array<string, int> a number map */
    public array $counts = [];

    /** @var array<string, string>|null a string map, or null */
    public ?array $maybeLabels = null;

    /** @var array<string, int>|null a number map, or null */
    public ?array $maybeCounts = null;

    /** @var array<string, User> the app's users by key */
    public array $users = [];

    public ?User $user = null;

    public function __construct(
        public User $owner,
        public CrmUser $contact,
        public CrmUser $deputy,
        public string $title = '',
        public bool $flag = false,
        public Status $status = Status::Published,
    ) {}
}

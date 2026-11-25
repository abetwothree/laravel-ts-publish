<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Workbench\App\Enums\Grade;
use Workbench\App\Enums\MediaType;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Season;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;

/**
 * Fixture (GH-166): case literals beside the alias that admits them, and `never[]` beside a list, at every join; the
 * `*_kept` keys hold arms no sibling admits, but `wrap_and_read_kept`, whose alias takes its case value in (GH-160).
 */
final class RedundantArmsPayload
{
    public MediaType $kind = MediaType::Image;

    public ?MediaType $maybeKind = null;

    public Grade $grade = Grade::Pass;

    public Visibility $visibility = Visibility::Public;

    public Priority $priority = Priority::Low;

    public Status $status = Status::Draft;

    public string $method = 'get';

    public bool $c = false;

    /** @var list<Priority> */
    public array $modes = [];

    /** @var array<string, Priority> */
    public array $byName = [];

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $keyed = $this->c ? ['x' => $this->method] : null;

        $props = [
            'case_beside_alias' => $this->c ? MediaType::Image->value : $this->kind->value,
            'case_beside_nullable_alias' => $this->c ? MediaType::Image->value : $this->maybeKind?->value,
            'coalesced_case' => $this->maybeKind?->value ?? MediaType::Video->value,
            'matched_cases' => match ($this->method) {
                'a' => MediaType::Audio->value,
                'v' => MediaType::Video->value,
                default => $this->kind->value,
            },
            'backed_name' => $this->c ? Grade::Pass->name : $this->grade->name,
            'pure_name' => $this->c ? Visibility::Public->name : $this->visibility->name,
            'int_case' => $this->c ? Priority::Low->value : $this->priority->value,
            'nested' => ['inner' => $this->c ? MediaType::Image->value : $this->kind->value],
            'listed' => [$this->c ? MediaType::Image->value : $this->kind->value],
            'helper_case_beside_alias' => match ($this->method) {
                'a' => $this->draft(),
                default => $this->status->value,
            },
            'helper_case_value' => $this->draft()->value,
            'helper_case_name' => $this->draft()->name,
            'values_of_keyed' => array_values($keyed ?? []),
            'name_beside_value_alias_kept' => $this->c ? MediaType::Image->name : $this->kind->value,
            'other_enum_case_kept' => $this->c ? Season::Spring->value : $this->kind->value,
            'cases_without_alias_kept' => $this->c ? MediaType::Image->value : MediaType::Video->value,
            'wrap_beside_case_kept' => $this->c ? EnumResource::make($this->kind) : MediaType::Image->value,
            'wrap_and_read_kept' => [
                'w' => EnumResource::make($this->kind),
                'v' => $this->c ? MediaType::Image->value : $this->kind->value,
            ],
            'emptied_list' => $this->modes,
            'filled_list' => [],
            'nullable_list' => $this->c ? null : $this->modes,
            'record_beside_empty_kept' => $this->byName,
            'object_beside_empty_kept' => $this->c ? [] : ['a' => 1],
            'folded_case' => $this->kind->value,
        ];

        if ($this->c) {
            $props['emptied_list'] = [];
            $props['nullable_list'] = [];
            $props['record_beside_empty_kept'] = [];
            $props['folded_case'] = $this->method === 'get' ? MediaType::Image->value : MediaType::Video->value;
        } else {
            $props['filled_list'] = $this->modes;
        }

        return $props;
    }

    public function draft(): mixed
    {
        return Status::Draft;
    }
}

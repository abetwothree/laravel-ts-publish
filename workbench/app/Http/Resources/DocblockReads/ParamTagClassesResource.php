<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\DocblockReads;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use UnitEnum;
use Workbench\App\Enums\Status;
use Workbench\App\Support\Opaque;
use Workbench\App\ValueObjects\DocblockReads\HasLabel;
use Workbench\App\ValueObjects\DocblockReads\LooseBox;
use Workbench\App\ValueObjects\DocblockReads\PrivateMoney;
use Workbench\App\ValueObjects\DocblockReads\Shape;
use Workbench\App\ValueObjects\DocblockReads\Square;
use Workbench\App\ValueObjects\DocblockReads\TextLabel;

/**
 * A closure `@param` naming a class that is not a model, each beside its native twin (GH-134; plan task T2).
 */
class ParamTagClassesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $amounts = Opaque::value([new PrivateMoney(500)]);
        $labels = Opaque::value([new TextLabel('a')]);
        $statuses = Opaque::value([Status::Published]);
        $groups = Opaque::value([collect([1, 2])]);
        $shapes = Opaque::value([new Square(2.0)]);
        $boxes = Opaque::value([new LooseBox]);

        return [
            'private_amounts_by_param_tag' => collect($amounts)
                ->map(/** @param PrivateMoney $money */ fn ($money) => $money->formatted())
                ->all(),
            'private_amounts_by_native_type' => collect($amounts)
                ->map(fn (PrivateMoney $money) => $money->formatted())
                ->all(),
            'interface_labels_by_param_tag' => collect($labels)
                ->map(/** @param HasLabel $label */ fn ($label) => $label->label())
                ->all(),
            'interface_labels_by_native_type' => collect($labels)
                ->map(fn (HasLabel $label) => $label->label())
                ->all(),
            'backed_enum_values_by_param_tag' => collect($statuses)
                ->map(/** @param BackedEnum $status */ fn ($status) => $status->value)
                ->all(),
            'backed_enum_values_by_native_type' => collect($statuses)
                ->map(fn (BackedEnum $status) => $status->value)
                ->all(),
            'unit_enum_names_by_param_tag' => collect($statuses)
                ->map(/** @param UnitEnum $status */ fn ($status) => $status->name)
                ->all(),
            'unit_enum_names_by_native_type' => collect($statuses)
                ->map(fn (UnitEnum $status) => $status->name)
                ->all(),
            'collection_counts_by_param_tag' => collect($groups)
                ->map(/** @param Collection $group */ fn ($group) => $group->count())
                ->all(),
            'collection_counts_by_native_type' => collect($groups)
                ->map(fn (Collection $group) => $group->count())
                ->all(),
            'abstract_areas_by_param_tag' => collect($shapes)
                ->map(/** @param Shape $shape */ fn ($shape) => $shape->area())
                ->all(),
            'abstract_areas_by_native_type' => collect($shapes)
                ->map(fn (Shape $shape) => $shape->area())
                ->all(),
            'loose_labels_by_param_tag' => collect($boxes)
                ->map(/** @param LooseBox $box */ fn ($box) => $box->label())
                ->all(),
            'loose_labels_by_native_type' => collect($boxes)
                ->map(fn (LooseBox $box) => $box->label())
                ->all(),
        ];
    }
}

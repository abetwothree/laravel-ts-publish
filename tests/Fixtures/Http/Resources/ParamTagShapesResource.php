<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Support\Opaque;
use Workbench\App\ValueObjects\DocblockReads\HasLabel;
use Workbench\App\ValueObjects\DocblockReads\PrivateMoney;
use Workbench\App\ValueObjects\DocblockReads\Square;
use Workbench\App\ValueObjects\DocblockReads\TextLabel;

/**
 * A closure `@param` naming a class that is not a model, each beside its native twin, in the shapes
 * ParamTagClassesResource leaves out: a numeric method, a nullable interface, no `all()`, `array_map()`, a statement
 * closure, a shape, an enum's cases, options builders, a helper spread and a nested key.
 */
class ParamTagShapesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $amounts = Opaque::value([new PrivateMoney(500)]);
        $squares = Opaque::value([new Square(2.0)]);
        $labels = Opaque::value([new TextLabel('a')]);
        $cases = Opaque::value(Status::cases());
        $enum = Opaque::value(Status::class);

        return [
            'square_areas_by_param_tag' => collect($squares)
                ->map(/** @param Square $square */ fn ($square) => $square->area())
                ->all(),
            'square_areas_by_native_type' => collect($squares)
                ->map(fn (Square $square) => $square->area())
                ->all(),
            'nullable_labels_by_param_tag' => collect($labels)
                ->map(/** @param HasLabel|null $label */ fn ($label) => $label?->label())
                ->all(),
            'nullable_labels_by_native_type' => collect($labels)
                ->map(fn (?HasLabel $label) => $label?->label())
                ->all(),
            'amount_collection_by_param_tag' => collect($amounts)
                ->map(/** @param PrivateMoney $money */ fn ($money) => $money->formatted()),
            'amount_collection_by_native_type' => collect($amounts)
                ->map(fn (PrivateMoney $money) => $money->formatted()),
            'mapped_amounts_by_param_tag' => array_map(/** @param PrivateMoney $money */ fn ($money) => $money->formatted(), $amounts),
            'mapped_amounts_by_native_type' => array_map(fn (PrivateMoney $money) => $money->formatted(), $amounts),
            'statement_amounts_by_param_tag' => collect($amounts)
                ->map(/** @param PrivateMoney $money */ function ($money) {
                    return $money->formatted();
                })
                ->all(),
            'statement_amounts_by_native_type' => collect($amounts)
                ->map(function (PrivateMoney $money) {
                    return $money->formatted();
                })
                ->all(),
            'label_rows_by_param_tag' => collect($labels)
                ->map(/** @param HasLabel $label */ fn ($label) => ['label' => $label->label()])
                ->all(),
            'label_rows_by_native_type' => collect($labels)
                ->map(fn (HasLabel $label) => ['label' => $label->label()])
                ->all(),
            'case_rows_by_param_tag' => collect($cases)
                ->map(/** @param BackedEnum $case */ fn ($case) => ['value' => $case->value, 'name' => $case->name])
                ->all(),
            'case_rows_by_native_type' => collect($cases)
                ->map(fn (BackedEnum $case) => ['value' => $case->value, 'name' => $case->name])
                ->all(),
            'enum_options_by_param_tag' => collect($enum::cases())
                ->map(/** @param BackedEnum $case */ fn ($case) => ['value' => $case->value, 'name' => $case->name])
                ->all(),
            'enum_options_by_native_type' => collect($enum::cases())
                ->map(fn (BackedEnum $case) => ['value' => $case->value, 'name' => $case->name])
                ->all(),
            'enum_option_list_by_param_tag' => collect($enum::cases())
                ->map(/** @param BackedEnum $case */ fn ($case) => ['value' => $case->value, 'name' => $case->name])
                ->values()
                ->all(),
            'enum_option_list_by_native_type' => collect($enum::cases())
                ->map(fn (BackedEnum $case) => ['value' => $case->value, 'name' => $case->name])
                ->values()
                ->all(),
            ...$this->helperAmounts(),
            'nested_labels_by_param_tag' => [
                'labels' => collect($labels)->map(/** @param HasLabel $label */ fn ($label) => $label->label())->all(),
            ],
            'nested_labels_by_native_type' => [
                'labels' => collect($labels)->map(fn (HasLabel $label) => $label->label())->all(),
            ],
        ];
    }

    /**
     * The amounts, read by closures in a helper the resource spreads.
     *
     * @return array<string, mixed>
     */
    private function helperAmounts(): array
    {
        $amounts = Opaque::value([new PrivateMoney(500)]);

        return [
            'helper_amounts_by_param_tag' => collect($amounts)
                ->map(/** @param PrivateMoney $money */ fn ($money) => $money->formatted())
                ->all(),
            'helper_amounts_by_native_type' => collect($amounts)
                ->map(fn (PrivateMoney $money) => $money->formatted())
                ->all(),
        ];
    }
}

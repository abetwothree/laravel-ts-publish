<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Order;
use Workbench\App\Services\UrlService;

/**
 * Exercises static-call return type reflection and enum static args (Task 10).
 *
 * @mixin Order
 */
class StaticCallResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'url' => UrlService::generateUrl($this->resource),
            'status_badge' => EnumResource::make(Status::defaultCase()),
            'status_const' => EnumResource::make(Status::Published),
            'items' => OrderItemCollection::make($this->items),
            // Proves acceptReflectedTypeInfo() plumbs a directly-returned enum's FQCN.
            'default_status' => UrlService::defaultStatus(),
            // Proves acceptReflectedTypeInfo() plumbs a directly-returned Model's FQCN
            // through the modelFqcn slot, importing the model interface.
            'located_order' => UrlService::locateOrder(1),
            // new SomeCollection(...) — mirrors the ::make() case above via the same
            // collectedResourceClass() helper, from analyzeNewResource() this time.
            'new_items' => new OrderItemCollection($this->items),
            // Proves acceptReflectedTypeInfo() carries a #[TsType(import: ...)]-annotated
            // class return's import through the new customImports slot.
            'menu_settings' => UrlService::menuSettings(),
            // Proves acceptReflectedTypeInfo() plumbs a multi-enum union return through
            // embeddedEnumFqcns, since directEnumFqcn is a single slot.
            'status_or_priority' => UrlService::statusOrPriority(),
            // Proves acceptReflectedTypeInfo() degrades void/never/mixed returns instead
            // of emitting syntactically-valid-but-nonsense property types.
            'void_return' => UrlService::voidReturn(),
            'never_return' => UrlService::neverReturn(),
            'mixed_return' => UrlService::mixedReturn(),
            // Proves acceptReflectedTypeInfo() plumbs a model+enum union through both
            // embeddedModelFqcns and embeddedEnumFqcns off the same result.
            'order_or_status' => UrlService::orderOrStatus(),
            // Proves acceptReflectedTypeInfo() still rejects a non-Model class result:
            // no published file exists to import OpaqueHandle from.
            'money_value' => UrlService::moneyValue(),
            // Proves a ternary branch's customImports survive analyzeClosureUnion()'s merge
            // instead of being dropped alongside the other propagated FQCN channels. Uses a
            // #[TsType] class distinct from MenuSettings so the regression test can't pass by
            // riding on the plain `menu_settings` property's own contribution above.
            'page_meta_ternary' => $this->notes
                ? UrlService::pageMeta()
                : null,
            // Proves the *used* operand's customImports survive analyzeCoalesce()'s merge — the
            // left branch degrades to unknown and is discarded, so only the right branch's
            // import may end up in the emitted file. A third distinct #[TsType] class for the
            // same isolation reason as page_meta_ternary above.
            'widget_config_coalesce' => UrlService::moneyValue() ?? UrlService::widgetConfig(),
            // A static call naming a model no generated file exports declines, so only the published arm is left.
            'order_or_base' => $this->notes ? UrlService::locateOrder(1) : UrlService::baseModel(),
            // Proves methodOrDocblockReturnTypes() defers a vague `: array` signature to a precise
            // @return array{...} docblock shape instead of emitting unknown[] (Task 6).
            'autocomplete' => $this->resource->asAutoCompleteOption(),
            // Proves a docblock array{...} shape nested inside list<> resolves through the same
            // shape resolver instead of degrading to unknown[][] (Task 6).
            'summaries' => $this->resource->presetSummaries(),
        ];
    }
}

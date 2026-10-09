<?php

declare(strict_types=1);

namespace Workbench\App\Services;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Workbench\App\Casts\MenuSettings;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Order;
use Workbench\App\ValueObjects\OpaqueHandle;
use Workbench\App\ValueObjects\PageMeta;
use Workbench\App\ValueObjects\WidgetConfig;

/**
 * Exercises general static-call return type reflection (Task 10):
 * a declared scalar return type, a docblock-only return type (shadowed here by
 * the native `array` hint, since native types win over docblocks), a directly
 * returned enum, and a directly returned model.
 *
 * Also exercises acceptReflectedTypeInfo()'s import-dispatch paths: a directly
 * returned Model (`modelFqcn`), a #[TsType(import: ...)]-annotated class (its
 * import carried through `customImports`), a multi-enum union (`embeddedEnumFqcns`,
 * since `directEnumFqcn` is a single slot), a model+enum union (both channels at
 * once), void/never/mixed return types (still meaningless, so still rejected), and
 * a plain non-Model, non-importable class (still rejected — no published file to
 * import it from).
 */
class UrlService
{
    public static function generateUrl(Order $order): string
    {
        return '/orders/'.$order->getKey();
    }

    /** @return array{id: int, url: string} */
    public static function urlPayload(Order $order): array
    {
        return ['id' => (int) $order->getKey(), 'url' => self::generateUrl($order)];
    }

    public static function defaultStatus(): Status
    {
        return Status::Draft;
    }

    public static function locateOrder(int $id): Order
    {
        return Order::query()->findOrFail($id);
    }

    public static function menuSettings(): MenuSettings
    {
        return new MenuSettings;
    }

    public static function statusOrPriority(): Status|Priority
    {
        return Status::Draft;
    }

    public static function voidReturn(): void {}

    public static function neverReturn(): never
    {
        throw new RuntimeException('never returns');
    }

    public static function mixedReturn(): mixed
    {
        return null;
    }

    /**
     * A one-enum-plus-one-model union — classFqcns and enumFqcns are both non-empty for
     * this single TypeScriptTypeInfo. Proves both dispatch channels (embeddedModelFqcns and
     * embeddedEnumFqcns) fire off the same result instead of one guard shadowing the other.
     */
    public static function orderOrStatus(): Order|Status
    {
        return Status::Draft;
    }

    /**
     * A plain class with no Arrayable/JsonSerializable/__toString/#[TsType] — its classFqcns
     * entry is not a Model, so acceptReflectedTypeInfo() must still reject the whole result:
     * no published file exists to import OpaqueHandle from.
     */
    public static function moneyValue(): OpaqueHandle
    {
        return new OpaqueHandle('opaque');
    }

    /**
     * A #[TsType(import: ...)]-annotated class, reflected inside a ternary branch — proves
     * customImports survives analyzeClosureUnion()'s merge with the ternary's other branch.
     */
    public static function pageMeta(): PageMeta
    {
        return new PageMeta('meta');
    }

    /**
     * A #[TsType(import: ...)]-annotated class, reflected as the right-hand side of a `??` whose
     * left-hand side degrades to unknown — proves customImports survives analyzeCoalesce()'s
     * merge even though the left branch's result is discarded.
     */
    public static function widgetConfig(): WidgetConfig
    {
        return new WidgetConfig('default');
    }

    /**
     * Declared as the framework's base model, which no generated file exports, so a union arm calling it is left out.
     */
    public static function baseModel(): Model
    {
        return Order::query()->firstOrFail();
    }
}

<?php

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Workbench\App\Models\Order;

/**
 * Edge-case resource exercising unusual but valid patterns for AST analyzer guard clauses.
 *
 * @mixin Order
 */
class QuirkyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Bare string value (null key, not MethodCall) — tests null-key guard
            'bare_value',
            // Integer key — tests non-string key guard
            42 => $this->total,
            // $this->when() with 1 arg — tests analyzeWhen fallback
            'flag' => $this->when(true),
            // Non-mergeWhen key-less method call — tests analyzeMergeExpression guard
            $this->merge([
                'extra' => 'data',
            ]),
            // mergeWhen with 1 arg — tests mergeWhen fallback
            $this->mergeWhen(true),
            // mergeWhen with non-array 2nd arg — tests non-array guard
            $this->mergeWhen($this->is_active ?? false, fn () => ['dynamic' => 'value']),
            // mergeWhen with unusual array items — tests extractPropertiesFromArray guards
            $this->mergeWhen($this->paid_at !== null, [
                'bare_in_merge',
                42 => 'number_keyed',
                'normal_merge_key' => $this->total,
            ]),
            // Non-resource static call — tests analyzeStaticCall unknown class
            'formatted' => Str::upper($this->notes),
            // Resource::make with non-conditional arg — tests hasConditionalArgument no match
            'plain_user' => UserResource::make($this->user),
            // Resource::make with no args — tests hasConditionalArgument empty args
            'empty_user' => UserResource::make(),
            // EnumResource::make with no args — tests analyzeEnumResourceMake guard
            'empty_enum' => EnumResource::make(),
            // EnumResource::make as first-class callable — tests isFirstClassCallable guard
            'fcc_enum' => EnumResource::make(...),
            // EnumResource::make with non-enum property — tests analyzeEnumResourceMake non-enum fallback
            'not_enum' => EnumResource::make($this->total),
            // EnumResource::make with uncasted property — tests analyzeEnumResourceMake null-enum fallback
            'uncast_enum' => EnumResource::make($this->ip_address),
            // $this->property for nonexistent column — tests resolveModelAttributeTypeInfo attr not found
            'fake_field' => $this->nonexistent_column,
            // bare whenLoaded for nonexistent relation — tests resolveModelRelationTypeInfo not found
            'fake_relation' => $this->whenLoaded('nonexistent_relation'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Providers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Mixins\ResourceMacroMixin;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Mixins\TicketMacro;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\NinthViewCounter;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

/** Registers the resource macros the macro fixtures call, as an application's provider would in boot(). */
class ResourceMacroServiceProvider extends ServiceProvider
{
    /** Register every macro on JsonResource, which every resource shares. */
    public function boot(): void
    {
        $suffix = '!';

        JsonResource::macro('label', fn (): string => 'macro-label');
        JsonResource::macro('moment', fn (): Stringable => Str::of('2026'));
        JsonResource::macro('shout', function () {
            return strtoupper((string) $this->resource->title);
        });
        JsonResource::macro('typedShout', function (): string {
            return strtoupper((string) $this->resource->title);
        });
        JsonResource::mixin(new ResourceMacroMixin);
        JsonResource::macro('ticket', new TicketMacro);
        JsonResource::macro('codes', /** @return list<string> */ function () {
            return ['a', 'b'];
        });
        JsonResource::macro('arrowDoc', /** @return int */ fn () => 3);
        JsonResource::macro('excl', function () use ($suffix) {
            return $suffix;
        });
        JsonResource::macro('arrowExcl', fn () => $suffix);
        JsonResource::macro('again', function (int $depth = 0) {
            return $depth > 1 ? 'done' : $this->again($depth + 1);
        });
        JsonResource::macro('echoArg', function ($value) {
            return $value;
        });
        JsonResource::macro('heldRows', function () {
            return $this->resource;
        });
        JsonResource::macro('plevelMacro', function () {
            return $this->plevel;
        });
        $counter = new NinthViewCounter;
        $counter->bump();
        JsonResource::macro('ninthViews', [$counter, 'getViews']);
    }
}

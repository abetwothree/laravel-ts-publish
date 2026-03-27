<?php

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Http\Resources\Json\JsonResource;

#[TsExtends('ResourceRoutes', '@/types/resources')]
#[TsExtends('Pick<Routable, "store" | "update">', '@/types/routing', ['Routable'])]
class RoutableResource extends JsonResource {}

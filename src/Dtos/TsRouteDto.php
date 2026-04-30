<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Dtos;

use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

/**
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type RouteArgData = array{
 *     name: string,
 *     required: bool,
 *     _routeKey?: string,
 *     _enumValues?: list<string|int>,
 *     where?: string,
 * }
 * @phpstan-type RouteActionData = array{
 *     name: string|null,
 *     url: string|null,
 *     uri: non-falsy-string,
 *     domain: string|null,
 *     methods: list<string>,
 *     methodName: string,
 *     originalMethodName: string,
 *     description: string|null,
 *     args: list<RouteArgData>,
 *     shouldAnnotate: bool,
 *     component?: string|array<string, string>,
 *     pageType?: string|array<string, string>,
 *     pageTypeAnnotation?: string,
 * }
 * @phpstan-type RouteData = array{
 *     controllerName: string,
 *     filePath: string,
 *     fqcn: string,
 *     description: string|null,
 *     actions: list<RouteActionData>,
 *     typeImports: TypesImportMap,
 *     hasPageTypes: bool,
 * }
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class TsRouteDto implements Arrayable, Datable, Jsonable, JsonSerializable
{
    /**
     * @param  list<RouteActionData>  $actions
     * @param  TypesImportMap  $typeImports
     */
    public function __construct(
        public string $controllerName,
        public string $filePath,
        public string $fqcn,
        public ?string $description,
        public array $actions,
        public array $typeImports = [],
        public bool $hasPageTypes = false,
    ) {}

    /** @return RouteData */
    public function toArray(): array
    {
        return [
            'controllerName' => $this->controllerName,
            'filePath' => $this->filePath,
            'fqcn' => $this->fqcn,
            'description' => $this->description,
            'actions' => $this->actions,
            'typeImports' => $this->typeImports,
            'hasPageTypes' => $this->hasPageTypes,
        ];
    }

    public function toJson($options = 0): string
    {
        return (string) json_encode($this->toArray(), $options);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}

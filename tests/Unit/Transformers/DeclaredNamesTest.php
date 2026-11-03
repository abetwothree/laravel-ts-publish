<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Generators\BroadcastEventGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ClassTypeGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\EnumGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\FormRequestGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ModelMetadataGenerator;
use AbeTwoThree\LaravelTsPublish\Generators\ResourceGenerator;
use Workbench\App\Enums\Color;
use Workbench\App\Enums\Visibility;
use Workbench\App\Events\DeclaredPropsEvent;
use Workbench\App\Http\Requests\ArrayRulesRequest;
use Workbench\App\Http\Resources\AddressExtendsResource;
use Workbench\App\Models\Grade;
use Workbench\App\Models\User;
use Workbench\App\Packages\Ui\Chart;
use Workbench\Shipping\Enums\Status as ShipmentStatus;

dataset('publishers', [
    'a backed enum' => [EnumGenerator::class, Color::class, null],
    'an enum #[TsEnum] renames' => [EnumGenerator::class, ShipmentStatus::class, null],
    'a unit enum' => [EnumGenerator::class, Visibility::class, null],
    'a model named like the enum it casts, split template' => [ModelGenerator::class, Grade::class, null],
    'a model named like the enum it casts, full template' => [ModelGenerator::class, Grade::class, 'laravel-ts-publish::model-full'],
    'a model with mutators and relations, split template' => [ModelGenerator::class, User::class, null],
    'a model with mutators and relations, full template' => [ModelGenerator::class, User::class, 'laravel-ts-publish::model-full'],
    'a model\'s metadata companion' => [ModelMetadataGenerator::class, User::class, null],
    'a resource' => [ResourceGenerator::class, AddressExtendsResource::class, null],
    'a form request' => [FormRequestGenerator::class, ArrayRulesRequest::class, null],
    'a broadcast event' => [BroadcastEventGenerator::class, DeclaredPropsEvent::class, null],
    'a composed class' => [ClassTypeGenerator::class, Chart::class, null],
]);

test('every name a published file declares is one its transformer declares', function (string $generatorClass, string $class, ?string $template) {
    config()->set('ts-publish.output_to_files', false);

    if ($template !== null) {
        config()->set('ts-publish.models.template', $template);
    }

    $generator = new $generatorClass($class);
    preg_match_all('/^export (?:const|interface|type) (\w+)/m', $generator->content, $matches);

    expect($matches[1])->not->toBeEmpty()
        ->and(array_values(array_diff(array_unique($matches[1]), $generator->transformer->declaredNames())))->toBe([]);
})->with('publishers');

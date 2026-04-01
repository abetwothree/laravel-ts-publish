<?php

declare(strict_types=1);

use AbeTwoThree\LaravelTsPublish\Dtos\ModelInfo;

describe('ModelInfo', function () {
    beforeEach(function () {
        $this->modelInfo = new ModelInfo(
            class: 'App\\Models\\User',
            database: 'sqlite',
            table: 'users',
            policy: null,
            attributes: collect([['name' => 'id', 'type' => 'integer']]),
            relations: collect([]),
            events: collect([]),
            observers: collect([]),
            collection: 'Illuminate\\Database\\Eloquent\\Collection',
            builder: 'Illuminate\\Database\\Eloquent\\Builder',
            resource: null,
        );
    });

    test('toArray returns all properties', function () {
        $array = $this->modelInfo->toArray();

        expect($array)
            ->toHaveKey('class', 'App\\Models\\User')
            ->toHaveKey('database', 'sqlite')
            ->toHaveKey('table', 'users')
            ->toHaveKey('policy', null)
            ->toHaveKey('attributes')
            ->toHaveKey('relations')
            ->toHaveKey('events')
            ->toHaveKey('observers')
            ->toHaveKey('collection')
            ->toHaveKey('builder')
            ->toHaveKey('resource', null);
    });

    test('offsetExists returns true for valid properties', function () {
        expect(isset($this->modelInfo['class']))->toBeTrue()
            ->and(isset($this->modelInfo['table']))->toBeTrue();
    });

    test('offsetExists returns false for invalid properties', function () {
        expect(isset($this->modelInfo['nonexistent']))->toBeFalse();
    });

    test('offsetGet returns property values', function () {
        expect($this->modelInfo['class'])->toBe('App\\Models\\User')
            ->and($this->modelInfo['table'])->toBe('users');
    });

    test('offsetGet throws for invalid property', function () {
        expect(fn () => $this->modelInfo['nonexistent'])
            ->toThrow(InvalidArgumentException::class);
    });

    test('offsetSet throws LogicException', function () {
        expect(fn () => $this->modelInfo['class'] = 'Other')
            ->toThrow(LogicException::class);
    });

    test('offsetUnset throws LogicException', function () {
        expect(function () {
            unset($this->modelInfo['class']);
        })
            ->toThrow(LogicException::class);
    });
});

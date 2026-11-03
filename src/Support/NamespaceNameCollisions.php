<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

/**
 * Which names two classes publish in one namespace, where its barrel and the globals file can export only one of them.
 *
 * @phpstan-type Publisher = array{namespace: string, fqcn: class-string, names: list<string>}
 * @phpstan-type Collision = array{subject: class-string, name: string, other: class-string}
 * @phpstan-type NameOwners = array<string, array<string, class-string>>
 *
 * @internal
 */
final class NamespaceNameCollisions
{
    /**
     * Each name a class publishes that an earlier, different class already publishes in its namespace.
     *
     * @param  list<Publisher>  $publishers  In the order their names are claimed: the first to list a name owns it.
     * @return list<Collision>
     */
    public static function find(array $publishers): array
    {
        /** @var NameOwners $owners namespace => name => owning FQCN */
        $owners = [];
        $collisions = [];

        foreach ($publishers as $publisher) {
            foreach (array_unique($publisher['names']) as $name) {
                $owner = $owners[$publisher['namespace']][$name] ??= $publisher['fqcn'];

                if ($owner !== $publisher['fqcn']) {
                    $collisions[] = ['subject' => $publisher['fqcn'], 'name' => $name, 'other' => $owner];
                }
            }
        }

        return $collisions;
    }
}

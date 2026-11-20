<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassQueueGuard;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisResult;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\TypeScanner;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Address;
use Workbench\App\Models\User;
use Workbench\Crm\Enums\Status as CrmStatus;
use Workbench\Crm\Http\Resources\UserResource as CrmUserResource;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Reads the class each token of a published member names, through the result's imports: a name after `typeof` through
 * its value imports, any other through its type imports. The module table is the oracle's, written here by hand.
 *
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type ModuleNames = array<string, array<string, class-string>>
 */
final readonly class PublishedClasses
{
    /** The output path the guard publishes from, so every import path is relative to it. */
    public const string FROM = 'workbench/guard';

    /** @var ModuleNames */
    private const array TYPES = [
        'workbench/app/enums' => ['StatusType' => Status::class, 'RoleType' => Role::class],
        'workbench/crm/enums' => ['StatusType' => CrmStatus::class],
        'workbench/app/models' => ['User' => User::class, 'Address' => Address::class],
        'workbench/crm/models' => ['User' => CrmUser::class],
        'workbench/app/http/resources' => ['Address' => AddressResource::class, 'UserResource' => UserResource::class],
        'workbench/crm/http/resources' => ['UserResource' => CrmUserResource::class],
    ];

    /** @var ModuleNames */
    private const array CONSTS = [
        'workbench/app/enums' => ['Status' => Status::class, 'Role' => Role::class],
        'workbench/crm/enums' => ['Status' => CrmStatus::class],
    ];

    /** Names a member spells that are no class: TypeScript's keywords, and the wrapper a wrap prints. */
    private const array UNNAMED = [
        'null', 'undefined', 'string', 'number', 'boolean', 'unknown', 'never', 'any', 'object', 'true', 'false',
        'typeof', 'readonly', 'Record', 'AsEnum',
    ];

    /**
     * @param  array<string, string>  $types  Each local type name, with the class it imports or why it names none.
     * @param  array<string, string>  $consts  Each local value name, likewise.
     */
    private function __construct(
        private array $types,
        private array $consts,
    ) {}

    /**
     * The local names a result imports, each read to its class.
     */
    public static function of(AnalysisResult $result): self
    {
        return new self(
            self::locals($result->typeImports, self::TYPES),
            self::locals($result->valueImports, self::CONSTS),
        );
    }

    /**
     * The class each token of $member names, in print order; a token no import names reads as `unresolved <name>`.
     *
     * @return list<string>
     */
    public function in(string $member): array
    {
        $masked = TypeScanner::maskLiterals($member);
        preg_match_all('/(?<![\w$])[A-Za-z_$][\w$]*(?![\w$])(?!\s*\??:)/', $masked, $matches, PREG_OFFSET_CAPTURE);
        $classes = [];

        foreach ($matches[0] as [$name, $offset]) {
            if (in_array($name, self::UNNAMED, true)) {
                continue;
            }

            $names = preg_match('/typeof\s+$/', substr($masked, 0, $offset)) === 1 ? $this->consts : $this->types;
            $classes[] = $names[$name] ?? "unresolved {$name}";
        }

        return $classes;
    }

    /**
     * Each local name an import map brings, read through the module table.
     *
     * @param  TypesImportMap  $imports
     * @param  ModuleNames  $modules
     * @return array<string, string>
     */
    private static function locals(array $imports, array $modules): array
    {
        $locals = [];

        foreach ($imports as $path => $names) {
            $module = self::resolve($path);

            foreach ($names as $name) {
                [$imported, $local] = array_pad(explode(' as ', $name), 2, $name);
                $locals[$local] = $modules[$module][$imported] ?? "{$imported} from {$module}";
            }
        }

        return $locals;
    }

    /**
     * An import path read from FROM, as a path from the output root.
     */
    private static function resolve(string $path): string
    {
        $segments = explode('/', self::FROM);

        foreach (explode('/', $path) as $segment) {
            match ($segment) {
                '.', '' => null,
                '..' => array_pop($segments),
                default => $segments[] = $segment,
            };
        }

        return implode('/', $segments);
    }
}

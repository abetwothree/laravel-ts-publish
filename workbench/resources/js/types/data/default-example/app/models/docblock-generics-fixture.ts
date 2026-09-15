import type { User } from '.';

/**
 * Docblock-engine fixtures: every accessor's type lives only in its docblock.
 *
 * @see Workbench\App\Models\DocblockGenericsFixture
 */
export interface DocblockGenericsFixture
{
    id: number;
    created_at: string | null;
    updated_at: string | null;
}

export interface DocblockGenericsFixtureMutators
{
    flag_default: boolean | number | string | null;
    assigned_users: (User & { pivot: unknown })[];
    ability_map: Record<string, boolean>;
}

export interface DocblockGenericsFixtureAll extends DocblockGenericsFixture, DocblockGenericsFixtureMutators {}

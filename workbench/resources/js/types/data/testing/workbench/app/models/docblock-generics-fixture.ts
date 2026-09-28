import type { Comment, User } from '.';

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
    child_items: Comment[];
}

export interface DocblockGenericsFixtureRelations
{
    // Relations
    child_rows: Comment[];
    // Counts
    child_rows_count: number;
    // Exists
    child_rows_exists: boolean;
}

export interface DocblockGenericsFixtureAll extends DocblockGenericsFixture, DocblockGenericsFixtureMutators, DocblockGenericsFixtureRelations {}

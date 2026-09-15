/**
 * Docblock-engine fixtures: every accessor's type lives only in its docblock.
 *
 * @see Workbench\App\Models\DocblockGenericsFixture
 */
export interface DocblockGenericsFixture
{
    // Columns
    id: number;
    created_at: string | null;
    updated_at: string | null;
    // Mutators
    flag_default: boolean | number | string | null;
}

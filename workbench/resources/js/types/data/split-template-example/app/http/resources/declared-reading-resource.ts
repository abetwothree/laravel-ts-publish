import type { User } from '../../models';

/**
 * Each local's inline `@var` admits what its assignment already reads, so the reading stands: a declaration never
 * widens a value the engine types more precisely. The engine cannot read what `$opaque` holds, so its vaguer
 * declaration keeps the loaded relation's model, which it admits.
 *
 * @see Workbench\App\Http\Resources\DeclaredReadingResource
 */
export interface DeclaredReadingResource
{
    title: string;
    counts: { a: number; b: number };
    mixed: { a: number; b: string };
    shape: { a: number; b: string };
    id: number;
    comment_count: number;
    either: string;
    scalar: string;
    author: User;
    opaque_name?: string;
}

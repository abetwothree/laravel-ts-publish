import type { User } from '../../models';

/**
 * The engine reads each local's assigned value as a known type, so that reading stands over the inline `@var` and a
 * wider declaration never widens it. It cannot read what `$opaque` holds, so the declaration applies there, and inside
 * whenLoaded() the loaded relation's model, which is a `Model` too, types the member read.
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

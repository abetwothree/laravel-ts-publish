import type { StatusType } from '../../../enums';

/**
 * Literals whose keys follow a sibling key's value, read back as keys (GH-157): a helper that writes into one, a
 * collection, a spread and a key read. Each reads the literal as one object, as it did before the literal split; a
 * collection of a value that may be null, or either of two literals, holds one item shape per arm.
 *
 * @see Workbench\App\Http\Resources\Discriminated\ReadBackResource
 */
export interface ReadBackResource
{
    stamped: { pinned: boolean; v: string | null; at: number };
    stamped_enum: { status: StatusType; v: string | null; at: number };
    stamped_local: { pinned: boolean; v: string | null; at: number };
    collected: { pinned: boolean; v: string | null };
    collected_nullable: { pinned: true; v: string } | { pinned: false; v: null } | never[];
    collected_ternary: { pinned: true; v: string } | { pinned: false; v: null } | never[];
    collected_all: { pinned: true; v: string } | { pinned: false; v: null } | never[];
    collected_either: { a: number } | { b: string };
    spread: { pinned: boolean; v: string | null; x: number };
    read_pinned: boolean;
    read_v: string | null;
}

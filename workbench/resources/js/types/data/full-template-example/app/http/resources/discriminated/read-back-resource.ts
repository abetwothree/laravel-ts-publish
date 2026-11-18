import type { StatusType } from '../../../enums';

/**
 * Literals whose keys follow a sibling key's value, read back as keys (GH-157): a helper that writes into one, a
 * collection, a spread and a key read. Each reads the literal as one object, as it did before the literal split.
 *
 * @see Workbench\App\Http\Resources\Discriminated\ReadBackResource
 */
export interface ReadBackResource
{
    stamped: { pinned: boolean; v: string | null; at: number };
    stamped_enum: { status: StatusType; v: string | null; at: number };
    stamped_local: { pinned: boolean; v: string | null; at: number };
    collected: { pinned: boolean; v: string | null };
    spread: { pinned: boolean; v: string | null; x: number };
    read_pinned: boolean;
    read_v: string | null;
}

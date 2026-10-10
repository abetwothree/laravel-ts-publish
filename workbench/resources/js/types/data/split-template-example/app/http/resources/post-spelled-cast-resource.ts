import type { StatusType, VisibilityType } from '../../enums';

/**
 * Lays a cast that spells an enum's own type over each key that holds an enum resource: each key publishes its cast
 * and imports the enum types it spells, never the `AsEnum` wrap.
 *
 * @see Workbench\App\Http\Resources\PostSpelledCastResource
 */
export interface PostSpelledCastResource
{
    id: number;
    status: StatusType;
    visibility: VisibilityType;
    either: StatusType | VisibilityType | null;
    mixed: StatusType | null;
}

import type { PriorityType } from '../../../enums';

/**
 * A top-level literal whose discriminants a call inside an arm writes between two of their reads: `changed` clears
 * the pin before `detail` reads it, and the arm `level` takes for `null` sets the priority before `check`, which
 * throws for `null`, reads it. PHP can send a value no arm admits, so the literal publishes flat (GH-174).
 *
 * @see Workbench\App\Http\Resources\Discriminated\HiddenWritesTopResource
 */
export interface HiddenWritesTopResource
{
    is_pinned: boolean;
    changed: string | null;
    detail: string | null;
    priority: PriorityType | null;
    level: string | boolean | null;
    check: string;
}

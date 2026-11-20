import { type AsEnum } from '@tolki/ts';

import { Status as EnumsStatus, Visibility } from '../../../enums';
import type { StatusType } from '../../../../crm/enums';

/**
 * Enum resources inside literals that print one object per arm (GH-157): beside a group, as a group's arm, two in its
 * arms, beside a same-named enum read directly, and in a ternary of two literals. Each wrap keeps its value import.
 *
 * @see Workbench\App\Http\Resources\Discriminated\WrapArmsResource
 */
export interface WrapArmsResource
{
    beside_group: { pinned: true; until: string | null; status: AsEnum<typeof EnumsStatus> } | { pinned: false; until?: never; status: AsEnum<typeof EnumsStatus> };
    in_arm: { pinned: true; e: AsEnum<typeof EnumsStatus> } | { pinned: false; e: null };
    two_wraps: { pinned: true; e: AsEnum<typeof EnumsStatus> } | { pinned: false; e: AsEnum<typeof Visibility> | null };
    beside_same_named: { pinned: true; e: AsEnum<typeof EnumsStatus>; c: StatusType; v: number } | { pinned: false; e: AsEnum<typeof EnumsStatus>; c: StatusType; v: null };
    literals_ternary: { e: AsEnum<typeof EnumsStatus>; x: number } | { e: null; x: string };
}

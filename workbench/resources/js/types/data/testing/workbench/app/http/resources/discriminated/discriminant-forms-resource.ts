import { type AsEnum } from '@tolki/ts';

import { Status } from '../../../enums';
import type { PriorityType, StatusType, VisibilityType } from '../../../enums';

/**
 * Nested literals whose keys follow a sibling key's value, one condition form per key (GH-157). Each `*_kept` key is
 * a control that stays one object: its test reads no sibling with a finite set of values, or cannot be decided.
 *
 * @see Workbench\App\Http\Resources\Discriminated\DiscriminantFormsResource
 */
export interface DiscriminantFormsResource
{
    negated: { pinned: boolean; v: string | number | null };
    strict_true: { pinned: boolean; v: string | null };
    strict_false: { pinned: boolean; v: string | number | null };
    case_compare: { status: StatusType; v: string | null };
    case_not_equal: { status: StatusType; v: string | number };
    value_read: { status: StatusType; v: string | number | null };
    unit_enum_default: { visibility: VisibilityType | null; v: string | null };
    match_true: { status: StatusType; v: string | number | null };
    when_dependent: { pinned: boolean; until?: string | null };
    when_closure: { pinned: boolean; until?: number };
    unless_default: { pinned: boolean; v: string | number | null };
    two_dependents: { a: string | null; pinned: boolean; b: string | null };
    nested_ternary: { pinned: boolean; v: string | number | null };
    two_discriminants: { pinned: boolean; status: StatusType; a: string | null; b: string | number | null };
    throw_arm: { priority: PriorityType | null; v: string | number };
    shared_keys: { id: number; pinned: boolean; v: string | number; title: string };
    cast_discriminant_kept: { pinned: boolean; v: string | null };
    method_condition_kept: { featured: boolean; v: string | null };
    unpublished_condition_kept: { id: number; v: string | null };
    string_discriminant_kept: { title: string; v: number | null };
    wrapped_discriminant_kept: { status: AsEnum<typeof Status>; v: number | null };
    optional_discriminant_kept: { pinned?: boolean; v: string | null };
    undecidable_kept: { pinned: boolean; v: string | null };
    same_arms_kept: { pinned: boolean; v: number };
    untypable_arm_kept: { pinned: boolean; v: null };
}

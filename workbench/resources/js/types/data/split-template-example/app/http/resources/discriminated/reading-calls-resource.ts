import type { StatusType } from '../../../enums';
import type { UserResource } from '..';

/**
 * Literals whose items between a discriminant and its dependents only read: a method of the discriminant's own enum,
 * model methods the resource forwards, a relation check, a nested resource, a reading helper's callable, a key that
 * drops a value outside the group, and reading calls inside an arm. Nothing changes the discriminant, so each literal
 * should publish one object per variant (GH-174).
 *
 * @see Workbench\App\Http\Resources\Discriminated\ReadingCallsResource
 */
export interface ReadingCallsResource
{
    enum_method: { status: StatusType; color: string; detail: string | null };
    forwarded_key: { is_pinned: boolean; key: number; detail: string | null };
    forwarded_key_in_arm: { is_pinned: true; key: number; detail: string } | { is_pinned: false; key: null; detail: null };
    relation_check: { is_pinned: boolean; loaded: boolean; detail: string | null };
    forwarded_check_in_when: { is_pinned: boolean; author_title?: string; detail: string | null };
    nested_resource: { is_pinned: true; author?: UserResource; detail: string } | { is_pinned: false; author?: UserResource; detail: null };
    reading_callable_in_when: { is_pinned: true; label?: string; detail: string } | { is_pinned: false; label?: string; detail: null };
    dropper_outside_group: { id: number; priority: 0; detail: string; stage: string } | { id: number; priority: 1 | 2 | 3; detail: null; stage: string };
    reading_call_in_arm: { is_pinned: true; label: string; detail: string } | { is_pinned: false; label: null; detail: null };
}

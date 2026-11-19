import type { UserResource } from '..';

/**
 * Literals whose items between a discriminant and its dependents only read: a method of the discriminant's own enum,
 * model methods the resource forwards, a relation check, a nested resource, a reading helper's callable, a key that
 * drops a value outside the group, reading calls inside an arm, a helper that reads, the model handed to a URL, a Gate
 * check and a resource, built with `new` or make(), a static helper, a reading helper's callable handed to
 * array_map(), when() handed a method's result on another read, a list of class names, an untyped helper's result
 * handed on, and a callback holding an unused closure that returns `$this`. Nothing changes the discriminant, so
 * each literal should publish one object per variant (GH-174).
 *
 * @see Workbench\App\Http\Resources\Discriminated\ReadingCallsResource
 */
export interface ReadingCallsResource
{
    enum_method: { status: 0; color: string; detail: null } | { status: 1; color: string; detail: string };
    forwarded_key: { is_pinned: true; key: number; detail: string } | { is_pinned: false; key: number; detail: null };
    forwarded_key_in_arm: { is_pinned: true; key: number; detail: string } | { is_pinned: false; key: null; detail: null };
    relation_check: { is_pinned: true; loaded: boolean; detail: string } | { is_pinned: false; loaded: boolean; detail: null };
    forwarded_check_in_when: { is_pinned: true; author_title?: string; detail: string } | { is_pinned: false; author_title?: string; detail: null };
    nested_resource: { is_pinned: true; author?: UserResource; detail: string } | { is_pinned: false; author?: UserResource; detail: null };
    reading_callable_in_when: { is_pinned: true; label?: string; detail: string } | { is_pinned: false; label?: string; detail: null };
    dropper_outside_group: { id: number; priority: 0; detail: string; stage: string } | { id: number; priority: 1 | 2 | 3; detail: null; stage: string };
    reading_call_in_arm: { is_pinned: true; label: string; detail: string } | { is_pinned: false; label: null; detail: null };
    reading_helper: { is_pinned: true; created: string | null; detail: string } | { is_pinned: false; created: string | null; detail: null };
    url_of_model: { is_pinned: true; url: string; detail: string } | { is_pinned: false; url: string; detail: null };
    gate_on_model: { is_pinned: true; can_view: boolean; detail: string } | { is_pinned: false; can_view: boolean; detail: null };
    resource_of_model: { is_pinned: true; card: UserResource; detail: string } | { is_pinned: false; card: UserResource; detail: null };
    resource_of_model_make: { is_pinned: true; author: UserResource; detail: string } | { is_pinned: false; author: UserResource; detail: null };
    callable_to_map: { is_pinned: true; labels: string[]; detail: string } | { is_pinned: false; labels: string[]; detail: null };
    when_value_of_another_read: { is_pinned: true; created: string | null; detail: string } | { is_pinned: false; created?: never; detail: null };
    class_list_check: { is_pinned: true; is_post: boolean; detail: string } | { is_pinned: false; is_post: boolean; detail: null };
    untyped_helper_handed: { is_pinned: true; shout: string; detail: string } | { is_pinned: false; shout: string; detail: null };
    static_reader: { is_pinned: true; shout: string; detail: string } | { is_pinned: false; shout: string; detail: null };
    nested_closure_return: { is_pinned: boolean; ones: number[]; detail: string | null };
}

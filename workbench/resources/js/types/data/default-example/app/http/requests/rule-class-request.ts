/**
 * @see Workbench\App\Http\Requests\RuleClassRequest
 */
export interface RuleClassRequest {
    start_date: string;
    end_date?: string | null;
    username: string;
    roles: unknown[];
    invalid_roles: unknown[];
    avatar: File;
    email: string;
    order_status?: 0;
    membership_level?: string;
    visibility?: string;
    /** @metadata exclude-if conditional */
    role_id?: unknown;
    /** @metadata exclude-if conditional */
    team_id?: unknown;
    /** @constraint exists */
    state?: unknown;
    zones: 'first-zone' | 'second-zone';
    "airports.*"?: 'NYC' | 'LIT';
    toppings: string;
    /** @metadata prohibited-if conditional */
    role_id_prohibited?: unknown;
    /** @metadata prohibited-if conditional */
    role_id_callback?: unknown;
    /** @metadata prohibited-if conditional */
    role_id_prohibited_unless?: unknown;
    /** @metadata prohibited-if conditional */
    role_id_prohibited_unless_callback?: unknown;
    /** @metadata required-if conditional */
    role_id_required_if: unknown;
    /** @metadata required-if conditional */
    role_id_required_if_callback: unknown;
    /** @metadata required-if conditional */
    role_id_required_unless: unknown;
    /** @metadata required-if conditional */
    role_id_required_unless_callback: unknown;
    title: string;
    /** @constraint unique */
    email_unique: unknown;
    "addresses.*.id"?: unknown;
    photo: File;
    quantity: number;
    accent_color?: 'red' | 'blue';
    forbidden_color?: 'green' | 'blue' | 'amber' | 'gray' | 'purple';
}

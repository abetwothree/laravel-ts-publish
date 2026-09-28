import type { CurrencyType, OrderStatusType, PaymentMethodType, RoleType } from '../../enums';
import type { OrderItem, User } from '../../models';
import type { Store } from '../../models/admin';

/** @see Workbench\App\Http\Resources\SpreadWithGuardDoubleClosureReturnResource */
export interface SpreadWithGuardDoubleClosureReturnResource
{
    id: number;
    ulid: string;
    user_id: number;
    status: OrderStatusType;
    payment_method: PaymentMethodType | null;
    currency: CurrencyType;
    subtotal: number;
    tax: number;
    discount: number;
    total: number;
    shipping_address: { line_1: string; line_2?: string; city: string; state?: string; postal_code: string; country_code: string };
    billing_address: { line_1: string; line_2?: string; city: string; state?: string; postal_code: string; country_code: string };
    notes: string | null;
    placed_at: string | null;
    paid_at: string | null;
    shipped_at: string | null;
    delivered_at: string | null;
    cancelled_at: string | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    item_count: number;
    is_paid: boolean;
    formatted_total: string;
    flagged_notes: (string | null)[] | null;
    tracking_code: string | null;
    score_map: Record<string, number>;
    sorted_items: OrderItem[];
    keyed_items: Record<string, OrderItem>;
    listed_items: OrderItem[];
    unsorted_items: OrderItem[];
    state_ids: number[] | null;
    capabilities: { typeName: string; tracksSteelDetails: boolean; warehouseDocsKey: string | null } | null;
    summary_items: Store[];
    user: User;
    items: OrderItem[];
    customer?: { name: string; initials: string; email: string; phone: string | null; avatar: string | null; role: RoleType | null; is_premium: boolean } | { name: string; email: string; phone: string | null; avatar: string | null; role: RoleType | null; is_premium: boolean; name_titled: string; morph: string } | null;
}

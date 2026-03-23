import { type AsEnum } from '@tolki/enum';

import { InvoiceStatus } from '../../enums';
import type { User } from '../../../app/models';
import type { PaymentResource } from '.';

/** Exercises: when(cond, EnumResource::make) — conditional enum, cross-module whenLoaded bare (App\User), Resource::collection sibling, whenCounted, when(cond, value), mergeWhen. */
export interface InvoiceResource
{
    id: number;
    number: string;
    status?: AsEnum<typeof InvoiceStatus>;
    subtotal: number;
    tax: number;
    total: number;
    due_at: string | null;
    issued_at?: string | null;
    paid_at?: string | null;
    user?: User;
    payments?: PaymentResource[];
    payments_count?: number;
    notes?: string | null;
}

import { defineEnum } from '@tolki/enum';

export const OrderStatus = defineEnum({
    /** Order has been placed but not yet processed */
    Pending: 0,
    /** Order is being prepared */
    Processing: 1,
    /** Order has been shipped */
    Shipped: 2,
    /** Order has been delivered */
    Delivered: 3,
    /** Order was cancelled */
    Cancelled: 4,
    /** Order was refunded */
    Refunded: 5,
    /** Order is on hold */
    OnHold: 6,
    /** Human-readable label */
    label: {
        Pending: 'Pending',
        Processing: 'Processing',
        Shipped: 'Shipped',
        Delivered: 'Delivered',
        Cancelled: 'Cancelled',
        Refunded: 'Refunded',
        OnHold: 'On Hold',
    },
    /** Tailwind color class for the status badge */
    color: {
        Pending: 'yellow',
        Processing: 'blue',
        Shipped: 'indigo',
        Delivered: 'green',
        Cancelled: 'red',
        Refunded: 'gray',
        OnHold: 'orange',
    },
    /** Whether the order can still be cancelled */
    isCancellable: {
        Pending: true,
        Processing: true,
        Shipped: false,
        Delivered: false,
        Cancelled: false,
        Refunded: false,
        OnHold: true,
    },
    /** Get statuses that represent a completed order */
    completedStatuses: [3, 4, 5],
    /** Get statuses that represent an active order */
    activeStatuses: [0, 1, 2, 6],
    _cases: ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled', 'Refunded', 'OnHold'],
    _methods: ['label', 'color', 'isCancellable'],
    _static: ['completedStatuses', 'activeStatuses'],
} as const);

export type OrderStatusType = 0 | 1 | 2 | 3 | 4 | 5 | 6;

export type OrderStatusKind = 'Pending' | 'Processing' | 'Shipped' | 'Delivered' | 'Cancelled' | 'Refunded' | 'OnHold';

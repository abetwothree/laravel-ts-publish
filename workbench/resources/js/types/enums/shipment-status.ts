export const ShipmentStatus = {
    _cases: ['Pending', 'LabelCreated', 'PickedUp', 'InTransit', 'OutForDelivery', 'Delivered', 'Returned'],
    _methods: [],
    _static: [],
    Pending: 'pending',
    LabelCreated: 'label_created',
    PickedUp: 'picked_up',
    InTransit: 'in_transit',
    OutForDelivery: 'out_for_delivery',
    Delivered: 'delivered',
    Returned: 'returned',
} as const;

export type ShipmentStatusType = 'pending' | 'label_created' | 'picked_up' | 'in_transit' | 'out_for_delivery' | 'delivered' | 'returned';

export type ShipmentStatusKind = 'Pending' | 'LabelCreated' | 'PickedUp' | 'InTransit' | 'OutForDelivery' | 'Delivered' | 'Returned';

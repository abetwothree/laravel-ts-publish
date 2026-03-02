<?php

namespace Workbench\Shipping\Enums;

enum ShipmentStatus: string
{
    case Pending = 'pending';
    case LabelCreated = 'label_created';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Returned = 'returned';
}

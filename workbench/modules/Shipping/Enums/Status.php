<?php

declare(strict_types=1);

namespace Workbench\Shipping\Enums;

use AbeTwoThree\LaravelTsPublish\Attributes\TsEnum;

#[TsEnum('ShipmentStatus')]
enum Status: string
{
    case Pending = 'pending';
    case LabelCreated = 'label_created';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Returned = 'returned';
}

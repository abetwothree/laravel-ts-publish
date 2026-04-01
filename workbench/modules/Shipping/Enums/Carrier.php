<?php

declare(strict_types=1);

namespace Workbench\Shipping\Enums;

enum Carrier: string
{
    case Ups = 'ups';
    case FedEx = 'fedex';
    case Usps = 'usps';
    case Dhl = 'dhl';
}

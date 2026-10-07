<?php

declare(strict_types=1);

namespace App\Enums;

enum CommercialOfferStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';
}

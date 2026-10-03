<?php

declare(strict_types=1);

namespace App\Enums;

enum PartyRelationshipType: string
{
    case Client = 'client';
    case Prospect = 'prospect';
    case Supplier = 'supplier';
    case Subcontractor = 'subcontractor';
    case Partner = 'partner';
    case Owner = 'owner';
    case Lessee = 'lessee';
    case Employee = 'employee';
}

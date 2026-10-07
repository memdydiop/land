<?php

declare(strict_types=1);

namespace App\Enums\Finance;

enum PaymentType: string
{
    case Deposit = 'deposit';
    case InvoicePayment = 'invoice_payment';
    case Installment = 'installment';
    case Refund = 'refund';
    case Other = 'other';
}

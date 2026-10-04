<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Finance\InvoiceType;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'invoices';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'invoice_type' => InvoiceType::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'immutable_at' => 'datetime',
            'subtotal' => 'decimal:4',
            'tax' => 'decimal:4',
            'retention_amount' => 'decimal:4',
            'net_amount' => 'decimal:4',
            'total' => 'decimal:4',
            'numbering_year' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'client_party_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function workSituation(): BelongsTo
    {
        return $this->belongsTo(WorkSituation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }
}

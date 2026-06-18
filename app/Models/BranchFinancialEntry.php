<?php

namespace App\Models;

use App\Enums\BranchFinancialEntryStatus;
use App\Enums\BranchFinancialEntryType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchFinancialEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'entry_number',
        'creditor_branch_id',
        'debtor_branch_id',
        'amount',
        'entry_type',
        'status',
        'reference_type',
        'reference_id',
        'description',
        'notes',
        'created_by',
        'settled_at',
        'settled_by',
        'voided_at',
        'voided_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'entry_type' => BranchFinancialEntryType::class,
            'status' => BranchFinancialEntryStatus::class,
            'settled_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function creditorBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'creditor_branch_id');
    }

    public function debtorBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'debtor_branch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function settler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }
}

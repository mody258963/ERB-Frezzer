<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\SupplierInstallmentRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FrostpartsOverdueInstallments extends Command
{
    protected $signature = 'frostparts:overdue-installments';

    protected $description = 'Flag overdue unpaid supplier installments';

    public function handle(SupplierInstallmentRepositoryInterface $installments): int
    {
        $rows = $installments->overdue();
        Log::warning('Overdue installments', ['count' => $rows->count()]);

        return self::SUCCESS;
    }
}

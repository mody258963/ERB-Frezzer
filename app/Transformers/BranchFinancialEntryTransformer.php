<?php

namespace App\Transformers;

use App\Models\BranchFinancialEntry;
use App\Transformers\Concerns\TransformsBackedEnums;

final class BranchFinancialEntryTransformer
{
    use TransformsBackedEnums;

    /**
     * @return array<string, mixed>
     */
    public static function transform(BranchFinancialEntry $entry): array
    {
        $data = [
            'id' => $entry->id,
            'entry_number' => $entry->entry_number,
            'creditor_branch_id' => $entry->creditor_branch_id,
            'debtor_branch_id' => $entry->debtor_branch_id,
            'amount' => (float) $entry->amount,
            'entry_type' => self::enumValue($entry->entry_type),
            'status' => self::enumValue($entry->status),
            'reference_type' => $entry->reference_type,
            'reference_id' => $entry->reference_id,
            'description' => $entry->description,
            'notes' => $entry->notes,
            'created_by' => $entry->created_by,
            'settled_at' => $entry->settled_at?->toISOString(),
            'settled_by' => $entry->settled_by,
            'voided_at' => $entry->voided_at?->toISOString(),
            'voided_by' => $entry->voided_by,
            'created_at' => $entry->created_at?->toISOString(),
            'updated_at' => $entry->updated_at?->toISOString(),
        ];

        if ($entry->relationLoaded('creditorBranch') && $entry->creditorBranch) {
            $data['creditor_branch'] = BranchTransformer::transform($entry->creditorBranch);
        }

        if ($entry->relationLoaded('debtorBranch') && $entry->debtorBranch) {
            $data['debtor_branch'] = BranchTransformer::transform($entry->debtorBranch);
        }

        if ($entry->relationLoaded('creator') && $entry->creator) {
            $data['creator'] = UserTransformer::transform($entry->creator);
        }

        return $data;
    }
}

<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Enums\PartUnit;
use App\Models\Part;

trait ValidatesItemQuantities
{
    protected function validateItemQuantities($validator, string $itemsKey = 'items'): void
    {
        $validator->after(function ($validator) use ($itemsKey) {
            $items = $this->input($itemsKey, []);
            if (! is_array($items)) {
                return;
            }

            $partIds = collect($items)->pluck('part_id')->filter()->unique()->all();
            if ($partIds === []) {
                return;
            }

            $parts = Part::query()->whereIn('id', $partIds)->get()->keyBy('id');

            foreach ($items as $index => $item) {
                $partId = $item['part_id'] ?? null;
                $quantity = $item['quantity'] ?? null;

                if (! $partId || $quantity === null || ! isset($parts[$partId])) {
                    continue;
                }

                $unit = $parts[$partId]->unit;
                $field = "{$itemsKey}.{$index}.quantity";

                if (! is_numeric($quantity) || (float) $quantity <= 0) {
                    $validator->errors()->add($field, 'Quantity must be greater than zero.');

                    continue;
                }

                if ($unit instanceof PartUnit && $unit->allowsFractionalQuantity()) {
                    continue;
                }

                if ((float) $quantity != (int) $quantity) {
                    $validator->errors()->add($field, 'Quantity must be a whole number for this unit.');
                }
            }
        });
    }
}

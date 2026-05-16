<?php

namespace App\Transformers;

final class AuditActivityRowTransformer
{
    /**
     * @return array<string, mixed>
     */
    public static function transform(object $r): array
    {
        $decodeJson = static function (mixed $value): mixed {
            if ($value === null || $value === '') {
                return null;
            }
            if (is_array($value)) {
                return $value;
            }
            if (is_string($value)) {
                $decoded = json_decode($value, true);

                return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            }

            return $value;
        };

        return [
            'id' => $r->id ?? null,
            'user_id' => $r->user_id ?? null,
            'action' => $r->action ?? null,
            'entity_type' => $r->entity_type ?? null,
            'entity_id' => $r->entity_id ?? null,
            'old_value' => $decodeJson($r->old_value ?? null),
            'new_value' => $decodeJson($r->new_value ?? null),
            'ip_address' => $r->ip_address ?? null,
            'created_at' => isset($r->created_at) ? (string) $r->created_at : null,
        ];
    }
}

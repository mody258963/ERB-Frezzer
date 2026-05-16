<?php

namespace App\Transformers;

final class HealthTransformer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function transform(array $payload): array
    {
        return [
            'status' => $payload['status'] ?? 'ok',
            'service' => $payload['service'] ?? config('app.name'),
        ];
    }
}

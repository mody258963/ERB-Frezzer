<?php

namespace App\Transformers;

final class MessageTransformer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function transform(array $payload): array
    {
        return [
            'message' => $payload['message'] ?? null,
        ];
    }
}

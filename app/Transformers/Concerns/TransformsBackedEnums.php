<?php

namespace App\Transformers\Concerns;

trait TransformsBackedEnums
{
    protected static function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}

<?php

namespace App\Http\Controllers\Concerns;

trait ResolvesRepositoryModels
{
    protected function resolveOrFail(?object $model): object
    {
        abort_if($model === null, 404);

        return $model;
    }
}

<?php

namespace App\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository
{
    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    /**
     * Relations loaded by default on find / update refresh.
     *
     * @return list<string>
     */
    protected function defaultRelations(): array
    {
        return [];
    }

    protected function newQuery(): Builder
    {
        return $this->modelClass()::query();
    }

    protected function findById(string $id, array $with = []): ?Model
    {
        $relations = $with !== [] ? $with : $this->defaultRelations();

        $query = $this->newQuery();

        if ($relations !== []) {
            $query->with($relations);
        }

        return $query->find($id);
    }

    protected function findByIdOrFail(string $id, array $with = []): Model
    {
        $model = $this->findById($id, $with);

        if ($model === null) {
            abort(404);
        }

        return $model;
    }

    protected function createRecord(array $data): Model
    {
        return $this->newQuery()->create($data);
    }

    protected function updateRecord(Model $model, array $data, ?array $freshWith = null): Model
    {
        $model->update($data);

        $relations = $freshWith ?? $this->defaultRelations();

        return $relations !== [] ? $model->fresh($relations) : $model->fresh();
    }

    protected function saveRecord(Model $model): void
    {
        $model->save();
    }

    /**
     * @param  list<string>  $relations
     */
    protected function findByIdWith(string $id, array $relations): ?Model
    {
        return $this->newQuery()->with($relations)->find($id);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function createWithItems(array $parentData, array $items, string $relation = 'items'): Model
    {
        $model = $this->createRecord($parentData);

        foreach ($items as $item) {
            $model->{$relation}()->create($item);
        }

        return $model->load($relation);
    }

    protected function nextSequentialNumber(string $column, string $prefix, int $padLength = 4): string
    {
        $max = $this->newQuery()->max($column);
        $next = 1;

        if ($max !== null && preg_match('/(\d+)$/', (string) $max, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $next, $padLength, '0', STR_PAD_LEFT);
    }
}

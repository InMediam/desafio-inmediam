<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class AbstractRepository
{
    protected Model $model;

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function paginated(int $perPage = 15, string $orderBy = 'id', string $direction = 'desc'): LengthAwarePaginator
    {
        return $this->model->orderBy($orderBy, $direction)->paginate($perPage);
    }

    public function find(int $id, array $select = [], array $with = [], array $where = []): ?Model
    {
        $query = $this->model->with($with)->where($where);

        if (!empty($select)) {
            $query->select($select);
        }

        return $query->find($id);
    }

    public function findFirst(array $select = [], array $with = [], array $where = []): ?Model
    {
        $query = $this->model->with($with)->where($where);

        if (!empty($select)) {
            $query->select($select);
        }

        return $query->first();
    }

    public function beforeCreate(array $data): array
    {
        return $data;
    }

    public function afterCreate(Model $model): Model
    {
        return $model;
    }

    public function create(array $data): Model
    {
        $data = $this->beforeCreate($data);
        $model = $this->model->create($data);
        return $this->afterCreate($model);
    }

    public function beforeUpdate(array $data): array
    {
        return $data;
    }

    public function afterUpdate(Model $model): Model
    {
        return $model;
    }

    public function update(int $id, array $data): ?Model
    {
        $record = $this->find($id);     
        if ($record) {
            $data = $this->beforeUpdate($data);
            $record->update($data);
            return $this->afterUpdate($record);
        }
        return null;
    }

    public function delete(int $id): bool
    {
        $record = $this->find($id);

        if (!$record) {
            return false;
        }

        return (bool) $record->delete();
    }
}

<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

abstract class AbstractService
{
    protected mixed $repository;

    protected function validate(array $data, array $rules): void
    {
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public function all(): Collection
    {
        return $this->repository->all();
    }

    public function paginated(int $perPage = 15, string $orderBy = 'id', string $direction = 'desc'): LengthAwarePaginator
    {
        return $this->repository->paginated($perPage, $orderBy, $direction);
    }

    public function find(int $id, array $select = [], array $with = [], array $where = []): ?Model
    {
        return $this->repository->find($id, $select, $with, $where);
    }

    public function findFirst(array $select = [], array $with = [], array $where = []): ?Model
    {
        return $this->repository->findFirst($select, $with, $where);
    }

    public function create(array $data): Model
    {
        return $this->repository->create($data);
    }

    public function update(int $id, array $data): ?Model
    {
        return $this->repository->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }
}

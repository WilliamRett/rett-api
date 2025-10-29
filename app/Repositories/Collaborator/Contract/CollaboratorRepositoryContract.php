<?php

namespace App\Repositories\Collaborator\Contract;

use App\Models\Collaborator;
use Illuminate\Pagination\LengthAwarePaginator;

interface CollaboratorRepositoryContract
{
    public function list(int $userId, int $perPage = 15): LengthAwarePaginator;
    public function findById(int $userId, int $id): Collaborator;
    public function create(array $data): Collaborator;
    public function update(int $id, array $data): Collaborator;
    public function delete(int $userId, int $id): void;
    public function bulkInsert(array $rows): int;
    public function ownerId(int $id): ?int;
    public function deleteById(int $id): void;
    public function existsForUser(int $userId, int $id): bool;
}

<?php

namespace App\Services\Collaborator\Contract;

use App\Helper\ServiceResponse;

interface CollaboratorServiceContract
{
    public function list(int $userId, int $perPage = 15): ServiceResponse;
    public function show(int $userId, int $id): ServiceResponse;
    public function create(int $userId, array $data): ServiceResponse;
    public function update(int $userId, int $id, array $data): ServiceResponse;
    public function delete(int $userId, int $id): ServiceResponse;
    public function importFromCsv(int $userId, string $storagePath): ServiceResponse;
    public function importFromCsvForUser(int $userId, string $storagePath): ServiceResponse;
}

<?php

namespace App\Repositories\User\Contract;


interface UserRepositoryContract
{
    public function create(array $data): mixed;
    public function list(int  $limit, int $page): mixed;
    public function findById(int $userId): mixed;
}

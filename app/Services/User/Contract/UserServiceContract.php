<?php

namespace App\Services\User\Contract;

use App\Helper\ServiceResponse;
use App\Models\User;
use Illuminate\Http\Request;

interface UserServiceContract
{
    public function create(array $data): ServiceResponse;
    public function list(Request $request): mixed;
    public function findById(int $userId): ?User;

}

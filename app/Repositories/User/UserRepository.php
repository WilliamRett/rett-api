<?php

namespace App\Repositories\User;

use App\Models\User;
use Illuminate\Http\Request;
use App\Repositories\User\Contract\UserRepositoryContract;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Class UserRepository.
 */
class UserRepository implements UserRepositoryContract
{
    /**
     * @var User $user
     */
    protected User $user;

    /**
     * User Contructor
     *
     * @param User $userRepository
     */

    public function __construct(User $user)
    {
        $this->user = $user;
    }



    /**
     * Create User
     *
     * @param array $data
     *
     * @return mixed
     *
     */
    public function create(array $data): mixed
    {
        DB::beginTransaction();
        $user = $this->user->newQuery()->create($data);
        DB::commit();

        return $user;
    }

    /**
     * List Users
     *
     * @param int $limit
     * @param int $page
     *
     * @return mixed
     *
     */
    public function list(int  $limit, int $page): mixed
    {
        return $this->user::where('status', 'on')->where('office', 'adm')->paginate($limit, ['*'], 'page', $page);
    }

    /**
     * Find For User
     *
     * @param int $userId
     *
     * @return mixed
     *
     */
    public function findById(int $userId): mixed
    {
        return $this->user->find($userId);
    }

}

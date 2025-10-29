<?php

namespace App\Services\User;

use App\Helper\ServiceResponse;
use App\Models\User;
use App\Repositories\User\Contract\UserRepositoryContract;
use Illuminate\Http\Request;
use App\Services\User\Contract\UserServiceContract;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Class UserService.
 */
class UserService implements UserServiceContract
{
    /**
     * @var UserRepositoryContract $userRepository
     */
    protected UserRepositoryContract $userRepository;

    /**
     * UserRepository Contructor
     *
     * @param UserRepositoryContract $userRepository
     */
    public function __construct(UserRepositoryContract $userRepository)
    {
        $this->userRepository = $userRepository;
    }


    /**
     * @param Request $requestm
     * @param bool $adm
     *
     * @return ServiceResponse
     */
    public function create(array $data): ServiceResponse
    {
        try {
            $payload = [
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
            ];

            $user = $this->userRepository->create($payload);

            return ServiceResponse::success($user, 'Usuário criado', 201);
        } catch (Throwable $e) {
            report($e);
            return ServiceResponse::error('Falha ao criar usuário', 500);
        }
    }

    /**
     * @param Request $requestm
     * @param bool $adm
     *
     * @return mixed
     */
    public function list(Request $request): mixed
    {
        return $this->userRepository->list($request->limit, $request->page);
    }

    /**
     * @param int $userId
     * @param bool $adm
     *
     * @return mixed
     */
    public function findById(int $userId): ?User
    {
        return $this->userRepository->findById($userId);
    }

}

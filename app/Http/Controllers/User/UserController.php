<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Services\User\Contract\UserServiceContract;

 /**
  * @OA\Tag(
  *   name="Users",
  *   description="Operações de gerenciamento de usuários (gestores)."
  * )
  */
class UserController extends Controller
{
    public function __construct(private readonly UserServiceContract $service) {}

    /**
     * @OA\Post(
     *   path="/api/users",
     *   tags={"Users"},
     *   summary="Cria um novo usuário (gestor)",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name","email","password"},
     *       @OA\Property(property="name",     type="string", maxLength=150, example="Gestor"),
     *       @OA\Property(property="email",    type="string", format="email", example="gestor@example.com"),
     *       @OA\Property(property="password", type="string", format="password", minLength=6, example="secret")
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Created",
     *     @OA\JsonContent(
     *       @OA\Property(property="ok", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="User created"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id",    type="integer", example=1),
     *         @OA\Property(property="name",  type="string",  example="Gestor"),
     *         @OA\Property(property="email", type="string",  example="gestor@example.com")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Não autenticado (JWT ausente/expirado)"
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Dados inválidos",
     *     @OA\JsonContent(
     *       @OA\Property(property="ok", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="The given data was invalid."),
     *       @OA\Property(
     *         property="errors",
     *         type="object",
     *         example={"email": {"The email has already been taken."}}
     *       )
     *     )
     *   )
     * )
     *
     * Create user
     *
     * @param StoreUserRequest $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     */
    public function store(StoreUserRequest $request): mixed
    {
        return $this->service->create($request->validated())->toResponse();
    }
}

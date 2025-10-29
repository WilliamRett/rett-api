<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Info(
 *   title="Collaborators API",
 *   version="1.0.0",
 *   description="API REST com JWT (gestor -> colaboradores)."
 * )
 * @OA\Server(
 *   url=L5_SWAGGER_CONST_HOST,
 *   description="Servidor local"
 * )
 * @OA\SecurityScheme(
 *   securityScheme="bearerAuth",
 *   type="http",
 *   scheme="bearer",
 *   bearerFormat="JWT"
 * )
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected string $authGuard = 'api';

    protected function user(): ?User
    {
        return Auth::guard($this->authGuard)->user();
    }

    protected function userOrFail(): User
    {
        $user = $this->user();
        abort_if(!$user, 401, 'Unauthenticated');
        return $user;
    }

    protected function userId(): ?int
    {
        $id = Auth::guard($this->authGuard)->id();
        return $id ? (int) $id : null;
    }

    protected function userIdOrFail(): int
    {
        $id = $this->userId();
        abort_if(!$id, 401, 'Unauthenticated');
        return $id;
    }

    protected function perPage(Request $request, int $default = 15, int $max = 100): int
    {
        $pp = (int) $request->input('per_page', $default);
        if ($pp < 1) $pp = 1;
        if ($pp > $max) $pp = $max;
        return $pp;
    }
}

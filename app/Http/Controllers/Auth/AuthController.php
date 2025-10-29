<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *   name="Auth",
 *   description="Autenticação via JWT (login, perfil, logout, refresh)."
 * )
 */
class AuthController extends Controller
{
    public function __construct()
    {
        // login é público; demais rotas exigem token
        $this->middleware('auth:api')->except(['login']);
    }

    /**
     * @OA\Post(
     *   operationId="authLogin",
     *   path="/api/auth/login",
     *   tags={"Auth"},
     *   summary="Login com e-mail e senha",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email","password"},
     *       @OA\Property(property="email", type="string", format="email", example="gestor@example.com"),
     *       @OA\Property(property="password", type="string", example="secret")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       required={"access_token","token_type","expires_in"},
     *       @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGci..."),
     *       @OA\Property(property="token_type",   type="string", example="bearer"),
     *       @OA\Property(property="expires_in",   type="integer", example=3600)
     *     )
     *   ),
     *   @OA\Response(response=401, description="Credenciais inválidas"),
     *   @OA\Response(response=422, description="Dados inválidos")
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        $ttlSeconds = (int) config('jwt.ttl') * 60;

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => $ttlSeconds,
        ], 200);
    }

    /**
     * @OA@Get(
     *   operationId="authMe",
     *   path="/api/auth/me",
     *   tags={"Auth"},
     *   summary="Retorna o perfil do usuário autenticado",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="id", type="integer", example=1),
     *       @OA\Property(property="name", type="string", example="Gestor"),
     *       @OA\Property(property="email", type="string", format="email", example="gestor@example.com")
     *     )
     *   ),
     *   @OA\Response(response=401, description="Não autenticado")
     * )
     */
    public function me(): JsonResponse
    {
        return response()->json(Auth::guard('api')->user());
    }

    /**
     * @OA\Post(
     *   operationId="authLogout",
     *   path="/api/auth/logout",
     *   tags={"Auth"},
     *   summary="Efetua logout (invalida o token atual)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Deslogado com sucesso")
     *     )
     *   ),
     *   @OA\Response(response=401, description="Não autenticado")
     * )
     */
    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();
        return response()->json(['message' => 'Deslogado com sucesso']);
    }

    /**
     * @OA\Post(
     *   operationId="authRefresh",
     *   path="/api/auth/refresh",
     *   tags={"Auth"},
     *   summary="Atualiza o token JWT",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       required={"access_token","token_type","expires_in"},
     *       @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGci..."),
     *       @OA\Property(property="token_type",   type="string", example="bearer"),
     *       @OA\Property(property="expires_in",   type="integer", example=3600)
     *     )
     *   ),
     *   @OA\Response(response=401, description="Não autenticado")
     * )
     */
    public function refresh(): JsonResponse
    {
        $newToken   = JWTAuth::parseToken()->refresh();
        $ttlSeconds = (int) config('jwt.ttl') * 60;

        return response()->json([
            'access_token' => $newToken,
            'token_type'   => 'bearer',
            'expires_in'   => $ttlSeconds,
        ], 200);
    }
}

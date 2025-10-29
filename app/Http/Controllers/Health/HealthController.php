<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * @OA\Get(
     *   path="/",
     *   tags={"Health"},
     *   summary="Health check",
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="ok", type="boolean", example=true),
     *       @OA\Property(property="app", type="string", example="Laravel"),
     *       @OA\Property(property="env", type="string", example="local"),
     *       @OA\Property(property="time", type="string", example="2025-10-28T22:07:00-03:00"),
     *       @OA\Property(property="uptime", type="string", nullable=true, example="12345.67 67890.12")
     *     )
     *   )
     * )
     */
    public function index(): JsonResponse
    {
        $uptime = null;
        if (@file_exists('/proc/uptime')) {
            $uptime = trim(@file_get_contents('/proc/uptime'));
        }

        return response()->json([
            'ok'     => true,
            'app'    => config('app.name'),
            'env'    => config('app.env'),
            'time'   => now()->toISOString(),
            'uptime' => $uptime,
        ], 200);
    }
}

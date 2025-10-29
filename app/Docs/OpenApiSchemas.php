<?php
/**
 *
 * @OA\Info(title="Rett API", version="1.0.0")
 *
 * @OA\Server(
 *   url=L5_SWAGGER_CONST_HOST,
 *   description="Local"
 *  )
 *
 * @OA\Schema(
 *   schema="ServiceResponseOk",
 *   @OA\Property(property="ok", type="boolean", example=true),
 *   @OA\Property(property="message", type="string", nullable=true, example="Operação realizada"),
 *   @OA\Property(property="data", type="object")
 * )
 *
 * @OA\Schema(
 *   schema="Collaborator",
 *   required={"id","user_id","name","email","cpf","city","state"},
 *   @OA\Property(property="id", type="integer", example=11),
 *   @OA\Property(property="user_id", type="integer", example=1),
 *   @OA\Property(property="name", type="string", example="Ana Silva"),
 *   @OA\Property(property="email", type="string", example="ana@ex.com"),
 *   @OA\Property(property="cpf", type="string", example="12345678901"),
 *   @OA\Property(property="city", type="string", example="São Paulo"),
 *   @OA\Property(property="state", type="string", example="SP"),
 *   @OA\Property(property="phone", type="string", nullable=true, example="11999990000")
 * )
 *
 * @OA\Schema(
 *   schema="PaginatedCollaborators",
 *   @OA\Property(property="current_page", type="integer", example=1),
 *   @OA\Property(
 *     property="data",
 *     type="array",
 *     @OA\Items(ref="#/components/schemas/Collaborator")
 *   ),
 *   @OA\Property(property="per_page", type="integer", example=15),
 *   @OA\Property(property="total", type="integer", example=42)
 * )
 */

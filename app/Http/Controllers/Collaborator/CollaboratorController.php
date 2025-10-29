<?php

namespace App\Http\Controllers\Collaborator;

use App\Http\Controllers\Controller as ControllersController;
use App\Http\Requests\Collaborator\StoreCollaboratorRequest;
use App\Http\Requests\Collaborator\UpdateCollaboratorPatchRequest;
use App\Http\Requests\Collaborator\UpdateCollaboratorRequest;
use App\Jobs\ProcessCollaboratorsCsv;
use App\Services\Collaborator\Contract\CollaboratorServiceContract;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *   name="Collaborators",
 *   description="Operações de CRUD e importação em massa de colaboradores (escopo por gestor autenticado)."
 * )
 */
class CollaboratorController extends ControllersController
{
    public function __construct(private readonly CollaboratorServiceContract $service) {}

    /**
     * @OA\Get(
     *   path="/api/collaborators",
     *   tags={"Collaborators"},
     *   summary="Lista colaboradores do gestor autenticado (paginado)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", minimum=1), example=1),
     *   @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100), example=15),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="ok", type="boolean", example=true),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="current_page", type="integer", example=1),
     *         @OA\Property(
     *           property="data",
     *           type="array",
     *           @OA\Items(
     *             @OA\Property(property="id", type="integer", example=11),
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Ana Silva"),
     *             @OA\Property(property="email", type="string", example="ana@ex.com"),
     *             @OA\Property(property="cpf", type="string", example="12345678901"),
     *             @OA\Property(property="city", type="string", example="São Paulo"),
     *             @OA\Property(property="state", type="string", example="São Paulo"),
     *             @OA\Property(property="phone", type="string", example="11999990000"),
     *             @OA\Property(property="created_at", type="string", example="2025-10-28T12:00:00Z"),
     *             @OA\Property(property="updated_at", type="string", example="2025-10-28T12:00:00Z")
     *           )
     *         ),
     *         @OA\Property(property="per_page", type="integer", example=15),
     *         @OA\Property(property="total", type="integer", example=42)
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Não autenticado")
     * )
     */
    public function list(Request $request)
    {
        $userId  = $this->userIdOrFail();
        $perPage = $this->perPage($request, 15);

        return $this->service->list($userId, $perPage)->toResponse();
    }

    /**
     * @OA\Get(
     *   path="/api/collaborators/{id}",
     *   tags={"Collaborators"},
     *   summary="Detalha um colaborador do gestor autenticado",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="ok", type="boolean", example=true),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=11),
     *         @OA\Property(property="user_id", type="integer", example=1),
     *         @OA\Property(property="name", type="string", example="Ana Silva"),
     *         @OA\Property(property="email", type="string", example="ana@ex.com"),
     *         @OA\Property(property="cpf", type="string", example="12345678901"),
     *         @OA\Property(property="city", type="string", example="São Paulo"),
     *         @OA\Property(property="state", type="string", example="São Paulo"),
     *         @OA\Property(property="phone", type="string", example="11999990000")
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Não autenticado"),
     *   @OA\Response(response=404, description="Não encontrado (pertence a outro gestor ou não existe)")
     * )
     */
    public function show(int $id)
    {
        return $this->service
            ->show($this->userIdOrFail(), (int) $id)
            ->toResponse();
    }

    /**
     * @OA\Post(
     *   path="/api/collaborators",
     *   tags={"Collaborators"},
     *   summary="Cria colaborador para o gestor autenticado",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name","email","cpf","city","state"},
     *       @OA\Property(property="name",  type="string", example="Ana Silva"),
     *       @OA\Property(property="email", type="string", format="email", example="ana@ex.com"),
     *       @OA\Property(property="cpf",   type="string", example="12345678901"),
     *       @OA\Property(property="city",  type="string", example="São Paulo"),
     *       @OA\Property(property="state", type="string", example="São Paulo"),
     *       @OA\Property(property="phone", type="string", nullable=true, example="11 99999-0000")
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Created",
     *     @OA\JsonContent(
     *       @OA\Property(property="ok", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Colaborador criado"),
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="id", type="integer", example=12)
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Não autenticado"),
     *   @OA\Response(response=422, description="Dados inválidos")
     * )
     */
    public function store(StoreCollaboratorRequest $request)
    {
        $userId = $this->userIdOrFail();
        return $this->service
            ->create($userId, $request->validated())
            ->toResponse();
    }

    /**
     * @OA\Put(
     *   path="/api/collaborators/{id}",
     *   tags={"Collaborators"},
     *   summary="Substitui completamente um colaborador do gestor autenticado",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name","email","cpf"},
     *       @OA\Property(property="name",  type="string", example="Ana Maria Silva"),
     *       @OA\Property(property="email", type="string", format="email", example="ana.maria@ex.com"),
     *       @OA\Property(property="cpf",   type="string", example="12345678901"),
     *       @OA\Property(property="city",  type="string", nullable=true, example="Osasco"),
     *       @OA\Property(property="state", type="string", nullable=true, example="São Paulo"),
     *       @OA\Property(property="phone", type="string", nullable=true, example="11988887777")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="ok", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Colaborador atualizado"),
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="id", type="integer", example=11)
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Não autenticado"),
     *   @OA\Response(response=404, description="Não encontrado"),
     *   @OA\Response(response=422, description="Dados inválidos")
     * )
     */
    public function update(UpdateCollaboratorRequest $request, int $id)
    {
        $userId = $this->userIdOrFail();

        return $this->service
            ->update($userId, $id, $request->validated())
            ->toResponse();
    }

    /**
     * @OA\Patch(
     *   path="/api/collaborators/{id}",
     *   tags={"Collaborators"},
     *   summary="Atualiza parcialmente um colaborador do gestor autenticado",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="name",  type="string", example="Ana Maria Silva"),
     *       @OA\Property(property="email", type="string", format="email", example="ana.maria@ex.com"),
     *       @OA\Property(property="cpf",   type="string", example="12345678901"),
     *       @OA\Property(property="city",  type="string", example="Osasco"),
     *       @OA\Property(property="state", type="string", example="São Paulo"),
     *       @OA\Property(property="phone", type="string", example="11988887777")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="ok", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Colaborador atualizado"),
     *       @OA\Property(property="data", type="object",
     *         @OA\Property(property="id", type="integer", example=11)
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Não autenticado"),
     *   @OA\Response(response=404, description="Não encontrado"),
     *   @OA\Response(response=422, description="Dados inválidos")
     * )
     */
    public function patch(UpdateCollaboratorPatchRequest $request, int $id)
    {
        $userId = $this->userIdOrFail();

        return $this->service
            ->update($userId, $id, $request->validated())
            ->toResponse();
    }

    /**
     * @OA\Delete(
     *   path="/api/collaborators/{id}",
     *   tags={"Collaborators"},
     *   summary="Remove um colaborador do gestor autenticado",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(property="ok", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Colaborador deletado"),
     *       @OA\Property(property="data", type="null", nullable=true)
     *     )
     *   ),
     *   @OA\Response(response=401, description="Não autenticado"),
     *   @OA\Response(response=404, description="Não encontrado")
     * )
     */
    public function destroy(int $id)
    {
        $userId = $this->userIdOrFail();
        return $this->service
            ->delete($userId, $id)
            ->toResponse();
    }

    /**
     * @OA\Post(
     *   path="/api/collaborators/imports",
     *   tags={"Collaborators"},
     *   summary="Importa colaboradores via CSV e envia e-mail ao finalizar",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="multipart/form-data",
     *       @OA\Schema(
     *         required={"file"},
     *         @OA\Property(
     *           property="file",
     *           type="string",
     *           format="binary",
 *           description="CSV com cabeçalho name,email,cpf,city,state[,phone]"
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=202,
     *     description="Accepted (processamento em fila)",
     *     @OA\JsonContent(
     *       @OA\Property(property="ok", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Arquivo recebido. Processamento em fila.")
     *     )
     *   ),
     *   @OA\Response(response=401, description="Não autenticado"),
     *   @OA\Response(response=422, description="Arquivo inválido")
     * )
     */
    public function upload(Request $request)
    {
        $userId = $this->userIdOrFail();
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:10240']);
        $path = $request->file('file')->store('uploads');

        ProcessCollaboratorsCsv::dispatch($userId, $path);

        return response()->json(['ok' => true, 'message' => 'Arquivo recebido. Processamento em fila.'], 202);
    }
}

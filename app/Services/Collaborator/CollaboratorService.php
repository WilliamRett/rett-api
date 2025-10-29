<?php

namespace App\Services\Collaborator;

use App\Helper\ServiceResponse;
use App\Mail\BulkUploadProcessed;
use App\Repositories\Collaborator\Contract\CollaboratorRepositoryContract;
use App\Repositories\User\Contract\UserRepositoryContract;
use App\Services\Collaborator\Contract\CollaboratorServiceContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CollaboratorService implements CollaboratorServiceContract
{
    /**
     * service constructor
     *
     * @param CollaboratorRepositoryContract $collaborator
     *
     * @return void
     *
     */
    public function __construct(
        private readonly CollaboratorRepositoryContract $collaborator,
        private readonly UserRepositoryContract $userRepository
    ) {}

    /**
     * list collaborators for current user
     *
     * @param int $userId
     * @param int $perPage
     *
     * @return ServiceResponse
     *
     */
    public function list(int $userId, int $perPage = 15): ServiceResponse
    {
        try {
            $data = $this->collaborator->list($userId, $perPage);
            return ServiceResponse::success($data);
        } catch (Throwable $e) {
            report($e);
            return ServiceResponse::error('Falha ao listar colaboradores', 500);
        }
    }

    /**
     * show collaborator details owned by current user
     *
     * @param int $userId
     * @param int $id
     *
     * @return ServiceResponse
     *
     */
    public function show(int $userId, int $id): ServiceResponse
    {
        try {
            $model = $this->collaborator->findById($userId, $id);
            return ServiceResponse::success($model);
        } catch (Throwable $e) {
            report($e);
            $code = str_contains($e::class, 'ModelNotFoundException') ? 404 : 500;
            return ServiceResponse::error('Colaborador não encontrado', $code);
        }
    }

    /**
     * create collaborator for current user
     *
     * @param int $userId
     * @param array $payload
     *
     * @return ServiceResponse
     *
     */
    public function create(int $userId, array $payload): ServiceResponse
    {
        $payload['user_id'] = $userId;
        $payload['state'] = $this->normalizeStateFullName($payload['state'] ?? null);
        DB::beginTransaction();
        try {
            $model = $this->collaborator->create($payload);
            return ServiceResponse::success($model, 'Colaborador criado', 201);
        } catch (Throwable $e) {
            $this->rollbackIfInTransaction();
            report($e);
            return ServiceResponse::error('Falha ao criar colaborador', 500);
        }
    }

    /**
     * update collaborator owned by current user
     *
     * @param int $userId
     * @param int $id
     * @param array $data
     *
     * @return ServiceResponse
     *
     */
    public function update(int $userId, int $id, array $data): ServiceResponse
    {
        if (!$this->collaborator->existsForUser($userId, $id)) {
            return ServiceResponse::error('Usuário não autorizado', 403);
        }
        $payload['state'] = $this->normalizeStateFullName($payload['state'] ?? null);
        DB::beginTransaction();
        try {
            $model = $this->collaborator->update($id, $data);
            return ServiceResponse::success($model, 'Colaborador atualizado');
        } catch (Throwable $e) {
            $this->rollbackIfInTransaction();
            report($e);
            $code = str_contains($e::class, 'ModelNotFoundException') ? 404 : 500;
            return ServiceResponse::error('Falha ao atualizar colaborador', $code);
        }
    }

    /**
     * delete collaborator owned by current user
     *
     * @param int $userId
     * @param int $id
     *
     * @return ServiceResponse
     *
     */
    public function delete(int $userId, int $id): ServiceResponse
    {
        if (!$this->collaborator->existsForUser($userId, $id)) {
            return ServiceResponse::error('Usuário não autorizado', 403);
        }

        DB::beginTransaction();
        try {
            $this->collaborator->delete($userId, $id);
            return ServiceResponse::success(null, 'Colaborador deletado');
        } catch (Throwable $e) {
            $this->rollbackIfInTransaction();
            report($e);
            return ServiceResponse::error('Falha ao deletar colaborador', $this->httpStatusFromException($e));
        }
    }

    /**
     * import collaborators from csv (current user)
     *
     * @param int $userId
     * @param string $storagePath
     *
     * @return ServiceResponse
     *
     */
    public function importFromCsv(int $userId, string $storagePath): ServiceResponse
    {
        return $this->importFromCsvForUser($userId, $storagePath);
    }

    /**
     * Import collaborators from CSV (by user id).
     *
     * Accepts CSV with header variations, normalizes fields,
     * converts 'state' to full state name, inserts in chunks.
     *
     * @param  int    $userId
     * @param  string $storagePath  e.g. "uploads/colabs.csv"
     * @return ServiceResponse
     */
    public function importFromCsvForUser(int $userId, string $storagePath): ServiceResponse
    {
        try {
            if (!Storage::exists($storagePath)) {
                return ServiceResponse::error('Arquivo não encontrado', 422);
            }
            $full = Storage::path($storagePath);
            if (!is_readable($full)) {
                return ServiceResponse::error('Arquivo não acessível', 422);
            }
        } catch (Throwable $e) {
            report($e);
            return ServiceResponse::error('Falha ao preparar importação', 500);
        }

        $created = 0;
        $skipped = 0;
        $errors  = [];

        try {
            $h = fopen($full, 'r');
            if (!$h) {
                return ServiceResponse::error('Falha ao abrir o arquivo', 422);
            }

            $firstLine = fgets($h);

            if ($firstLine === false) {
                fclose($h);
                return ServiceResponse::error('Arquivo vazio', 422);
            }
            $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);

            $header = str_getcsv($firstLine, ',');
            $map    = $this->mapHeader($header);

            $requiredHeaders = ['name', 'email', 'cpf', 'city', 'state'];
            $missing = array_filter($requiredHeaders, fn($k) => !isset($map[$k]) || $map[$k] === null);
            if ($missing) {
                fclose($h);
                return ServiceResponse::error('Cabeçalho inválido/insuficiente no CSV', 422);
            }

            $buffer = [];
            $chunk  = 1000;

            $get = static function (array $row, ?int $idx) {
                return ($idx !== null && array_key_exists($idx, $row)) ? $row[$idx] : null;
            };

            while (($row = fgetcsv($h, 0, ',')) !== false) {
                if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                    continue;
                }

                $name  = $get($row, $map['name']);
                $email = $get($row, $map['email']);
                $cpf   = $get($row, $map['cpf']);
                $city  = $get($row, $map['city']);
                $stRaw = $get($row, $map['state']);
                $state = $this->normalizeStateFullName($stRaw);

                $payload = [
                    'user_id' => $userId,
                    'name'    => $name   !== null ? trim($name) : null,
                    'email'   => $email  !== null ? strtolower(trim($email)) : null,
                    'cpf'     => $cpf    !== null ? preg_replace('/\D+/', '', (string) $cpf) : null,
                    'city'    => $city   !== null ? trim($city) : null,
                    'state'   => $state,
                    'phone'   => null,
                ];

                $missingFields = [];
                foreach (['user_id', 'name', 'email', 'cpf', 'city', 'state'] as $f) {
                    if (empty($payload[$f])) $missingFields[] = $f;
                }

                if ($missingFields) {
                    $skipped++;
                    if (count($errors) < 10) {
                        $errors[] = [
                            'line'   => 'n/d',
                            'reason' => 'Campos ausentes: ' . implode(', ', $missingFields),
                        ];
                    }
                    continue;
                }

                $buffer[] = $payload;

                if (count($buffer) >= $chunk) {
                    $created += $this->collaborator->bulkInsert($buffer);
                    $buffer = [];
                }
            }
            fclose($h);

            if ($buffer) {
                $created += $this->collaborator->bulkInsert($buffer);
            }

            try {
                if ($user = $this->userRepository->findById($userId)) {
                    Mail::to($user->email)->send(new BulkUploadProcessed(
                        userName: $user->name,
                        fileName: basename($full),
                        created: $created,
                        skipped: $skipped,
                        total: $created + $skipped,
                        startedAt: null,
                        finishedAt: now()->toDateTimeString(),
                        duration: null,
                        errors: $errors,
                        dashboardUrl: config('app.url') . '/dashboard/collaborators'
                    ));
                }
            } catch (Throwable $notifyErr) {
                report($notifyErr);
                return ServiceResponse::success(
                    ['created' => $created, 'skipped' => $skipped, 'errors' => $errors],
                    'Processamento ok, falha ao enviar e-mail.'
                );
            }

            return ServiceResponse::success(
                ['created' => $created, 'skipped' => $skipped, 'errors' => $errors],
                'Processamento realizado com sucesso'
            );
        } catch (Throwable $e) {
            report($e);
            return ServiceResponse::error('Falha ao processar CSV', 500);
        }
    }

    /**
     * Normalize BR state input to full official name (e.g., 'SP' or 'sao paulo' -> 'São Paulo').
     *
     * @param  string|null $state
     * @return string|null
     */
    private function normalizeStateFullName(?string $state): ?string
    {
        if ($state === null) return null;
        $raw = trim((string)$state);
        if ($raw === '') return null;

        $byUf = [
            'AC' => 'Acre',
            'AL' => 'Alagoas',
            'AP' => 'Amapá',
            'AM' => 'Amazonas',
            'BA' => 'Bahia',
            'CE' => 'Ceará',
            'DF' => 'Distrito Federal',
            'ES' => 'Espírito Santo',
            'GO' => 'Goiás',
            'MA' => 'Maranhão',
            'MT' => 'Mato Grosso',
            'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais',
            'PA' => 'Pará',
            'PB' => 'Paraíba',
            'PR' => 'Paraná',
            'PE' => 'Pernambuco',
            'PI' => 'Piauí',
            'RJ' => 'Rio de Janeiro',
            'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul',
            'RO' => 'Rondônia',
            'RR' => 'Roraima',
            'SC' => 'Santa Catarina',
            'SP' => 'São Paulo',
            'SE' => 'Sergipe',
            'TO' => 'Tocantins',
        ];

        if (preg_match('/^[A-Za-z]{2}$/', $raw)) {
            $uf = strtoupper($raw);
            return $byUf[$uf] ?? $raw;
        }

        $slug = static function (string $v): string {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $v);
            $v = $ascii !== false ? $ascii : $v;
            $v = strtolower(trim($v));
            return preg_replace('/[^a-z0-9]+/', '-', $v);
        };

        $rawSlug = $slug($raw);
        foreach ($byUf as $uf => $full) {
            if ($rawSlug === $slug($full)) {
                return $full;
            }
        }

        $fallback = mb_convert_case($raw, MB_CASE_TITLE, 'UTF-8');
        return $fallback;
    }

    /**
     * http status helper from exception
     *
     * @param Throwable $e
     *
     * @return int
     *
     */
    private function httpStatusFromException(Throwable $e): int
    {
        $class = $e::class;
        return str_contains($class, 'AuthenticationException') ? 401
            : (str_contains($class, 'ModelNotFoundException') ? 404 : 500);
    }

    /**
     * map csv header to column positions
     *
     * @param array $header
     *
     * @return array{name:?int,email:?int,cpf:?int,city:?int,state:?int}
     *
     */
    private function mapHeader(array $header): array
    {
        $norm = static function (?string $v): string {
            $v = (string) $v;
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $v);
            $v = $ascii !== false ? $ascii : $v;
            $v = strtolower(trim($v));
            return preg_replace('/[^a-z0-9]+/', '', $v);
        };

        $normalized = array_map($norm, $header);

        $indexOf = static function (array $normalized, array $candidates) use ($norm): ?int {
            foreach ($candidates as $c) {
                $needle = $norm($c);
                $i = array_search($needle, $normalized, true);
                if ($i !== false) return $i;
            }
            return null;
        };

        return [
            'name'  => $indexOf($normalized, ['name', 'nome', 'fullname', 'full_name']),
            'email' => $indexOf($normalized, ['email', 'e-mail', 'mail', 'emailaddress']),
            'cpf'   => $indexOf($normalized, ['cpf']),
            'city'  => $indexOf($normalized, ['city', 'cidade', 'municipio', 'município']),
            'state' => $indexOf($normalized, ['state', 'estado']),
        ];
    }

    /**
     * rollback helper when in transaction
     *
     * @return void
     *
     */
    private function rollbackIfInTransaction(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

}

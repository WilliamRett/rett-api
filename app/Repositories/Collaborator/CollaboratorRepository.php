<?php

namespace App\Repositories\Collaborator;

use App\Models\Collaborator;
use App\Repositories\Collaborator\Contract\CollaboratorRepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CollaboratorRepository implements CollaboratorRepositoryContract
{
    /**
     * base eloquent model instance
     *
     * @var Collaborator
     *
     */
    protected Collaborator $collaborator;

    /**
     * repository constructor
     *
     * @param Collaborator $collaborator
     *
     * @return void
     *
     */
    public function __construct(Collaborator $collaborator)
    {
        $this->collaborator = $collaborator;
    }

    /**
     * build base query scoped by user id
     *
     * @param int $userId
     *
     * @return Builder
     *
     */
    protected function builder(int $userId): Builder
    {
        return $this->collaborator->newQuery()->where('user_id', $userId);
    }

    /**
     * commit transaction if currently inside a transaction
     *
     * @return void
     *
     */
    private function commitIfInTransaction(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::commit();
        }
    }

    /**
     * list collaborators for a specific user (paginated)
     *
     * @param int $userId
     * @param int $perPage
     *
     * @return LengthAwarePaginator
     *
     */
    public function list(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->builder($userId)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * create collaborator (expects validated payload with user_id)
     *
     * @param array $data
     *
     * @return Collaborator
     *
     */
    public function create(array $data): Collaborator
    {
        $result = $this->collaborator->newQuery()->create($data);
        $this->commitIfInTransaction();
        return $result;
    }

    /**
     * find collaborator by id for user or fail
     *
     * @param int $userId
     * @param int $id
     *
     * @return Collaborator
     *
     * @throws ModelNotFoundException
     */
    public function findForUserOrFail(int $userId, int $id): Collaborator
    {
        $model = $this->builder($userId)->whereKey($id)->first();
        if (!$model) {
            throw new ModelNotFoundException();
        }
        return $model;
    }

    /**
     * find collaborator by id for user (alias to findForUserOrFail)
     *
     * @param int $userId
     * @param int $id
     *
     * @return Collaborator
     *
     */
    public function findById(int $userId, int $id): Collaborator
    {
        return $this->findForUserOrFail($userId, $id);
    }

    /**
     * update collaborator by id (no user scope)
     *
     * @param int $id
     * @param array $data
     *
     * @return Collaborator
     *
     */
    public function update(int $id, array $data): Collaborator
    {
        $model = $this->collaborator->newQuery()->findOrFail($id);
        unset($data['user_id']);
        $model->fill($data)->save();
        $this->commitIfInTransaction();
        return $model;
    }

    /**
     * delete collaborator by id for user scope
     *
     * @param int $userId
     * @param int $id
     *
     * @return void
     *
     */
    public function delete(int $userId, int $id): void
    {
        $this->findForUserOrFail($userId, $id)->delete();
        $this->commitIfInTransaction();
    }

    /**
     * bulk insert collaborators
     *
     * @param array $rows
     *
     * @return int
     *
     */
    public function bulkInsert(array $rows): int
    {
        $now     = now();
        $allowed = array_flip($this->collaborator->getFillable());
        $bulk    = [];

        foreach ($rows as $r) {
            $payload = array_intersect_key($r, $allowed);

            if (isset($payload['name']))  { $payload['name']  = trim((string)$payload['name']); }
            if (isset($payload['email'])) { $payload['email'] = trim((string)$payload['email']); }
            if (isset($payload['city']))  { $payload['city']  = trim((string)$payload['city']); }
            if (isset($payload['state'])) { $payload['state'] = $this->normalizeState((string)$payload['state']); }
            if (isset($payload['cpf']))   { $payload['cpf']   = preg_replace('/\D+/', '', (string)$payload['cpf']); }

            if (
                empty($payload['user_id']) || empty($payload['name']) || empty($payload['email']) ||
                empty($payload['cpf'])     || empty($payload['city']) || empty($payload['state'])
            ) {
                continue;
            }

            $payload['created_at'] = $now;
            $payload['updated_at'] = $now;

            $bulk[] = $payload;
        }

        if (!$bulk) {
            return 0;
        }

        $this->collaborator->newQuery()->insert($bulk);
        $this->commitIfInTransaction();

        return count($bulk);
    }

    /**
     * get owner id (user_id) for collaborator id
     *
     * @param int $id
     *
     * @return int|null
     *
     */
    public function ownerId(int $id): ?int
    {
        $owner = $this->collaborator->newQuery()->whereKey($id)->value('user_id');
        return $owner !== null ? (int) $owner : null;
        }

    /**
     * delete collaborator by id (no user scope)
     *
     * @param int $id
     *
     * @return void
     *
     */
    public function deleteById(int $id): void
    {
        $this->collaborator->newQuery()->whereKey($id)->delete();
        $this->commitIfInTransaction();
    }

    /**
     * validate if collaborator exists for user
     *
     * @param int $userId
     * @param int $id
     *
     * @return bool
     *
     */
    public function existsForUser(int $userId, int $id): bool
    {
        return $this->builder($userId)->whereKey($id)->exists();
    }

    /**
     * normalize state full name (title case)
     *
     * @param string $state
     *
     * @return string
     *
     */
    private function normalizeState(string $state): string
    {
        $state = trim($state);
        if (function_exists('mb_convert_case')) {
            $state = mb_convert_case(mb_strtolower($state, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        } else {
            $state = ucwords(strtolower($state));
        }
        return $state;
    }
}

<?php

namespace Tests\Feature;

use App\Mail\BulkUploadProcessed;
use App\Models\Collaborator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CollaboratorEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.defaults.guard'    => 'api',
            'auth.guards.api.driver' => 'session',
        ]);
    }

    private function actingAsApi(User $user): void
    {
        $this->actingAs($user, 'api');
    }

    #[Test]
    public function insere_colaborador(): void
    {
        $user = User::factory()->create();
        $this->actingAsApi($user);

        $payload = [
            'name'  => 'Ana Silva',
            'email' => 'ana.teste@outlook.com',
            'cpf'   => '12345678901',
            'city'  => 'São Paulo',
            'state' => 'São Paulo',
            'phone' => '11999990000',
        ];

        $res = $this->postJson('/api/collaborators', $payload);

        $res->assertCreated();
        $this->assertDatabaseHas('collaborators', [
            'email'   => 'ana.teste@outlook.com',
            'user_id' => $user->id,
            'state'   => 'São Paulo',
        ]);
    }

    #[Test]
    public function lista_apenas_colaboradores_do_gestor_autenticado(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Collaborator::factory()->count(2)->create([
            'user_id' => $owner->id,
            'state'   => 'São Paulo',
        ]);

        Collaborator::factory()->create([
            'user_id' => $other->id,
            'state'   => 'Rio de Janeiro',
        ]);

        $this->actingAsApi($owner);

        $res = $this->getJson('/api/collaborators?per_page=100');

        $res->assertOk();
        $payload = $res->json();

        $items = collect(
            $payload['data']['data'] ??
            $payload['data']        ??
            []
        );

        $this->assertCount(2, $items, 'Deve listar somente os 2 do owner');
        $this->assertTrue($items->every(fn ($c) => $c['user_id'] === $owner->id));
    }

    #[Test]
    public function edita_colaborador(): void
    {
        $user  = User::factory()->create();
        $model = Collaborator::factory()->create([
            'user_id' => $user->id,
            'name'    => 'Antigo Nome',
            'state'   => 'São Paulo',
        ]);

        $this->actingAsApi($user);

        $res = $this->putJson("/api/collaborators/{$model->id}", [
            'name'  => 'Novo Nome',
            'email' => 'novo@laravel.com',
            'cpf'   => '98765432100',
            'city'  => 'Osasco',
            'state' => 'São Paulo',
            'phone' => null,
        ]);

        $res->assertOk();

        $this->assertDatabaseHas('collaborators', [
            'id'    => $model->id,
            'name'  => 'Novo Nome',
            'email' => 'novo@laravel.com',
        ]);
    }

    #[Test]
    public function nao_permite_editar_colaborador_de_outro_usuario(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $model = Collaborator::factory()->create([
            'user_id' => $other->id,
            'state'   => 'São Paulo',
        ]);

        $this->actingAsApi($owner);

        $res = $this->putJson("/api/collaborators/{$model->id}", [
            'name'  => 'Hack',
            'email' => 'hack@laravel.com',
            'cpf'   => '11122233344',
            'city'  => 'Cidade',
            'state' => 'São Paulo',
        ]);

        $this->assertTrue(in_array($res->status(), [403, 404], true));
    }

    #[Test]
    public function deleta_colaborador(): void
    {
        $user  = User::factory()->create();
        $model = Collaborator::factory()->create([
            'user_id' => $user->id,
            'state'   => 'São Paulo',
        ]);

        $this->actingAsApi($user);

        $res = $this->deleteJson("/api/collaborators/{$model->id}");

        $res->assertOk();
        $this->assertDatabaseMissing('collaborators', ['id' => $model->id]);
    }

    #[Test]
    public function nao_permite_deletar_colaborador_de_outro_usuario(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $model = Collaborator::factory()->create([
            'user_id' => $other->id,
            'state'   => 'São Paulo',
        ]);

        $this->actingAsApi($owner);

        $res = $this->deleteJson("/api/collaborators/{$model->id}");

        $this->assertTrue(in_array($res->status(), [403, 404], true));
        $this->assertDatabaseHas('collaborators', ['id' => $model->id]);
    }

    #[Test]
    public function valida_payload_inadequado_no_create(): void
    {
        $user = User::factory()->create();
        $this->actingAsApi($user);

        $res = $this->postJson('/api/collaborators', [
            'name'  => '',
            'email' => 'nao-e-email',
        ]);

        $res->assertStatus(422);
    }

    #[Test]
    public function upload_csv_em_massa_e_notifica_por_email(): void
    {
        $user = User::factory()->create();
        $this->actingAsApi($user);

        config(['queue.default' => 'sync']);
        Storage::fake('local');
        Mail::fake();

        $csv = implode("\n", [
            'name,email,cpf,city,state,phone',
            'Alice,alice@laravel.com,12345678901,São Paulo,São Paulo,11999990001',
            'Bruno,bruno@laravel.com,98765432100,Osasco,São Paulo,11999990002',
        ]);

        $file = UploadedFile::fake()->createWithContent('colabs.csv', $csv);

        $res = $this->post('/api/collaborators/imports', ['file' => $file]);

        $res->assertStatus(202);

        $this->assertDatabaseHas('collaborators', [
            'email'   => 'alice@laravel.com',
            'user_id' => $user->id,
            'state'   => 'São Paulo',
        ]);

        $this->assertDatabaseHas('collaborators', [
            'email'   => 'bruno@laravel.com',
            'user_id' => $user->id,
        ]);

        Mail::assertQueued(BulkUploadProcessed::class, fn ($m) => $m->hasTo($user->email));
    }
}

<?php

use App\Enums\Permission as AppPermission;
use App\Enums\Role as AppRole;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Models\ChangeRequest;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (AppPermission::values() as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $adminRole = Role::findOrCreate(AppRole::Admin->value, 'web');
    $clientRole = Role::findOrCreate(AppRole::Client->value, 'web');

    $adminRole->syncPermissions(AppPermission::adminDefaults());
    $clientRole->syncPermissions(AppPermission::clientDefaults());
});

function createProjectForClient(User $client): Project
{
    $order = Order::factory()->create([
        'user_id' => $client->id,
        'status' => 'approved',
    ]);

    return Project::query()->create([
        'order_id' => $order->id,
        'payment_link' => 'https://pay.example.com/project-'.$order->id,
        'payment_status' => 'pending',
        'github_url' => null,
        'deploy_url' => null,
        'current_progress' => 0,
    ]);
}

function createAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(AppRole::Admin->value);

    return $admin;
}

function createClient(): User
{
    $client = User::factory()->create();
    $client->assignRole(AppRole::Client->value);

    return $client;
}

test('client can submit a change request in own project', function (): void {
    $client = createClient();
    $project = createProjectForClient($client);

    Auth::login($client);

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->set('newChangeRequest.description', 'Quero trocar a seção de contato e ajustar o formulário para duas etapas.')
        ->call('submitChangeRequest');

    $changeRequest = ChangeRequest::query()->first();

    expect($changeRequest)->not()->toBeNull();
    expect($changeRequest?->status)->toBe('requested');
    expect($changeRequest?->requester_id)->toBe($client->id);
});

test('admin can approve request and send quote with percentage', function (): void {
    $client = createClient();
    $admin = createAdmin();
    $project = createProjectForClient($client);

    $changeRequest = ChangeRequest::query()->create([
        'project_id' => $project->id,
        'requester_id' => $client->id,
        'description' => 'Preciso adicionar um bloco de depoimentos na home.',
        'status' => 'requested',
        'impact_price' => 0,
        'change_weight' => null,
        'payment_link' => null,
    ]);

    Auth::login($admin);

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->call('approveChangeRequest', $changeRequest->id)
        ->set("quoteForms.{$changeRequest->id}.impact_price", 350)
        ->set("quoteForms.{$changeRequest->id}.change_weight", 25)
        ->call('submitQuote', $changeRequest->id);

    $changeRequest->refresh();

    expect($changeRequest->status)->toBe('quoted');
    expect((int) $changeRequest->change_weight)->toBe(25);
    expect((float) $changeRequest->impact_price)->toBe(350.0);
});

test('client cannot respond to quote that belongs to another client', function (): void {
    $ownerClient = createClient();
    $otherClient = createClient();
    $project = createProjectForClient($ownerClient);

    $changeRequest = ChangeRequest::query()->create([
        'project_id' => $project->id,
        'requester_id' => $ownerClient->id,
        'description' => 'Quero mudar o layout da seção de serviços.',
        'status' => 'quoted',
        'impact_price' => 200,
        'change_weight' => 10,
        'payment_link' => null,
    ]);

    Auth::login($otherClient);

    expect(fn (): mixed => Livewire::test(ViewProject::class, ['record' => $project->id])
        ->call('approveQuotedChangeRequest', $changeRequest->id))
        ->toThrow(NotFoundHttpException::class);
});

test('admin cannot mark payment as paid without payment link', function (): void {
    $client = createClient();
    $admin = createAdmin();
    $project = createProjectForClient($client);

    $changeRequest = ChangeRequest::query()->create([
        'project_id' => $project->id,
        'requester_id' => $client->id,
        'description' => 'Adicionar integração com CRM.',
        'status' => 'payment_pending',
        'impact_price' => 500,
        'change_weight' => 30,
        'payment_link' => null,
    ]);

    Auth::login($admin);

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->set("paymentForms.{$changeRequest->id}.payment_status", 'paid')
        ->call('updatePaymentStatus', $changeRequest->id);

    $changeRequest->refresh();

    expect($changeRequest->status)->toBe('payment_pending');
});

test('admin can complete change request only from pending development', function (): void {
    $client = createClient();
    $admin = createAdmin();
    $project = createProjectForClient($client);

    $changeRequest = ChangeRequest::query()->create([
        'project_id' => $project->id,
        'requester_id' => $client->id,
        'description' => 'Ajustar SEO técnico e metadados.',
        'status' => 'pending_development',
        'impact_price' => 150,
        'change_weight' => 15,
        'payment_link' => 'https://pay.example.com/abc',
    ]);

    Auth::login($admin);

    Livewire::test(ViewProject::class, ['record' => $project->id])
        ->call('markChangeRequestAsCompleted', $changeRequest->id);

    $changeRequest->refresh();

    expect($changeRequest->status)->toBe('completed');
});

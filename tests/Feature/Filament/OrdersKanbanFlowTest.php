<?php

use App\Enums\Permission as AppPermission;
use App\Enums\Role as AppRole;
use App\Filament\Resources\Orders\Pages\OrdersKanban;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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

test('admin can update order status from kanban', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(AppRole::Admin->value);

    $order = Order::factory()->create([
        'status' => 'pending',
    ]);

    Auth::login($admin);

    Livewire::test(OrdersKanban::class)
        ->call('updateOrderStatus', $order->id, 'in_progress');

    $order->refresh();

    expect($order->status)->toBe('in_progress');
});

test('client cannot update order status from kanban', function (): void {
    $client = User::factory()->create();
    $client->assignRole(AppRole::Client->value);

    $order = Order::factory()->create([
        'user_id' => $client->id,
        'status' => 'pending',
    ]);

    Auth::login($client);

    Livewire::test(OrdersKanban::class)
        ->call('updateOrderStatus', $order->id, 'done');

    $order->refresh();

    expect($order->status)->toBe('pending');
});

test('client kanban shows only own active statuses', function (): void {
    $client = User::factory()->create();
    $client->assignRole(AppRole::Client->value);

    $otherClient = User::factory()->create();
    $otherClient->assignRole(AppRole::Client->value);

    Order::factory()->create([
        'user_id' => $client->id,
        'status' => 'in_progress',
    ]);

    Order::factory()->create([
        'user_id' => $client->id,
        'status' => 'done',
    ]);

    Order::factory()->create([
        'user_id' => $otherClient->id,
        'status' => 'under_review',
    ]);

    Auth::login($client);

    $component = Livewire::test(OrdersKanban::class);

    /** @var array<int, array{status: string, label: string, color: string, orders: Collection<int, Order>}> $columns */
    $columns = $component->instance()->kanbanColumns();

    $inProgressColumn = collect($columns)->firstWhere('status', 'in_progress');
    $doneColumn = collect($columns)->firstWhere('status', 'done');
    $underReviewColumn = collect($columns)->firstWhere('status', 'under_review');

    expect($inProgressColumn)->not()->toBeNull();
    expect($doneColumn)->not()->toBeNull();
    expect($underReviewColumn)->not()->toBeNull();

    expect($inProgressColumn['orders']->count())->toBe(1);
    expect($doneColumn['orders']->count())->toBe(0);
    expect($underReviewColumn['orders']->count())->toBe(0);
});

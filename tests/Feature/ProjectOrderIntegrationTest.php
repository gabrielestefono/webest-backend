<?php

use App\Models\Order;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('project payment status paid syncs order to paid', function (): void {
    $order = Order::factory()->create([
        'status' => 'approved',
    ]);

    $project = Project::query()->create([
        'order_id' => $order->id,
        'payment_link' => 'https://pay.example.com/p-1',
        'payment_status' => 'pending',
        'github_url' => null,
        'deploy_url' => null,
        'current_progress' => 0,
    ]);

    $project->update([
        'payment_status' => 'paid',
    ]);

    $order->refresh();

    expect($order->status)->toBe('paid');
});

test('project payment status rejected syncs order to rejected', function (): void {
    $order = Order::factory()->create([
        'status' => 'approved',
    ]);

    $project = Project::query()->create([
        'order_id' => $order->id,
        'payment_link' => 'https://pay.example.com/p-2',
        'payment_status' => 'pending',
        'github_url' => null,
        'deploy_url' => null,
        'current_progress' => 0,
    ]);

    $project->update([
        'payment_status' => 'rejected',
    ]);

    $order->refresh();

    expect($order->status)->toBe('rejected');
});

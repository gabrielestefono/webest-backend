<?php

use App\Enums\Permission as AppPermission;
use App\Enums\Role as AppRole;
use App\Filament\Resources\Orders\Pages\NegotiationPage;
use App\Filament\Resources\Orders\Pages\OrderScopePage;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderScope;
use App\Models\Product;
use App\Models\Proposal;
use App\Models\User;
use App\Services\OrderTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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

test('client can list products with stripe billing metadata and create multi-product order with private attachments', function (): void {
    Storage::fake('private');

    $client = User::factory()->create();
    $client->assignRole(AppRole::Client->value);
    $token = $client->createToken('test')->plainTextToken;

    $p1 = Product::query()->create([
        'name' => 'Landing Page',
        'description' => 'LP moderna',
        'base_price' => 1200,
        'billing_type' => 'one_time',
        'billing_interval' => null,
        'active' => true,
    ]);

    $p2 = Product::query()->create([
        'name' => 'Domínio DNS',
        'description' => 'Gestão anual DNS',
        'base_price' => 90,
        'billing_type' => 'recurring',
        'billing_interval' => 'year',
        'active' => true,
    ]);

    $catalogRes = $this->withToken($token)->getJson('/api/products');
    $catalogRes->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data');

    $mockup = UploadedFile::fake()->image('layout-referencia.png', 800, 600);

    $storeRes = $this->withToken($token)->post('/api/orders', [
        'title' => 'Minha Landing Page Roxa',
        'description' => 'Gostaria de uma landing page moderna com cores roxas e escuras.',
        'figma_url' => 'https://www.figma.com/file/abc123/Webest-LP',
        'product_ids' => [$p1->id, $p2->id],
        'initial_message' => 'Olá, quero o meu website com a cor vermelha',
        'attachments' => [$mockup],
    ], ['Accept' => 'application/json']);

    $storeRes->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'under_review')
        ->assertJsonCount(2, 'data.products')
        ->assertJsonCount(1, 'data.attachments');

    $orderId = (int) $storeRes->json('data.id');
    $attachmentId = (int) $storeRes->json('data.attachments.0.id');

    $order = Order::query()->with('attachments')->findOrFail($orderId);
    Storage::disk('private')->assertExists($order->attachments[0]->file_path);

    // Owner can download private attachment
    $downloadRes = $this->withToken($token)->get("/api/orders/{$orderId}/attachments/{$attachmentId}/download");
    $downloadRes->assertOk();

    // Another client is forbidden from downloading private attachment
    Auth::forgetGuards();
    $intruder = User::factory()->create();
    $intruder->assignRole(AppRole::Client->value);
    $intruderToken = $intruder->createToken('intruder')->plainTextToken;

    $forbiddenRes = $this->withToken($intruderToken)->getJson("/api/orders/{$orderId}/attachments/{$attachmentId}/download");
    $forbiddenRes->assertStatus(403)
        ->assertJsonPath('success', false);
});

test('turn protocol enforces single message per turn, versioned edits while unanswered, and permanent lock once answered', function (): void {
    $client = User::factory()->create(['name' => 'Cliente Teste']);
    $client->assignRole(AppRole::Client->value);
    $token = $client->createToken('test')->plainTextToken;

    $admin = User::factory()->create(['name' => 'Prestador WeBest']);
    $admin->assignRole(AppRole::Admin->value);

    $product = Product::query()->create([
        'name' => 'Landing Page',
        'base_price' => 500,
        'billing_type' => 'one_time',
        'active' => true,
    ]);

    $storeRes = $this->withToken($token)->postJson('/api/orders', [
        'title' => 'Website Institucional',
        'description' => 'Descrição completa do website institucional desejado.',
        'product_ids' => [$product->id],
        'initial_message' => 'Olá, quero o meu website com a cor vermelha',
    ]);

    $storeRes->assertOk();
    $orderId = (int) $storeRes->json('data.id');
    $msgId = (int) $storeRes->json('data.turn_state.editable_client_message_id');
    expect($msgId)->toBeGreaterThan(0);

    // Client cannot send a second separate message in the same unanswered turn
    $duplicateTurn = $this->withToken($token)->postJson("/api/orders/{$orderId}/messages", [
        'content' => 'Mais uma mensagem solta',
    ]);
    $duplicateTurn->assertStatus(400);

    // Client CAN edit their active unanswered turn message, preserving version history
    $editRes = $this->withToken($token)->putJson("/api/orders/{$orderId}/messages/{$msgId}", [
        'content' => 'Olá, quero o meu website com a cor cinza',
    ]);
    $editRes->assertOk()
        ->assertJsonPath('success', true);

    $order = Order::query()->findOrFail($orderId);
    $firstMessage = $order->messages()->firstOrFail();
    expect($firstMessage->current_content)->toBe('Olá, quero o meu website com a cor cinza')
        ->and($firstMessage->versions()->count())->toBe(2)
        ->and($firstMessage->is_locked)->toBeFalse();

    // Provider replies via timeline service -> locks client's Turn #1 permanently
    app(OrderTimelineService::class)->sendTurnMessage(
        order: $order,
        sender: $admin,
        senderRole: 'provider',
        content: 'Olá! Vamos fazer o site com a cor cinza',
    );

    $firstMessage->refresh();
    expect($firstMessage->is_locked)->toBeTrue();

    // Now client CANNOT edit Turn #1 anymore
    $lockedEdit = $this->withToken($token)->putJson("/api/orders/{$orderId}/messages/{$msgId}", [
        'content' => 'Tentando mudar depois da resposta',
    ]);
    $lockedEdit->assertStatus(400);

    // Instead, client CAN open a new Turn #3
    $newTurnRes = $this->withToken($token)->postJson("/api/orders/{$orderId}/messages", [
        'content' => 'Perfeito, combinado!',
    ]);
    $newTurnRes->assertOk();
    expect($order->messages()->count())->toBe(3);
});

test('full lifecycle: mandatory scope, consolidated pdf, ping-pong negotiation, contract lock, stripe webhook, out-of-scope new order spawn and 30d warranty', function (): void {
    config()->set('services.stripe.webhook_secret', 'whsec_test_secret_123');

    $client = User::factory()->create(['name' => 'Cliente Tramelo']);
    $client->assignRole(AppRole::Client->value);
    $token = $client->createToken('test')->plainTextToken;

    $admin = User::factory()->create(['name' => 'Gabriel WeBest']);
    $admin->assignRole(AppRole::Admin->value);

    $product = Product::query()->create([
        'name' => 'Landing Page',
        'base_price' => 500,
        'billing_type' => 'one_time',
        'active' => true,
    ]);

    $deployProduct = Product::query()->create([
        'name' => 'Deploy & Configuração VPS',
        'base_price' => 250,
        'billing_type' => 'recurring',
        'billing_interval' => 'month',
        'active' => true,
    ]);

    $storeRes = $this->withToken($token)->postJson('/api/orders', [
        'title' => 'Landing page Tramelo',
        'description' => 'Quero uma landing page SaaS completa com 6 seções.',
        'product_ids' => [$product->id],
        'initial_message' => 'Olá! Preciso da landing page responsiva com formulário de contato.',
    ]);
    $orderId = (int) $storeRes->json('data.id');
    $order = Order::query()->findOrFail($orderId);

    // PDF cannot be downloaded before scope is registered
    $prematurePdf = $this->withToken($token)->getJson("/api/orders/{$orderId}/contract/pdf");
    $prematurePdf->assertStatus(400)
        ->assertJsonPath('success', false);

    // 1. Provider tries to send proposal BEFORE registering mandatory scope -> BLOCKED!
    Auth::forgetGuards();
    Auth::guard('web')->login($admin);
    Livewire::test(NegotiationPage::class, ['record' => $order->id])
        ->set('proposalFormData.content', 'Tentativa sem escopo.')
        ->set('proposalFormData.price', '500.00')
        ->set('proposalFormData.estimated_days', 7)
        ->call('sendProposal');

    $order->refresh();
    expect($order->status)->toBe('under_review')
        ->and($order->proposals()->count())->toBe(0);

    // 2. Provider registers mandatory Escopo do Serviço using dynamic list items on OrderScopePage
    Livewire::test(OrderScopePage::class, ['record' => $order->id])
        ->set('scopeForm.included_items.0', 'Desenvolvimento de uma landing page.')
        ->call('addItem', 'included_items')
        ->set('scopeForm.included_items.1', 'Layout responsivo (desktop e mobile).')
        ->call('addItem', 'included_items')
        ->set('scopeForm.included_items.2', 'Item temporário para remover')
        ->call('removeItem', 'included_items', 2)
        ->set('scopeForm.excluded_items.0', 'Desenvolvimento de painel administrativo.')
        ->call('addItem', 'excluded_items')
        ->set('scopeForm.excluded_items.1', 'Deploy em produção / VPS.')
        ->set('scopeForm.delivery_criteria.0', 'Página acessível por URL pública de homologação.')
        ->set('scopeForm.revision_rules.0', 'Até 2 rodadas de ajustes respeitando o escopo contratado.')
        ->set('scopeForm.client_responsibilities.0', 'Fornecer textos, imagens e validar a homologação.')
        ->call('saveScope', false);

    $order->refresh();
    expect($order->hasCompleteScope())->toBeTrue();
    $savedScope = OrderScope::query()->where('order_id', $order->id)->firstOrFail();
    expect($savedScope->normalizedIncludedItems())->toHaveCount(2)
        ->and($savedScope->locked_at)->toBeNull();

    // 3. Now Provider sends offer of R$ 500 via Filament NegotiationPage -> SUCCEEDS!
    Livewire::test(NegotiationPage::class, ['record' => $order->id])
        ->set('proposalFormData.content', 'Olá! Farei sua landing page em 7 dias úteis conforme o Escopo do Serviço.')
        ->set('proposalFormData.price', '500.00')
        ->set('proposalFormData.estimated_days', 7)
        ->call('sendProposal');

    $order->refresh();
    expect($order->status)->toBe('proposal_negotiation');

    // 4. Client downloads the consolidated PDF (scope + conversation + proposals) and sends counter-proposal of R$ 450
    Auth::guard('web')->logout();
    Auth::forgetGuards();

    $pdfRes = $this->withToken($token)->get("/api/orders/{$orderId}/contract/pdf");
    $pdfRes->assertOk();
    expect((string) $pdfRes->headers->get('content-type'))->toContain('application/pdf');
    $pdfBytes = $pdfRes->streamedContent();
    expect(str_starts_with($pdfBytes, '%PDF-'))->toBeTrue();

    $counterRes = $this->withToken($token)->postJson("/api/orders/{$orderId}/proposals/respond", [
        'decision' => 'counter',
        'counter_price' => 450,
        'counter_days' => 7,
        'counter_content' => 'Tenho 450 no orçamento, fechamos?',
    ]);
    $counterRes->assertOk();

    $clientProposal = Proposal::query()->where('order_id', $orderId)->latest('id')->firstOrFail();
    expect((float) $clientProposal->price)->toBe(450.0);

    // 5. Provider accepts counter-proposal via Filament -> no fake contract_url fallback
    Auth::forgetGuards();
    Auth::guard('web')->login($admin);
    Livewire::test(NegotiationPage::class, ['record' => $order->id])
        ->call('acceptProposal', $clientProposal->id);

    $order->refresh();
    expect($order->status)->toBe('awaiting_contract')
        ->and((float) $order->final_price)->toBe(450.0)
        ->and($order->contract_url)->toBeNull();

    // 6. Client signs contract -> status becomes awaiting_payment AND OrderScope is locked!
    Auth::guard('web')->logout();
    Auth::forgetGuards();
    $signRes = $this->withToken($token)->postJson("/api/orders/{$orderId}/contract/sign", [
        'accepted_terms' => true,
    ]);
    $signRes->assertOk()
        ->assertJsonPath('data.status', 'awaiting_payment');

    $savedScope->refresh();
    expect($savedScope->locked_at)->not->toBeNull();

    // Provider cannot mutate locked scope after signature
    Auth::forgetGuards();
    Auth::guard('web')->login($admin);
    Livewire::test(OrderScopePage::class, ['record' => $order->id])
        ->set('scopeForm.included_items.0', 'Tentativa de adulterar escopo assinado')
        ->call('saveScope', false);

    $savedScope->refresh();
    expect($savedScope->normalizedIncludedItems()[0])->toBe('Desenvolvimento de uma landing page.');

    // 7. Stripe Webhook confirms payment -> status automatically moves to awaiting_development
    Auth::guard('web')->logout();
    Auth::forgetGuards();
    $webhookBody = json_encode([
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'metadata' => [
                    'order_id' => (string) $orderId,
                    'payment_purpose' => 'order_main',
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $timestamp = time();
    $sig = hash_hmac('sha256', "{$timestamp}.{$webhookBody}", 'whsec_test_secret_123');

    $webhookRes = $this->call(
        'POST',
        '/api/webhooks/stripe',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$sig}",
        ],
        $webhookBody
    );
    $webhookRes->assertOk();

    $order->refresh();
    expect($order->status)->toBe('awaiting_development');

    // 8. Provider manually starts development and releases homologation in Filament
    Auth::forgetGuards();
    Auth::guard('web')->login($admin);
    Livewire::test(NegotiationPage::class, ['record' => $order->id])
        ->call('startDevelopmentManually')
        ->set('stagingUrlInput', 'https://staging-lp.webest.dev')
        ->call('releaseForHomologation');

    $order->refresh();
    expect($order->status)->toBe('homologation');

    // 9. Client requests out-of-scope adjustment (Deploy) -> Provider spawns a NEW Order with product selection!
    Auth::guard('web')->logout();
    Auth::forgetGuards();
    $adjRes = $this->withToken($token)->postJson("/api/orders/{$orderId}/adjustments", [
        'description' => 'Achei que o deploy em VPS de produção já estava incluso, podem subir no meu domínio?',
    ]);
    $adjRes->assertOk()
        ->assertJsonPath('data.status', 'adjustment_review');

    $adjId = (int) $adjRes->json('data.adjustments.0.id');

    Auth::forgetGuards();
    Auth::guard('web')->login($admin);
    Livewire::test(NegotiationPage::class, ['record' => $order->id])
        ->call('openSpawnOrderModal', $adjId)
        ->call('toggleSpawnProduct', $deployProduct->id)
        ->set('spawnOrderForm.title', 'Deploy em Produção — Landing page Tramelo')
        ->set('spawnOrderForm.provider_notes', 'O escopo aprovado contemplava apenas o desenvolvimento e homologação da Landing Page. Criamos este novo pedido de Deploy & VPS para seguirmos com escopo e orçamento próprios.')
        ->call('createNewOrderFromAdjustment');

    $order->refresh();
    $adjustment = OrderAdjustment::query()->findOrFail($adjId);
    expect($order->status)->toBe('homologation')
        ->and($adjustment->decision)->toBe('redirected_to_new_order')
        ->and($adjustment->spawned_order_id)->not->toBeNull();

    $spawnedOrder = Order::query()->with('products')->findOrFail($adjustment->spawned_order_id);
    expect($spawnedOrder->parent_order_id)->toBe($order->id)
        ->and($spawnedOrder->user_id)->toBe($client->id)
        ->and($spawnedOrder->status)->toBe('under_review')
        ->and($spawnedOrder->products->pluck('id')->all())->toBe([$deployProduct->id]);

    $redirectBotEvent = $order->events()
        ->where('event_type', 'adjustment_redirected_to_order')
        ->latest('id')
        ->first();
    expect($redirectBotEvent)->not->toBeNull()
        ->and($redirectBotEvent?->payload['new_order_id'] ?? null)->toBe($spawnedOrder->id)
        ->and($redirectBotEvent?->payload['new_order_path'] ?? null)->toBe("/dashboard/pedidos/{$spawnedOrder->id}");

    // 10. Both Client and Provider finalize original order -> 30-day warranty activated!
    Auth::guard('web')->logout();
    Auth::forgetGuards();
    $this->withToken($token)->postJson("/api/orders/{$orderId}/complete")->assertOk();

    Auth::forgetGuards();
    Auth::guard('web')->login($admin);
    Livewire::test(NegotiationPage::class, ['record' => $order->id])
        ->call('confirmProviderCompletion');

    $order->refresh();
    expect($order->status)->toBe('done')
        ->and($order->warranty_until)->not->toBeNull();

    $hasWarrantyEvent = $order->events()->where('event_type', 'warranty_activated')->exists();
    expect($hasWarrantyEvent)->toBeTrue();
});

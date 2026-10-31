<?php

use App\Filament\Resources\Orders\Pages\NegotiationPage;
use App\Filament\Resources\Orders\Pages\OrderScopePage;
use Filament\Resources\Pages\EditRecord;

test('negotiation and scope pages exist', function () {
    expect(class_exists(NegotiationPage::class))->toBeTrue()
        ->and(class_exists(OrderScopePage::class))->toBeTrue();
});

test('negotiation and scope pages extend EditRecord', function () {
    $negotiationRef = new ReflectionClass(NegotiationPage::class);
    $scopeRef = new ReflectionClass(OrderScopePage::class);

    expect($negotiationRef->getParentClass()->getName())->toBe(EditRecord::class)
        ->and($scopeRef->getParentClass()->getName())->toBe(EditRecord::class);
});

test('negotiation page has sendProposal and createNewOrderFromAdjustment methods', function () {
    expect(method_exists(NegotiationPage::class, 'sendProposal'))->toBeTrue()
        ->and(method_exists(NegotiationPage::class, 'createNewOrderFromAdjustment'))->toBeTrue()
        ->and(method_exists(NegotiationPage::class, 'issueTermoAddendum'))->toBeFalse();
});

test('negotiation page has acceptProposal method', function () {
    expect(method_exists(NegotiationPage::class, 'acceptProposal'))->toBeTrue();
});

test('negotiation page has rejectProposal method', function () {
    expect(method_exists(NegotiationPage::class, 'rejectProposal'))->toBeTrue();
});

test('negotiation page has proposalFormData property', function () {
    $reflectionClass = new ReflectionClass(NegotiationPage::class);
    expect($reflectionClass->hasProperty('proposalFormData'))->toBeTrue();
});

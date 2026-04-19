<?php

use App\Filament\Resources\Orders\Pages\NegotiationPage;
use Filament\Resources\Pages\EditRecord;

test('negotiation page exists', function () {
    expect(class_exists(NegotiationPage::class))->toBeTrue();
});

test('negotiation page extends EditRecord', function () {
    $reflectionClass = new ReflectionClass(NegotiationPage::class);
    expect($reflectionClass->getParentClass()->getName())->toBe(EditRecord::class);
});

test('negotiation page has sendProposal method', function () {
    expect(method_exists(NegotiationPage::class, 'sendProposal'))->toBeTrue();
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

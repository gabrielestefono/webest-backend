<?php

namespace App\Http\Controllers\Api;

use App\Actions\Stripe\HandleStripeWebhookAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stripe\StripeWebhookRequest;
use App\Http\Resources\Stripe\StripeWebhookResource;

class StripeWebhookController extends Controller
{
    public function handle(StripeWebhookRequest $request, HandleStripeWebhookAction $action): StripeWebhookResource
    {
        return new StripeWebhookResource($action->handle($request->toDto()));
    }
}


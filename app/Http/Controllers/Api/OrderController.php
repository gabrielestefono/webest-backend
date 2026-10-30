<?php

namespace App\Http\Controllers\Api;

use App\Actions\Order\CompleteOrderAction;
use App\Actions\Order\DownloadOrderAttachmentAction;
use App\Actions\Order\ListOrdersAction;
use App\Actions\Order\RespondAddendumAction;
use App\Actions\Order\RespondProposalAction;
use App\Actions\Order\SendTurnMessageAction;
use App\Actions\Order\ShowOrderAction;
use App\Actions\Order\SignContractAction;
use App\Actions\Order\StoreAdjustmentAction;
use App\Actions\Order\StoreOrderAction;
use App\Actions\Order\UpdateTurnMessageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CompleteOrderRequest;
use App\Http\Requests\Order\DownloadOrderAttachmentRequest;
use App\Http\Requests\Order\ListOrdersRequest;
use App\Http\Requests\Order\RespondAddendumRequest;
use App\Http\Requests\Order\RespondProposalRequest;
use App\Http\Requests\Order\SendTurnMessageRequest;
use App\Http\Requests\Order\ShowOrderRequest;
use App\Http\Requests\Order\SignContractRequest;
use App\Http\Requests\Order\StoreAdjustmentRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateTurnMessageRequest;
use App\Http\Resources\Order\CompleteOrderResource;
use App\Http\Resources\Order\ListOrdersResource;
use App\Http\Resources\Order\RespondAddendumResource;
use App\Http\Resources\Order\RespondProposalResource;
use App\Http\Resources\Order\SendTurnMessageResource;
use App\Http\Resources\Order\ShowOrderResource;
use App\Http\Resources\Order\SignContractResource;
use App\Http\Resources\Order\StoreAdjustmentResource;
use App\Http\Resources\Order\StoreOrderResource;
use App\Http\Resources\Order\UpdateTurnMessageResource;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(ListOrdersRequest $request, ListOrdersAction $action): ListOrdersResource
    {
        return new ListOrdersResource($action->handle($request->toDto()));
    }

    public function store(StoreOrderRequest $request, StoreOrderAction $action): StoreOrderResource
    {
        return new StoreOrderResource($action->handle($request->toDto()));
    }

    public function show(ShowOrderRequest $request, ShowOrderAction $action): ShowOrderResource
    {
        return new ShowOrderResource($action->handle($request->toDto()));
    }

    public function downloadAttachment(DownloadOrderAttachmentRequest $request, DownloadOrderAttachmentAction $action): StreamedResponse
    {
        return $action->handle($request->toDto());
    }

    public function sendMessage(SendTurnMessageRequest $request, SendTurnMessageAction $action): SendTurnMessageResource
    {
        return new SendTurnMessageResource($action->handle($request->toDto()));
    }

    public function updateMessage(UpdateTurnMessageRequest $request, UpdateTurnMessageAction $action): UpdateTurnMessageResource
    {
        return new UpdateTurnMessageResource($action->handle($request->toDto()));
    }

    public function respondProposal(RespondProposalRequest $request, RespondProposalAction $action): RespondProposalResource
    {
        return new RespondProposalResource($action->handle($request->toDto()));
    }

    public function signContract(SignContractRequest $request, SignContractAction $action): SignContractResource
    {
        return new SignContractResource($action->handle($request->toDto()));
    }

    public function storeAdjustment(StoreAdjustmentRequest $request, StoreAdjustmentAction $action): StoreAdjustmentResource
    {
        return new StoreAdjustmentResource($action->handle($request->toDto()));
    }

    public function respondAddendum(RespondAddendumRequest $request, RespondAddendumAction $action): RespondAddendumResource
    {
        return new RespondAddendumResource($action->handle($request->toDto()));
    }

    public function completeOrder(CompleteOrderRequest $request, CompleteOrderAction $action): CompleteOrderResource
    {
        return new CompleteOrderResource($action->handle($request->toDto()));
    }
}


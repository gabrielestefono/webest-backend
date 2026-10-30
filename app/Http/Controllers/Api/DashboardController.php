<?php

namespace App\Http\Controllers\Api;

use App\Actions\Dashboard\DashboardOverviewAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardOverviewRequest;
use App\Http\Resources\Dashboard\DashboardOverviewResource;

class DashboardController extends Controller
{
    public function index(DashboardOverviewRequest $request, DashboardOverviewAction $action): DashboardOverviewResource
    {
        return new DashboardOverviewResource($action->handle($request->toDto()));
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Actions\Product\ListProductsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ListProductsRequest;
use App\Http\Resources\Product\ListProductsResource;

class ProductController extends Controller
{
    public function index(ListProductsRequest $request, ListProductsAction $action): ListProductsResource
    {
        return new ListProductsResource($action->handle($request->toDto()));
    }
}


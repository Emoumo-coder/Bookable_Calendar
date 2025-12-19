<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    /**
     * GET /api/services
     * Returns all available services
     */
    public function getServices(): JsonResponse
    {
        return response()->json([
            'data' => ServiceResource::collection(
                Service::active()->get()
            ),
            'message' => 'Services retrieved successfully'
        ]);
    }
}

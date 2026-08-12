<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class WellKnownPasskeyEndpointsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'enroll' => route('security.edit'),
            'manage' => route('security.edit'),
        ]);
    }
}

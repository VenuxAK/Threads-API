<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UtilityController extends Controller
{
    public function wafTest(): JsonResponse
    {
        return response()->json([
            'message' => 'WAF Test Endpoint',
            'timestamp' => now()->toISOString(),
            'waf_enabled' => config('waf.enabled', false),
            'waf_mode' => config('waf.mode', 'monitor'),
        ]);
    }

    public function pingMongoDb(): JsonResponse
    {
        if (! config('database.connections.mongodb.dsn')) {
            return response()->json([
                'msg' => 'MongoDB is not configured',
            ], 500);
        }

        try {
            $client = DB::connection('mongodb')->getClient();
            $client->selectDatabase('admin')->command(['ping' => 1]);

            return response()->json([
                'msg' => 'Pinged your deployment. You successfully connected to MongoDB!',
            ]);
        } catch (\Throwable $e) {
            Log::error('MongoDB ping failed', ['error' => $e->getMessage()]);

            return response()->json([
                'msg' => 'MongoDB connection failed',
            ], 500);
        }
    }
}

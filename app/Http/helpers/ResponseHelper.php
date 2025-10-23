<?php

namespace App\Http\helpers;

use Illuminate\Http\JsonResponse;

class ResponseHelper
{
    /**
     * This is the method used to formulate a success response
     * @param array $data
     * @param string $message
     * @param $statusCode
     * @return JsonResponse
     */
    public static function success(array $data, string $message, $statusCode): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * This is the method used to formulate an error response
     * @param array $data
     * @param string $message
     * @param $statusCode
     * @return JsonResponse
     */
    public static function error(array $data, string $message, $statusCode): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }
}

<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class Response
{
    public static function Success($data, $message = 'okay', $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 1,
            'data' => $data,
            'message' => $message
        ], $code);
    }

    public static function Error($data, $message, $code = 500): JsonResponse
    {
        return response()->json([
            'status' => 0,
            'data' => $data,
            'message' => $message
        ], $code);
    }

    public static function Validation($errors, $message = 'Validation error', $code = 422): JsonResponse
    {
        return response()->json([
            'status' => 0,
            'data' => $errors,
            'message' => $message
        ], $code);
    }
}

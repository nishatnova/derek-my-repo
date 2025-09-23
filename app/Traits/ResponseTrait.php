<?php

namespace App\Traits;

trait ResponseTrait
{
    /**
     * Send a success response.
     *
     * @param mixed $data
     * @param string $message
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */

    public function sendResponse($data, $message = '', $status = 200)
    {
        return response()->json([
            'success' => true,
            'status' => $status,
            'message' => $message,
            'data' => $data
        ], $status);
    }


    /**
     * Send an error response.
     *
     * @param string $message
     * @param array $error
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */

    public function sendError($message, $error = [], $status = 400)
    {
        return response()->json([
            'success' => false,
            'status' => $status,
            'message' => $message,
            'data' => []
        ], $status);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BaseController extends Controller
{
    /**
     * Return a successful JSON response with data.
     * Conforms to ADR-0008 JSON:API standard with data/meta/error envelope.
     */
    protected function successResponse($data, ?array $meta = null): JsonResponse
    {
        $response = [
            'data' => $data,
        ];

        if ($meta) {
            $response['meta'] = $meta;
        }

        $response['error'] = null;

        return response()->json($response);
    }

    /**
     * Return an error JSON response.
     * Conforms to ADR-0008 JSON:API standard with data/meta/error envelope.
     */
    protected function errorResponse(string $code, string $message, $details = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'data' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details ?? [],
            ],
        ], $status);
    }

    /**
     * Return a paginated JSON response.
     * Conforms to ADR-0008 JSON:API standard with data/meta/error envelope.
     */
    protected function paginatedResponse($paginator): JsonResponse
    {
        return $this->successResponse(
            $paginator->items(),
            [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]
        );
    }
}

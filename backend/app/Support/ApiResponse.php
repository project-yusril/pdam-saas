<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * ApiResponse — format response API standar (PRD 0.5).
 * Semua endpoint mengembalikan bentuk konsisten:
 *   sukses : { "success": true, "data": ..., "meta": {...}|null }
 *   error  : { "success": false, "error": { "code": ..., "message": ..., "details": ... } }
 */
class ApiResponse
{
    /** Response sukses data tunggal / arbitrary. */
    public static function success(mixed $data = null, ?array $meta = null, int $status = 200): JsonResponse
    {
        $payload = ['success' => true, 'data' => $data];
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /** Response sukses dengan pesan (mis. aksi tanpa body data). */
    public static function message(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /** Response dari paginator: data + meta pagination standar. */
    public static function paginated(LengthAwarePaginator $paginator, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ], $status);
    }

    /** Response error standar. */
    public static function error(string $code, string $message, mixed $details = null, int $status = 400): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== null) {
            $error['details'] = $details;
        }

        return response()->json(['success' => false, 'error' => $error], $status);
    }
}

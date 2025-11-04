<?php

declare(strict_types = 1);

namespace App\Http\Utilities;

use Illuminate\Http\JsonResponse;

class ResponseFormatter
{
    /**
     * Formats the given service response into a JSON response.
     *
     * This method processes a ServiceResponse instance into an HTTP JSON response
     * containing structured data and the appropriate status code.
     *
     * @param ServiceResponse $serviceResponse The response object from the service layer, containing
     *                                         data and status details.
     *
     * @return JsonResponse The JSON HTTP response with the structured data.
     */
    public static function format(ServiceResponse $serviceResponse): JsonResponse
    {
        $response = self::prepareResponse($serviceResponse);

        return response()->json($response, $response['status']);
    }

    /**
     * Prepares a response array based on the provided ServiceResponse object.
     *
     * Populates the response array with the status, message, and data from the
     * ServiceResponse instance. If the service response is not successful and
     * the message is empty, null, or '0', the exception message from the thrown
     * exception is used as the response message.
     *
     * @param ServiceResponse $serviceResponse An instance of ServiceResponse containing the response details.
     * @return array<string, mixed> The prepared response array with status, message, and data.
     */
    private static function prepareResponse(ServiceResponse $serviceResponse): array
    {
        $response = [
            'status'  => $serviceResponse->status,
            'message' => $serviceResponse->message,
            'data'    => $serviceResponse->data,
        ];

        if (! $serviceResponse->success && in_array($serviceResponse->message, [null, '', '0'], true)) {
            $response['message'] = $serviceResponse->throw->getMessage();
        }

        return $response;
    }

    /**
     * Formats the given service response into a JSON response with optional cookie handling.
     *
     * This method processes a ServiceResponse instance into an HTTP JSON response.
     * If the service response is successful and contains cookie information, a cookie
     * is attached to the response with the specified attributes. Otherwise, it returns
     * a JSON response with the appropriate status.
     *
     * @param ServiceResponse $serviceResponse The response object from the service layer containing
     *                                         data, status, and optional cookie details.
     *
     * @return JsonResponse The JSON HTTP response containing the structured data and
     *                      optional cookie.
     */
    public static function formatWithCookie(ServiceResponse $serviceResponse): JsonResponse
    {
        $response = self::prepareResponse($serviceResponse);

        if ($serviceResponse->success && $serviceResponse->cookie !== []) {
            return response()
                ->json($response, $response['status'])
                ->cookie(
                    $serviceResponse->cookie['name'],
                    $serviceResponse->cookie['value'],
                    $serviceResponse->cookie['minutes'],
                    $serviceResponse->cookie['path'],
                    $serviceResponse->cookie['domain'],
                    $serviceResponse->cookie['secure'],
                    $serviceResponse->cookie['httpOnly'],
                    $serviceResponse->cookie['raw'],
                    $serviceResponse->cookie['sameSite'],
                );
        }

        return response()->json($response, $response['status']);
    }
}

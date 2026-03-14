<?php

namespace App\Utils;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Standardized API Response Trait
 *
 * Provides consistent response formats for all API endpoints
 */
trait Http
{
    /**
     * Success response
     *
     * @param array $data
     * @param string|null $message
     * @param int $code
     * @return JsonResponse
     */
    protected function success(array $data, ?string $message = null, int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        // Remove null message
        if ($message === null) {
            unset($response['message']);
        }

        return response()->json($response, $code);
    }

    /**
     * Error response
     *
     * @param string $message
     * @param int $code
     * @param array|null $errors
     * @return JsonResponse
     */
    protected function error(string $message, int $code = 400, ?array $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => $code,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Alias for success response (backward compatibility)
     *
     * @param array $data
     * @param int $code
     * @return JsonResponse
     */
    protected function response(array $data, int $code = 200): JsonResponse
    {
        return $this->success($data, null, $code);
    }

    /**
     * Alias for error response (backward compatibility)
     *
     * @param mixed $error
     * @param int $code
     * @return JsonResponse
     */
    protected function failed($error, int $code = 400): JsonResponse
    {
        $message = is_string($error) ? $error : 'An error occurred';
        $errors = is_array($error) ? $error : null;

        return $this->error($message, $code, $errors);
    }

    /**
     * No content response
     *
     * @param int $code
     * @return Response
     */
    protected function responseStatus(int $code = 204): Response
    {
        return response()->noContent($code);
    }

    /**
     * Get HTTP status text
     *
     * @param int $code
     * @return string
     */
    private function getHttpStatusText(int $code): string
    {
        $statusTexts = [
            100 => 'Continue',
            101 => 'Switching Protocols',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            414 => 'URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Range Not Satisfiable',
            417 => 'Expectation Failed',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
        ];

        return $statusTexts[$code] ?? 'Unknown Status';
    }
}

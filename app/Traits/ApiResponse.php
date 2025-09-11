<?php
namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function responseSuccess($data = null, $message = 'Request processed successfully.', $status_code = JsonResponse::HTTP_OK, $status = 'success', $meta_data = [])
    {
        $response = [
            'status'      => $status,
            'status_code' => $status_code,
            'message'     => $message,
        ];
        if (! empty($data)) {
            $response['data'] = $data;
        }
        if (! empty($meta_data)) {
            $response['metadata'] = $meta_data;
        }

        return response()->json($response, $status_code);
    }

    public function responseError($errors, $message = 'The request could not be processed due to an error.', $status_code = JsonResponse::HTTP_BAD_REQUEST, $status = 'error', $meta_data = [])
    {
        $response = [
            'status'      => $status,
            'status_code' => $status_code,
            'message'     => $message,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        if (! empty($meta_data)) {
            $response['metadata'] = $meta_data;
        }

        return response()->json($response, $status_code);
    }

    public function responseInfo($info, $message = 'This is an informational message. No action needed.', $status_code = JsonResponse::HTTP_OK, $status = 'info', $meta_data = [])
    {
        $response = [
            'status'      => $status,
            'status_code' => $status_code,
            'message'     => $message,
            'info'        => $info,
        ];

        if (! empty($meta_data)) {
            $response['metadata'] = $meta_data;
        }

        return response()->json($response, $status_code);
    }

}

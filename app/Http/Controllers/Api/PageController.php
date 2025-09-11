<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\Page;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Page\PageCreateOrUpdateRequest;

class PageController extends Controller
{
    use ApiResponse;

    public function getPage(Request $request)
    {
        $request->validate([
            'type' => 'required|in:Terms & Conditions,Privacy Policy,About Us',
        ]);
        try {
            $page = Page::where('type', $request->type)->first();

            if (!$page) {
                return $this->responseError(null, 'Page not found.', 404);
            }

            $success_message = $request->type . ' retrieved successfully.';
            return $this->responseSuccess($page, $success_message);

        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to retrieved page.', 500);
        }
    }

    public function createOrUpdatePage(PageCreateOrUpdateRequest $request)
    {
        try {
            $page = Page::updateOrCreate(
                ['type' => $request->type],
                ['text' => $request->text]
            );

            $success_message = $request->type . ($page->wasRecentlyCreated ? ' has been added successfully.' : ' has been updated successfully.');
            $status_code = $page->wasRecentlyCreated ? 201 : 200;

            return $this->responseSuccess($page, $success_message, $status_code);
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to create or update page.', 500);
        }
    }
}

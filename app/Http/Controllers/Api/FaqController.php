<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\Faq;
use App\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Faq\FaqAddUpdateRequest;


class FaqController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $faqs = Faq::get();
        return $this->responseSuccess($faqs, 'FAQs retrieved successfully.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FaqAddUpdateRequest $request)
    {
        try {
            $faq = new Faq();
            $faq->question = $request->question;
            $faq->answer = $request->answer;
            $faq->save();

            return $this->responseSuccess($faq, 'FAQ has been added successfully.', 201);
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to add FAQ.', 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(FaqAddUpdateRequest $request, string $id)
    {
        try {
            $faq = Faq::findOrFail($id);
            $faq->question = $request->question;
            $faq->answer = $request->answer;
            $faq->save();

            return $this->responseSuccess($faq, 'FAQ has been updated successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to update FAQ.', 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $faq = Faq::findOrFail($id);
            $faq->delete();

            return $this->responseSuccess(null, 'FAQ has been deleted successfully.', 200);
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to delete FAQ.', 500);
        }
    }
}

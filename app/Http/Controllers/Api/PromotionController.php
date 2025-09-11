<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\PromotionCreateUpdateRequest;
use App\Models\Promotion;
use App\Services\FileUploadService;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    use ApiResponse;
    protected $fileuploadService;
    private $filePath = 'uploads/promotions';
    public function __construct(FileUploadService $fileuploadService)
    {
        $this->fileuploadService = $fileuploadService->setDefaultFiles('default_image.png')->setPath($this->filePath);
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $per_page   = $request->input('per_page') ?? 10;
        $promotions = Promotion::latest('id')->paginate($per_page);
        return $this->responseSuccess($promotions, 'Promotions retrieved successfully.');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PromotionCreateUpdateRequest $request)
    {
        try {
            $promotion = new Promotion();
            if ($request->hasFile('image')) {
                $promotion->image = $this->fileuploadService->saveOptimizedImage($request->file('image'), 40, 512, null, true);
            }
            $promotion->save();

            return $this->responseSuccess($promotion, 'Promotion has been added successfully.', 201);
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to add promotion.', 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PromotionCreateUpdateRequest $request, string $id)
    {
        try {
            $promotion = Promotion::findOrFail($id);
            if ($request->hasFile('image')) {
                $promotion->image = $this->fileuploadService->updateOptimizedImage($request->file('image'), $promotion->image, 40, 512, null, true);
            }
            $promotion->save();
            return $this->responseSuccess($promotion, 'Promotion has been updated successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to update promotion.', 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $promotion = Promotion::findOrFail($id);
            if ($promotion->image) {
                $this->fileuploadService->deleteFile($promotion->image);
            }
            $promotion->delete();
            return $this->responseSuccess($promotion, 'Promotion has been deleted successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to delete promotion.', 500);
        }
    }
}

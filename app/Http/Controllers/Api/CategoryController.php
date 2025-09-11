<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\Category;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Services\FileUploadService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\CategoryStoreRequest;
use App\Http\Requests\Category\CategoryUpdateRequest;


class CategoryController extends Controller
{
    use ApiResponse;
    protected $fileuploadService;
    private $iconPath = 'uploads/categories';
    public function __construct(FileUploadService $fileuploadService)
    {
        $this->fileuploadService = $fileuploadService->setPath($this->iconPath);
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $per_page = $request->input('per_page') ?? 10;
        $categories = Category::latest('id')->paginate($per_page);
        return $this->responseSuccess($categories, 'Categories retrieved successfully.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CategoryStoreRequest $request)
    {
        try {
            $category = new Category();
            $category->name = $request->name;
            if ($request->hasFile('icon')) {
                $category->icon = $this->fileuploadService->saveOptimizedImage($request->file('icon'), 40, 512, null, true);
            }
            $category->save();

            return $this->responseSuccess($category, 'Category has been added successfully.', 201);
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to add category.', 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $category = Category::findOrFail($id);
            return $this->responseSuccess($category, 'Category details retrieved successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to retrieve category details.');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CategoryUpdateRequest $request, string $id)
    {
        try {
            $category = Category::findOrFail($id);
            $category->name = $request->name;
            if ($request->hasFile('icon')) {
                $category->icon = $this->fileuploadService->updateOptimizedImage($request->file('icon'), $category->icon, 40, 512, null, true);
            }
            $category->save();
            return $this->responseSuccess($category, 'Category has been updated successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to update category.', 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $category = Category::findOrFail($id);
            if ($category->icon) {
                $this->fileuploadService->deleteFile($category->icon);
            }
            $category->delete();
            return $this->responseSuccess($category, 'Category has been deleted successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to delete category.', 500);
        }
    }

    public function toggleCategoryStatus($id)
    {
        try {
            $category = Category::findOrFail($id);
            $category->is_active = !$category->is_active;
            $category->save();
            return $this->responseSuccess($category, 'Category status has been updated successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to update category status.');
        }
    }
}

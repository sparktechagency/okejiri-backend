<?php
namespace App\Http\Controllers;

use App\Http\Requests\Provider\PortfolioStoreUpdateRequest;
use App\Models\ProviderPortfolio;
use App\Services\FileUploadService;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProviderPortfolioController extends Controller
{
    use ApiResponse;
    protected $fileuploadService;
    private $filePath    = 'uploads/portfolio';
    private $defaultFile = ['default_image.png'];

    public function __construct(FileUploadService $fileuploadService)
    {
        $this->fileuploadService = $fileuploadService->setPath($this->filePath)->setDefaultFiles($this->defaultFile);
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $per_page   = $request->input('per_page', 10);
        $portfolios = ProviderPortfolio::where('provider_id', Auth::id())->latest('id')->paginate($per_page);
        return $this->responseSuccess($portfolios, 'Portfolio retrieved successfully.');
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
    public function store(PortfolioStoreUpdateRequest $request)
    {
        try {
            $portfolio              = new ProviderPortfolio();
            $portfolio->provider_id = Auth::id();
            if ($request->hasFile('image')) {
                $portfolio->image = $this->fileuploadService->saveOptimizedImage($request->file('image'), 40, 1320, null, true);
            }
            $portfolio->save();

            return $this->responseSuccess($portfolio, 'Portfolio has been added successfully.', 201);
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to add portfolio.', 500);
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
    public function update(PortfolioStoreUpdateRequest $request, string $id)
    {
        try {
            $portfolio              = ProviderPortfolio::findOrFail($id);
            $portfolio->provider_id = Auth::id();
            if ($request->hasFile('image')) {
                $portfolio->image = $this->fileuploadService->updateOptimizedImage($request->file('image'), $portfolio->image, 40, 1320, null, true);
            }
            $portfolio->save();
            return $this->responseSuccess($portfolio, 'Portfolio has been updated successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to update portfolio.', 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $portfolio = ProviderPortfolio::findOrFail($id);
            if ($portfolio->image) {
                $this->fileuploadService->deleteFile($portfolio->image);
            }
            $portfolio->delete();
            return $this->responseSuccess($portfolio, 'Portfolio has been deleted successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to delete portfolio.', 500);
        }
    }
}

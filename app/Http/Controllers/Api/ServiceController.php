<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\NewServiceAddRequest;
use App\Http\Requests\Service\ServiceStoreRequest;
use App\Http\Requests\Service\ServiceUpdateRequest;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Services\FileUploadService;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServiceController extends Controller
{
    use ApiResponse;
    protected $fileuploadService;
    private $filePath = 'uploads/services';
    public function __construct(FileUploadService $fileuploadService)
    {
        $this->fileuploadService = $fileuploadService->setPath($this->filePath);
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search   = $request->input('search');
        $services = Service::withCount('packages')->when($search, function ($query) use ($search) {
            $query->where('name', 'LIKE', '%' . $search . '%');
        })->latest('id')->get();
        $data = [
            'service_requests' => ServiceRequest::count() ?? 0,
            'services'         => $services,
        ];
        return $this->responseSuccess($data, 'Services retrieved successfully.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ServiceStoreRequest $request)
    {
        try {
            $service       = new Service();
            $service->name = $request->name;
            if ($request->hasFile('image')) {
                $service->image = $this->fileuploadService->saveOptimizedImage($request->file('image'), 40, 1320, null, true);
            }
            $service->save();

            return $this->responseSuccess($service, 'Service has been added successfully.', 201);
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to add service.', 500);
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
     * Update the specified resource in storage.
     */
    public function update(ServiceUpdateRequest $request, string $id)
    {
        try {
            $service       = Service::findOrFail($id);
            $service->name = $request->name;
            if ($request->hasFile('image')) {
                $service->image = $this->fileuploadService->updateOptimizedImage($request->file('image'), $service->image, 40, 1320, null, true);
            }
            $service->save();
            return $this->responseSuccess($service, 'Service has been updated successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to update service.', 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $service = Service::findOrFail($id);
            if ($service->image) {
                $this->fileuploadService->deleteFile($service->image);
            }
            $service->delete();
            return $this->responseSuccess($service, 'Service has been deleted successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to delete service.', 500);
        }
    }

    public function requestAddService(NewServiceAddRequest $request)
    {
        try {
            $new_service_request               = new ServiceRequest();
            $new_service_request->service_name = $request->service_name;
            $new_service_request->request_by   = Auth::user()->id;
            $new_service_request->save();

            return $this->responseSuccess($new_service_request, 'Service add request has been sent successfully.', 201);
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to sent request.', 500);
        }
    }
    public function requestedServices(Request $request)
    {
        $per_page        = $request->input('per_page') ?? 10;
        $search          = $request->input('search');
        $service_request = ServiceRequest::with('user:id,name,email,avatar')
            ->when($search, function ($query) use ($search) {
                $query->where('service_name', 'LIKE', '%' . $search . '%')
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'LIKE', '%' . $search . '%')
                            ->orWhere('email', 'LIKE', '%' . $search . '%');
                    });
            })
            ->latest('id')
            ->paginate($per_page);
        return $this->responseSuccess($service_request, 'Request service retrieved successfully.');
    }
    public function deleteRequestedServices(string $id)
    {
        try {
            $service_request = ServiceRequest::findOrFail($id);
            $service_request->delete();
            return $this->responseSuccess($service_request, 'Requested service has been deleted successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to delete requested service.', 500);
        }
    }
    public function addRequestedServices(ServiceStoreRequest $request)
    {
        try {
            $service       = new Service();
            $service->name = $request->name;
            if ($request->hasFile('image')) {
                $service->image = $this->fileuploadService->saveOptimizedImage($request->file('image'), 40, 1320, null, true);
            }
            $service->save();
            $service_request = ServiceRequest::findOrFail($request->requested_service_id);
            $service_request->delete();
            return $this->responseSuccess($service, 'Service has been added successfully.', 201);
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to add service.', 500);
        }
    }
}

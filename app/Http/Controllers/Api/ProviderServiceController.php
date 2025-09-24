<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Package\AddMyServicePackageRequest;
use App\Models\Package;
use App\Models\PackageAvailableTime;
use App\Models\PackageDetail;
use App\Services\FileUploadService;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProviderServiceController extends Controller
{
    use ApiResponse;

    protected $fileuploadService;
    private $filePath = 'uploads/packages';
    public function __construct(FileUploadService $fileuploadService)
    {
        $this->fileuploadService = $fileuploadService->setPath($this->filePath);
    }
    public function myServicePackage(Request $request)
    {
        $request->validate([
            'service_id' => 'required|nullable',
        ]);
        $per_page = $request->input('per_page', 10);

        $packages = Package::with('package_detail_items')->where('provider_id', Auth::id())->latest('id')->paginate($per_page);

        return $this->responseSuccess($packages, 'My Service Packages retrieved successfully.');
    }

    public function addMyServicePackage(AddMyServicePackageRequest $request)
    {
        DB::beginTransaction();

        try {
            $package              = new Package();
            $package->provider_id = Auth::id();
            $package->service_id  = $request->service_id;
            $package->title       = $request->title;
            $package->image       = $this->fileuploadService->saveOptimizedImage(
                $request->file('image'),
                40,
                1320,
                null,
                true
            );
            $package->price         = $request->price;
            $package->delivery_time = $request->delivery_time;
            $package->save();

            if ($package && $request->service_details && is_array($request->service_details)) {
                foreach ($request->service_details as $item) {
                    PackageDetail::create([
                        'package_id' => $package->id,
                        'item'       => $item,
                    ]);
                }
            }

            $fromTimes = $request->available_time_from;
            $toTimes   = $request->available_time_to;

            foreach ($fromTimes as $index => $from) {
                $to = $toTimes[$index];

                PackageAvailableTime::create([
                    'package_id'          => $package->id,
                    'available_time_from' => $from,
                    'available_time_to'   => $to,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Package created successfully!',
                'package' => $package,
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Something went wrong while creating the package.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
       public function myServicePackageDetails(Request $request,$package_id)
    {
        $packages = Package::with('package_detail_items','available_time')->where('id', $package_id)->first();

        return $this->responseSuccess($packages, 'Packages details retrieved successfully.');
    }

}

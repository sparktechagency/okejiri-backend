<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Package\AddMyServicePackageRequest;
use App\Http\Requests\Package\AddServiceAvailableTimeRequest;
use App\Http\Requests\Package\UpdateServiceAvailableTimeRequest;
use App\Http\Requests\Package\UpdateServicePackageRequest;
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

        $packages = Package::with(['package_detail_items' => function ($q) {
            $q->latest('id');
        }])->where('provider_id', Auth::id())->where('service_id',$request->service_id)->latest('id')->paginate($per_page);

        return $this->responseSuccess($packages, 'My Service Packages retrieved successfully.');
    }

    public function addMyServicePackage(AddMyServicePackageRequest $request)
    {
        $check_already_connected = Auth::user();
        if (! $check_already_connected ||
            ! $check_already_connected->stripe_account_id ||
            ! $check_already_connected->stripe_payouts_enabled) {
            return $this->responseError(null, 'You are not connected with the app. Please create a connected account first.');
        }
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

            return $this->responseSuccess($package, 'Package created successfully.', 201);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->responseError($e->getMessage());
        }
    }

    public function myServicePackageDetails(Request $request, $package_id)
    {
        $packages = Package::with(['package_detail_items' => function ($q) {
            $q->latest('id');
        }, 'available_time' => function ($q) {
            $q->latest('id');
        }])->where('id', $package_id)->first();

        return $this->responseSuccess($packages, 'Packages details retrieved successfully.');
    }

    public function myServicePackageEdit(UpdateServicePackageRequest $request, $package_id)
    {
        $check_already_connected = Auth::user();
        if (! $check_already_connected ||
            ! $check_already_connected->stripe_account_id ||
            ! $check_already_connected->stripe_payouts_enabled) {
            return $this->responseError(null, 'You are not connected with the app. Please create a connected account first.');
        }
        try {
            $package        = Package::findOrFail($package_id);
            $package->title = $request->title;
            $package->image = $this->fileuploadService->updateOptimizedImage(
                $request->file('image'),
                $package->image,
                40,
                1320,
                null,
                true
            );
            $package->price         = $request->price;
            $package->delivery_time = $request->delivery_time;
            $package->save();
            return $this->responseSuccess($package, 'Package updated successfully.', 200);
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function addServicePackageItem(Request $request)
    {
        $request->validate([
            'package_id' => 'required|exists:packages,id',
            'item'       => 'required|string|max:255',
        ]);
        try {
            $item = PackageDetail::create([
                'package_id' => $request->package_id,
                'item'       => $request->item,
            ]);
            return $this->responseSuccess($item, 'Package item added successfully.', 201);
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }
    public function deleteServicePackageItem($package_item_id)
    {
        try {
            $item = PackageDetail::findOrFail($package_item_id);
            $item->delete();
            return $this->responseSuccess($item, 'Package item deleted successfully.', 200);
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function addServiceAvailableTime(AddServiceAvailableTimeRequest $request)
    {

        try {
            $available_time = PackageAvailableTime::create([
                'package_id'          => $request->package_id,
                'available_time_from' => $request->available_time_from,
                'available_time_to'   => $request->available_time_to,
            ]);
            return $this->responseSuccess($available_time, 'Package available time added successfully.', 201);
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }
    public function updateServiceAvailableTime(UpdateServiceAvailableTimeRequest $request, $package_id)
    {
        try {
            $available_time = PackageAvailableTime::findOrFail($package_id);

            $available_time->available_time_from = $request->available_time_from;
            $available_time->available_time_to   = $request->available_time_to;

            $available_time->save();

            return $this->responseSuccess($available_time, 'Package available time updated successfully.', 200);
        } catch (\Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

}

<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\ManageDiscountRequest;
use App\Models\ProviderService;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProviderController extends Controller
{
    use ApiResponse;

    public function manageDiscounts(ManageDiscountRequest $request)
    {
        $user           = Auth::user();
        $user->discount = $request->discount_amount;
        $user->save();
        return $this->responseSuccess($user, 'Discounts updated successfully');
    }

    public function myServices(Request $request)
    {
        $per_page    = $request->input('per_page', 10);
        $my_services = ProviderService::with('service')->where('provider_id', Auth::id())->latest('id')->paginate($per_page);
        return $this->responseSuccess($my_services, 'My services fetched successfully');
    }

    public function deleteMyServices(Request $request, $provider_service_id)
    {
        try {
            $my_services = ProviderService::findOrFail($provider_service_id);
            $my_services->delete();
            return $this->responseSuccess($my_services, 'My service deleted successfully');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function addNewServices(Request $request)
    {
        $request->validate([
            'service_id' => 'required|numeric',
        ]);
        try {
            $already_exists = ProviderService::where('provider_id', Auth::id())->where('service_id', $request->service_id)->first();
            if ($already_exists) {
                return $this->responseError('Service already added');
            } else {
                $new_service              = new ProviderService();
                $new_service->provider_id = Auth::id();
                $new_service->service_id  = $request->service_id;
                $new_service->save();
                return $this->responseSuccess($new_service, 'New service added successfully');
            }
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }
}

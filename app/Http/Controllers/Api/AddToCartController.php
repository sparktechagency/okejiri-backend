<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AddToCart;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AddToCartController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $cart_data = AddToCart::with('package','package.available_time')->where('user_id', Auth::id())->get();
        return $this->responseSuccess($cart_data, 'Cart data retrieved successfully.');
    }

    public function storeOrDelete(Request $request)
    {
        $validate = $request->validate([
            'package_id' => 'required|numeric|exists:packages,id',
        ]);
        $package_id    = $request->input('package_id');
        $already_exist = AddToCart::where('package_id', $package_id)->first();
        if ($already_exist) {
            $already_exist->delete();
            return $this->responseSuccess($already_exist, 'Package removed from the cart.');
        }
        $new_entry = AddToCart::create([
            'user_id'    => Auth::id(),
            'package_id' => $package_id,
        ]);
        return $this->responseSuccess($new_entry, 'Package added to the cart.');
    }

    public function deleteAllCartItem()
    {
        $cart_data = AddToCart::where('user_id', Auth::id())->get();
        foreach ($cart_data as $data) {
            $data->delete();
        }
        return $this->responseSuccess($cart_data, 'Current cart item has been removed successfully.');
    }
}

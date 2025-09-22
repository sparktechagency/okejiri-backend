<?php

namespace App\Http\Controllers\Api;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Provider\ManageDiscountRequest;

class ProviderController extends Controller
{
    use ApiResponse;

    public function manageDiscounts(ManageDiscountRequest $request){
        $user=Auth::user();
        $user->discount=$request->discount_amount;
        $user->save();
        return $this->responseSuccess($user,'Discounts updated successfully');
    }
}

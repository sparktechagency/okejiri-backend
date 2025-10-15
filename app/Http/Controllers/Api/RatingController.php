<?php
namespace App\Http\Controllers\Api;

use App\Models\Rating;
use App\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Rating\RatingStoreRequest;

class RatingController extends Controller
{
    use ApiResponse;

    public function create(RatingStoreRequest $request)
    {
        $already_exist = Rating::where('user_id', Auth::id())
            ->where('booking_id', $request->booking_id)
            ->exists();

        if ($already_exist) {
            return $this->responseError(null, 'You have already rated this booking.', 409);
        }

        $rating = Rating::create([
            'user_id'     => Auth::id(),
            'booking_id'  => $request->booking_id,
            'provider_id' => $request->provider_id,
            'package_id'  => null,
            'rating'      => $request->rating,
            'review'      => $request->review,
        ]);

        return $this->responseSuccess($rating, 'Rating submitted successfully.');
    }
}

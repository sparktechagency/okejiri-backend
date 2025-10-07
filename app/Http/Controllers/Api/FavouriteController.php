<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Favorite\StoreFavoriteRequest;
use App\Models\Favorite;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavouriteController extends Controller
{
    use ApiResponse;
    public function index(Request $request)
    {
        $userId  = Auth::id();
        $perPage = $request->input('per_page', 10);

        $favorites = Favorite::with([
            'package' => function ($q) {
                $q->with(['service:id,name,image'])
                    ->with(['provider' => function ($p) {
                        $p->select('id', 'name', 'avatar', 'latitude', 'longitude', 'kyc_status')
                            ->withCount('ratings')
                            ->withAvg('ratings', 'rating');
                    }]);
            },
        ])
            ->where('user_id', $userId)
            ->latest()
            ->paginate($perPage);
        $favorites->getCollection()->transform(function ($fav) {
            if ($fav->package && $fav->package->provider) {
                $provider = $fav->package->provider;

                $provider->ratings_avg_rating = $provider->ratings_avg_rating !== null
                    ? number_format($provider->ratings_avg_rating, 1)
                    : number_format(0, 1);

                $provider->ratings_count = $provider->ratings_count ?? 0;
            }

            return $fav;
        });

        return $this->responseSuccess($favorites, 'Favorite package retrieved successfully.');
    }

    public function store(StoreFavoriteRequest $request)
    {
        $userId    = Auth::id();
        $packageId = $request->package_id;

        $exists = Favorite::where('user_id', $userId)
            ->where('package_id', $packageId)
            ->exists();

        if ($exists) {
            return $this->responseError('This package is already in your favorites.', 409);
        }

    $favorite=   Favorite::create([
            'user_id'    => $userId,
            'package_id' => $packageId,
        ]);

        return $this->responseSuccess($favorite, 'Package added to favorites successfully.');
    }
    public function destroy($package_id)
    {
        $userId = Auth::id();

        $favorite = Favorite::where('user_id', $userId)
            ->where('package_id', $package_id)
            ->first();

        if (! $favorite) {
            return $this->responseError('This package is not in your favorites.', 404);
        }

        $favorite->delete();

        return $this->responseSuccess($favorite, 'Package removed from favorites successfully.');
    }

}

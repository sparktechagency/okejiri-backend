<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Employee;
use App\Models\EmployeeServiceCompletion;
use App\Models\Favorite;
use App\Models\Package;
use App\Models\ProviderPortfolio;
use App\Models\Rating;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    use ApiResponse;

    public function getPackages(Request $request, $service_id)
    {
        $per_page        = $request->input('per_page', 10);
        $boostedPackages = Package::with([
            'service:id,name',
            'provider' => function ($q) {
                $q->select('id', 'name', 'latitude', 'longitude', 'kyc_status', 'is_boosted')->withAvg('ratings', 'rating');
            },
        ])
            ->where('service_id', $service_id)
            ->where('is_suspend', 0)
            ->whereHas('provider', function ($q) {
                $q->where('is_boosted', 1);
            })
            ->latest('id')
        // ->withCount('package_ratings')
        // ->withAvg('package_ratings', 'rating')
            ->take(4)
            ->get()
            ->transform(function ($package) {
                // $avg = $package->package_ratings_avg_rating;
                // $package->package_ratings_avg_rating = number_format($avg ?? 0, 1, '.', '');
                if ($package->provider) {
                    $avg                                   = $package->provider->ratings_avg_rating;
                    $package->provider->ratings_avg_rating = $avg
                        ? number_format($avg, 1)
                        : number_format(0, 1);
                }
                return $package;
            });

        $normalPackages = Package::with([
            'service:id,name',
            'provider' => function ($q) {
                $q->select('id', 'name', 'latitude', 'longitude', 'kyc_status', 'is_boosted');
            },
        ])
            ->where('service_id', $service_id)
            ->where('is_suspend', 0)
            ->whereHas('provider', function ($q) {
                $q->where('is_boosted', 0);
            })
            ->latest('id')
        // ->withCount('package_ratings')
        // ->withAvg('package_ratings', 'rating')
            ->paginate($per_page);

        $normalPackages->getCollection()->transform(function ($package) {
            // $avg                                 = $package->package_ratings_avg_rating;
            // $package->package_ratings_avg_rating = number_format($avg ?? 0, 1, '.', '');

            if ($package->provider) {
                $avg                                   = $package->provider->ratings_avg_rating;
                $package->provider->ratings_avg_rating = $avg
                    ? number_format($avg, 1)
                    : number_format(0, 1);
            }
            return $package;
        });

        $mergedPackages = collect($boostedPackages)
            ->merge($normalPackages->items())
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Packages retrieved successfully',
            'data'    => [
                'packages'   => $mergedPackages,
                'pagination' => [
                    'current_page' => $normalPackages->currentPage(),
                    'last_page'    => $normalPackages->lastPage(),
                    'per_page'     => $normalPackages->perPage(),
                    'total'        => $normalPackages->total(),
                ],
            ],
        ]);
    }

    public function getPackageDetail(Request $request, $package_id)
    {
        $package = Package::with([
            'provider' => function ($query) {
                $query->select('id', 'name', 'avatar', 'kyc_status', 'latitude', 'longitude')
                    ->withAvg('ratings', 'rating')
                    ->withCount('ratings');
            },
            'package_detail_items',

        ])->findOrFail($package_id);

        $user = Auth::user();
        if (! $user || ! $user->latitude || ! $user->longitude) {
            return $this->responseError('User location not available', 422);
        }

        $user_latitude  = $user->latitude;
        $user_longitude = $user->longitude;

        if ($package->provider) {
            $package->provider->ratings_avg_rating = $package->provider->ratings_avg_rating !== null
                ? number_format($package->provider->ratings_avg_rating, 1)
                : number_format(0, 1);

            $package->provider->ratings_count = $package->provider->ratings_count ?? 0;
        }

        // // Distance calculate
        $distanceText = null;
        if (
            $package->provider &&
            ! empty($package->provider->latitude) && is_numeric($package->provider->latitude) &&
            ! empty($package->provider->longitude) && is_numeric($package->provider->longitude)
        ) {
            $distanceInKm = $this->calculateDistance(
                $user_latitude,
                $user_longitude,
                $package->provider->latitude,
                $package->provider->longitude
            );

            $distanceText = $this->formatDistance($distanceInKm);
        }

        // // Reviews & Portfolio
        $reviews    = Rating::with('user:id,name,email,avatar')->where('provider_id', $package->provider->id)->latest('id')->take(5)->get();
        $portfolios = ProviderPortfolio::where('provider_id', $package->provider->id)->latest('id')->take(5)->get();

        // // More services from this provider
        $more_services_from_this_provider = Package::with('package_detail_items')->where('provider_id', $package->provider->id)
            ->where('is_suspend', 0)
        // ->withCount('package_ratings')
        // ->withAvg('package_ratings', 'rating')
            ->take(5)
            ->inRandomOrder()
            ->get();

        // You might also like
        $you_might_also_like = Package::with([
            'service:id,name',
            'provider' => function ($q) {
                $q->select('id', 'name', 'kyc_status')
                    ->withAvg('ratings', 'rating');
                ;
            },
        ])
            ->whereNot('provider_id', $package->provider->id)
            ->where('is_suspend', 0)
            ->take(5)
        // ->withCount('package_ratings')
        // ->withAvg('package_ratings', 'rating')
            ->inRandomOrder()
            ->get();

        // $more_services_from_this_provider = $more_services_from_this_provider->transform(function ($service) {

        //     $service->package_ratings_avg_rating = $service->package_ratings_avg_rating !== null
        //         ? number_format($service->package_ratings_avg_rating, 1)
        //         : number_format(0, 1);
        //     $service->package_ratings_count = $service->package_ratings_count ?? 0;
        //     return $service;
        // });

        $you_might_also_like = $you_might_also_like->transform(function ($service) {

            // $service->package_ratings_avg_rating = $service->package_ratings_avg_rating !== null
            //     ? number_format($service->package_ratings_avg_rating, 1)
            //     : number_format(0, 1);
            // $service->package_ratings_count = $service->package_ratings_count ?? 0;
            if ($service->provider) {
                $avg                                   = $service->provider->ratings_avg_rating;
                $service->provider->ratings_avg_rating = $avg
                    ? number_format($avg, 1)
                    : number_format(0, 1);
            }
            return $service;
        });

        $data = [
            'is_favorite'                      => $this->isFavourite($package_id),
            'package_details'                  => $package,
            'distance'                         => $distanceText,
            'reviews'                          => $reviews,
            'portfolio'                        => $portfolios,
            'more_services_from_this_provider' => $more_services_from_this_provider,
            'you_might_also_like'              => $you_might_also_like,
        ];

        return $this->responseSuccess($data, 'Package details retrieved successfully');
    }

    protected function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371;

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $latDiff = $lat2 - $lat1;
        $lonDiff = $lon2 - $lon1;

        $a = sin($latDiff / 2) ** 2 +
        cos($lat1) * cos($lat2) * sin($lonDiff / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    protected function formatDistance($distanceInKm)
    {
        if ($distanceInKm < 1) {
            return round($distanceInKm * 1000) . ' m away';
        } else {
            return number_format($distanceInKm, 1) . ' km away';
        }
    }

    public function isFavourite($package_id)
    {
        $userId = auth()->id();

        return Favorite::where('user_id', $userId)
            ->where('package_id', $package_id)
            ->exists();
    }

    public function getProviderPortfolio(Request $request, $provider_id)
    {
        $per_page   = $request->input('per_page', 10);
        $portfolios = ProviderPortfolio::where('provider_id', $provider_id)->paginate($per_page);
        return $this->responseSuccess($portfolios, 'Portfolios retrieved successfully');
    }

    public function getProviderProfile(Request $request, $provider_id)
    {
        $profile = User::with([
            'provider_services.service:id,name,image',
            'company',
        ])
            ->withCount('ratings')
            ->withAvg('ratings', 'rating')
            ->where('id', $provider_id)
            ->first();

        if (! $profile) {
            return $this->responseError(null, 'Provider not found', 404);
        }

        $profile->ratings_avg_rating = $profile->ratings_avg_rating !== null
            ? number_format($profile->ratings_avg_rating, 1)
            : number_format(0, 1);

        $profile->ratings_count = $profile->ratings_count ?? 0;

        return $this->responseSuccess($profile, 'Provider profile retrieved successfully');
    }

    public function getProviderReview(Request $request, $provider_id)
    {
        $reviews = Rating::with('user:id,name,email,avatar')->where('provider_id', $provider_id)->take(5)->latest('id')->get();
        return $this->responseSuccess($reviews, 'Provider ratings retrieved successfully');
    }
    public function getProviderServices(Request $request, $provider_id)
    {

        $packages = Package::with([
            'service:id,name',
            'provider' => function ($q) {
                $q->select('id', 'name', 'latitude', 'longitude', 'kyc_status')
                    ->withAvg('ratings', 'rating');
                ;
            },
        ])
            ->where('provider_id', $provider_id)
            ->where('is_suspend', 0)
        // ->withCount('package_ratings')
        // ->withAvg('package_ratings', 'rating')
            ->get();
        $packages = $packages->transform(function ($service) {

            $service->provider->ratings_avg_rating = $service->provider->ratings_avg_rating !== null
                ? number_format($service->provider->ratings_avg_rating, 1)
                : number_format(0, 1);
            return $service;
        });
        return $this->responseSuccess($packages, 'Provider package retrieved successfully');
    }

    public function homeData(Request $request)
    {
        $request->validate([
            'filter' => 'required|string|in:this_week,this_month,this_year',
        ]);
        $data       = [];
        $providerId = Auth::id();
        $now        = now();
        if ($request->filter === 'this_week') {
            $startDate = $now->startOfWeek();
        } elseif ($request->filter === 'this_month') {
            $startDate = $now->startOfMonth();
        } else {
            $startDate = $now->startOfYear();
        }

        $totalAmount = Transaction::where('receiver_id', $providerId)
            ->where('created_at', '>=', $startDate)
            ->sum('amount');

        $totalProfit = Transaction::where('receiver_id', $providerId)
            ->where('created_at', '>=', $startDate)
            ->sum('profit');


        $totalEarnings = $totalAmount - $totalProfit;
        $data          = [
            'total_earnings'     => $totalEarnings,
            'total_employee'     => Employee::where('provider_id', $providerId)->count(),
            'new_order'          => Booking::where('provider_id', $providerId)->where('status', 'New')->count(),
            'pending_order'      => Booking::where('provider_id', $providerId)->where('status', 'Pending')->count(),
            'completed_order'    => Booking::where('provider_id', $providerId)->where('status', 'Completed')->count(),
        ];

        return $this->responseSuccess($data, 'Home page data retrieved successfully');
    }

}

<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class ServiceNearbyController extends Controller
{
    use ApiResponse;

    public function servicesNearby(Request $request)
    {
        $perPage   = $request->input('per_page', 10);
        $latitude  = Auth::user()->latitude;
        $longitude = Auth::user()->longitude;
        $radius    = $request->input('radius', 5);

        $packages = Package::with([
            'service:id,name',
            'provider' => function ($q) {
                $q->select('id', 'name', 'latitude', 'longitude', 'kyc_status');
            },
        ])
        ->withAvg('package_ratings', 'rating')
        ->where('is_suspend',0)
            ->get()
            ->map(function ($package) use ($latitude, $longitude) {
                if ($package->provider && $package->provider->latitude && $package->provider->longitude) {
                    $distanceKm = $this->calculateDistance(
                        $latitude,
                        $longitude,
                        $package->provider->latitude,
                        $package->provider->longitude
                    );

                    if ($distanceKm < 1) {
                        $package->distance_text = round($distanceKm * 1000) . ' m';
                    } else {
                        $package->distance_text = round($distanceKm, 2) . ' km';
                    }

                    $package->distance = $distanceKm;
                } else {
                    $package->distance      = null;
                    $package->distance_text = null;
                }
                    $avg                                   = $package->package_ratings_avg_rating;
                    $package->package_ratings_avg_rating = $avg
                        ? number_format($avg, 1)
                        : number_format(0, 1);

                return $package;
            })
            ->filter(fn($p) => $p->distance !== null && $p->distance <= $radius)
            ->sortBy('distance')
            ->values();

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $pagedData   = $packages->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginated = new LengthAwarePaginator(
            $pagedData,
            $packages->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->responseSuccess($paginated, 'Nearby packages retrieved successfully.');
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km
        $latDelta    = deg2rad($lat2 - $lat1);
        $lonDelta    = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}

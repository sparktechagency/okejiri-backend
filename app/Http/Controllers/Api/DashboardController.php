<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;
    /**
     * Handle the incoming request.
     */
    // public function __invoke(Request $request)
    // {
    //     $request->validate([
    //         'filter' => 'nullable|string|in:All,Last7Days,Last15Days,Last30Days,Last3Month,Last6Month,Last1Year',
    //     ]);
    //     $filter    = $request->filter ?? 'All';
    //     $startDate = match ($filter) {
    //         'Last7Days'  => now()->subDays(7),
    //         'Last15Days' => now()->subDays(15),
    //         'Last30Days' => now()->subDays(30),
    //         'Last3Month' => now()->subMonths(3),
    //         'Last6Month' => now()->subMonths(6),
    //         'Last1Year'  => now()->subYear(),
    //         default      => null,
    //     };

    //     $userQuery     = User::where('role', 'USER');
    //     $providerQuery = User::where('role', 'PROVIDER');
    //     $bookingQuery  = Booking::whereIn('status', ['New', 'Pending', 'Completed']);
    //     $earningsQuery = Transaction::where('transaction_type', 'purchase');

    //     if ($startDate) {
    //         $userQuery->where('created_at', '>=', $startDate);
    //         $providerQuery->where('created_at', '>=', $startDate);
    //         $bookingQuery->where('created_at', '>=', $startDate);
    //         $earningsQuery->where('created_at', '>=', $startDate);
    //     }
    //     $data = [
    //         'users'          => $userQuery->count(),
    //         'total_bookings' => $bookingQuery->count(),
    //         'providers'      => $providerQuery->count(),
    //         'earnings'       => $earningsQuery->sum('profit'),
    //         // 'user_statistics'=>
    //     ];

    // // User statistics
    // $userStats = [
    //     'total'        => User::where('role', 'USER')->count(),
    //     'verified'     => User::where('role', 'USER')->where('kyc_status', 'Verified')->count(),
    //     'unverified'   => User::where('role', 'USER')->where('kyc_status', 'Pending')->count(),
    //     'new'          => User::where('role', 'USER')
    //                         ->where('created_at', '>=', now()->subDays(30))
    //                         ->count(),
    // ];

    // // Provider statistics
    // $providerStats = [
    //     'total'        => User::where('role', 'PROVIDER')->count(),
    //     'verified'     => User::where('role', 'PROVIDER')->where('kyc_status', 'Verified')->count(),
    //     'unverified'   => User::where('role', 'PROVIDER')->where('kyc_status', 'Pending')->count(),
    //     'new'          => User::where('role', 'PROVIDER')
    //                         ->where('created_at', '>=', now()->subDays(30))
    //                         ->count(),
    // ];

    // // Registration stats (auto group)
    // $userRegistrationStats = User::selectRaw("DATE_FORMAT(created_at, '{$groupByFormat}') as period, COUNT(*) as total")
    //     ->where('role', 'USER')
    //     ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
    //     ->groupBy('period')
    //     ->orderBy('period', 'asc')
    //     ->get();

    // $providerRegistrationStats = User::selectRaw("DATE_FORMAT(created_at, '{$groupByFormat}') as period, COUNT(*) as total")
    //     ->where('role', 'PROVIDER')
    //     ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
    //     ->groupBy('period')
    //     ->orderBy('period', 'asc')
    //     ->get();

    // // Final response
    // $response = [
    //     'dashboard' => $dashboard,
    //     'statistics' => [
    //         'users'     => $userStats,
    //         'providers' => $providerStats,
    //     ],
    //     'registration_statistics' => [
    //         'users'     => $userRegistrationStats,
    //         'providers' => $providerRegistrationStats,
    //     ],
    // ];
    //     return $this->responseSuccess($response, 'Dashboard data retrieved successfully.');
    // }

    public function __invoke(Request $request)
    {
        $request->validate([
            'filter' => 'nullable|string|in:All,Last7Days,Last15Days,Last30Days,Last3Month,Last6Month,Last1Year',
        ]);

        $filter = $request->filter ?? 'All';

        $startDate     = null;
        $groupByFormat = '%Y-%m-%d';

        switch ($filter) {
            case 'Last7Days':
                $startDate     = now()->subDays(7);
                $groupByFormat = '%Y-%m-%d';
                break;
            case 'Last15Days':
                $startDate     = now()->subDays(15);
                $groupByFormat = '%Y-%m-%d';
                break;
            case 'Last30Days':
                $startDate     = now()->subDays(30);
                $groupByFormat = '%Y-%m-%d';
                break;
            case 'Last3Month':
                $startDate     = now()->subMonths(3);
                $groupByFormat = '%Y-%m-%W';
                break;
            case 'Last6Month':
                $startDate     = now()->subMonths(6);
                $groupByFormat = '%Y-%m';
                break;
            case 'Last1Year':
                $startDate     = now()->subYear();
                $groupByFormat = '%Y-%m';
                break;
        }

        $userQuery     = User::where('role', 'USER');
        $providerQuery = User::where('role', 'PROVIDER');
        $bookingQuery  = Booking::whereIn('status', ['New', 'Pending', 'Completed']);
        $earningsQuery = Transaction::where('transaction_type', 'purchase');

        if ($startDate) {
            $userQuery->where('created_at', '>=', $startDate);
            $providerQuery->where('created_at', '>=', $startDate);
            $bookingQuery->where('created_at', '>=', $startDate);
            $earningsQuery->where('created_at', '>=', $startDate);
        }

        $dashboard = [
            'total_users'     => $userQuery->count(),
            'total_bookings'  => $bookingQuery->count(),
            'total_providers' => $providerQuery->count(),
            'total_earnings'  => $earningsQuery->sum('profit'),
        ];

        $userRegistrations = User::selectRaw("DATE_FORMAT(created_at, '{$groupByFormat}') as period, COUNT(*) as user_total")
            ->where('role', 'USER')
            ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
            ->groupBy('period')
            ->pluck('user_total', 'period')
            ->toArray();

        $providerRegistrations = User::selectRaw("DATE_FORMAT(created_at, '{$groupByFormat}') as period, COUNT(*) as provider_total")
            ->where('role', 'PROVIDER')
            ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
            ->groupBy('period')
            ->pluck('provider_total', 'period')
            ->toArray();

        $allPeriods = collect(array_unique(array_merge(array_keys($userRegistrations), array_keys($providerRegistrations))))->sort();

        $userProviderStats = $allPeriods->map(function ($period) use ($userRegistrations, $providerRegistrations) {
            return [
                'period'         => $period,
                'user_total'     => $userRegistrations[$period] ?? 0,
                'provider_total' => $providerRegistrations[$period] ?? 0,
            ];
        })->values();

        $earningsStats = Transaction::selectRaw("DATE_FORMAT(created_at, '{$groupByFormat}') as period, SUM(profit) as total_profit")
            ->where('transaction_type', 'purchase')
            ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(function ($item) {
                return [
                    'period'       => $item->period,
                    'total_profit' => (float) $item->total_profit,
                ];
            });

        $response = [
            'dashboard'           => $dashboard,
            'user_statistics'     => $userProviderStats,
            'earnings_statistics' => $earningsStats,
        ];

        return $this->responseSuccess($response, 'Dashboard data retrieved successfully.');
    }

}

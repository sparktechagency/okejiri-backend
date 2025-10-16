<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ReportActionRequest;
use App\Http\Requests\Report\ReportStoreRequest;
use App\Models\Package;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\User;
use App\Notifications\NewReportNotification;
use App\Notifications\ReportWarningNotification;
use App\Services\FileUploadService;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use ApiResponse;
    protected $fileuploadService;
    private $filePath = 'uploads/report';
    public function __construct(FileUploadService $fileuploadService)
    {
        $this->fileuploadService = $fileuploadService->setPath($this->filePath);
    }

    public function reportProvider(ReportStoreRequest $request)
    {
        try {
            DB::beginTransaction();
            $report                     = new Report();
            $report->booking_id         = $request->booking_id;
            $report->user_id            = Auth::user()->id;
            $report->provider_id        = $request->provider_id;
            $report->report_reason      = $request->report_reason;
            $report->report_description = $request->report_description ?? null;
            $report->save();

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $reportAttachment             = new ReportAttachment();
                    $reportAttachment->report_id  = $report->id;
                    $reportAttachment->attachment = $this->fileuploadService->saveOptimizedImage(
                        $file,
                        40,
                        1320,
                        null,
                        true
                    );
                    $reportAttachment->save();
                }
            }
            DB::commit();
            $admins = User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $admin->notify(new NewReportNotification($report->id));
            }
            return $this->responseSuccess($report, 'Report has been submitted successfully!', 201);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->responseError($e->getMessage());
        }
    }

    public function getReports(Request $request)
    {

        $per_page = $request->input('per_page', 10);
        $search   = $request->input('search');
        $reports  = Report::with('user:id,name,avatar,kyc_status', 'provider:id,name,avatar,kyc_status', 'provider.provider_services.service:id,name')->when($search, function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('provider', function ($providerQuery) use ($search) {
                    $providerQuery->where('name', 'like', "%{$search}%");
                })
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    });
            });
        })->latest('id')->paginate($per_page);
        return $this->responseSuccess($reports, 'Reports retrieved successfully.');
    }

    public function deleteReports($report_id)
    {
        try {
            $report             = Report::findOrFail($report_id);
            $report_attachments = ReportAttachment::where('report_id', $report->id)->get();
            $report_attachments = ReportAttachment::where('report_id', $report->id)->get();
            if ($report_attachments->isNotEmpty()) {
                foreach ($report_attachments as $attachment) {
                    $this->fileuploadService->deleteFile($attachment->attachment);
                    $attachment->delete();
                }
            }
            $report->delete();
            return $this->responseSuccess($report, 'Report deleted successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function getReportDetail($report_id)
    {
        try {
            $report_details = Report::with('user:id,name,email,phone,address,avatar,kyc_status', 'provider:id,name,email,phone,address,avatar,kyc_status', 'provider.provider_services.service:id,name')->findOrFail($report_id);
            $otherReports   = Report::where('provider_id', $report_details->provider_id)
                ->select('id', 'report_reason')
                ->get();

            $data = [
                'report_details'           => $report_details,
                'provider_report_count'    => $otherReports->count(),
                'provider_related_reports' => $otherReports,
            ];

            return $this->responseSuccess($data, 'Report detail retrieved successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function takeReportAction(ReportActionRequest $request, $report_id)
    {
        try {
            $report     = Report::with('booking.package:id,title', 'booking.booking_items')->findOrFail($report_id);
            $packageIds = $report->booking->booking_items->pluck('package_id')->toArray();
            $packages   = Package::whereIn('id', $packageIds)->get();
            $provider   = User::findOrFail($report->provider_id);
            $type       = 'report';

            // 🔸 Notification Title set করা হচ্ছে
            if ($request->action_name == 'Give a warning') {
                $title = "Warning Regarding Your Service.";
            } elseif ($request->action_name == 'Suspend for 3 days') {
                $title = 'Your Service Has Been Temporarily Suspended for 3 Days';
            } elseif ($request->action_name == 'Suspend for 7 days') {
                $title = 'Your Service Has Been Temporarily Suspended for 7 Days.';
            } elseif ($request->action_name == 'Suspend for 15 days') {
                $title = 'Your Service Has Been Temporarily Suspended for 15 Days.';
            } elseif ($request->action_name == 'Suspend for 30 days') {
                $title = 'Your Service Has Been Temporarily Suspended for 30 Days.';
            } elseif ($request->action_name == 'Suspend permanently') {
                $title = 'Your Service Has Been Permanently Suspended.';
            }

            // 🔸 Action অনুযায়ী package suspend করা হচ্ছে
            foreach ($packages as $package) {
                if ($request->action_name == 'Suspend for 3 days') {
                    $package->is_suspend     = true;
                    $package->suspend_reason = 'Suspend for 3 days';
                    $package->suspend_until  = now()->addDays(3);
                } elseif ($request->action_name == 'Suspend for 7 days') {
                    $package->is_suspend     = true;
                    $package->suspend_reason = 'Suspend for 7 days';
                    $package->suspend_until  = now()->addDays(7);
                } elseif ($request->action_name == 'Suspend for 15 days') {
                    $package->is_suspend     = true;
                    $package->suspend_reason = 'Suspend for 15 days';
                    $package->suspend_until  = now()->addDays(15);
                } elseif ($request->action_name == 'Suspend for 30 days') {
                    $package->is_suspend     = true;
                    $package->suspend_reason = 'Suspend for 30 days';
                    $package->suspend_until  = now()->addDays(30);
                } elseif ($request->action_name == 'Suspend permanently') {
                    $package->is_suspend     = true;
                    $package->suspend_reason = 'Suspend permanently';
                    $package->suspend_until  = null;
                }

                $package->save();
            }

            $report->update([
                'report_action'             => $request->action_name,
                'report_action_description' => $request->action_reason,
            ]);
            $firstPackage = $packages->first();
            $report_data  = [
                'booking_id'                => $report->booking_id,
                'service_name'              => $firstPackage ? $firstPackage->title : null,
                'report_reason'             => $report->report_reason,
                'report_description'        => $report->report_description,
                'report_action'             => $report->report_action,
                'report_action_description' => $report->report_action_description,
            ];

            $provider->notify(new ReportWarningNotification($title, $type, $report_data));

            return $this->responseSuccess($report, "Take report action as {$request->action_name}.");
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }
}

<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dispute\DisputeAppealRequest;
use App\Models\DisputeAppeal;
use App\Services\FileUploadService;
use App\Traits\ApiResponse;

class DisputeAppealController extends Controller
{
    use ApiResponse;
    protected $fileuploadService;
    private $filePath = 'uploads/disputes/appeals';
    public function __construct(FileUploadService $fileuploadService)
    {
        $this->fileuploadService = $fileuploadService->setPath($this->filePath);
    }

    public function addDisputeAppeal(DisputeAppealRequest $request)
    {
        $existingAppeal = DisputeAppeal::where('dispute_id', $request->dispute_id)->first();

        if ($existingAppeal) {
            return $this->responseError(null, 'An appeal has already been submitted for this dispute.', 409);
        }

        $appeal             = new DisputeAppeal();
        $appeal->dispute_id = $request->dispute_id;
        $appeal->details    = $request->details;

        if ($request->hasFile('attachments')) {
            $attachments = $this->fileuploadService->saveMultipleFiles(
                $request->file('attachments'),
                true, 40, 1320, null, true
            );
            $appeal->attachments = json_encode($attachments);
        }

        $appeal->save();

        return $this->responseSuccess($appeal, 'Appeal created successfully.', 201);
    }

}

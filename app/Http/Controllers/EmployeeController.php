<?php
namespace App\Http\Controllers;

use App\Http\Requests\Employee\AssignEmployeeRequest;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Models\Employee;
use App\Models\EmployeeServiceCompletion;
use App\Services\FileUploadService;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeController extends Controller
{
    use ApiResponse;
    protected $fileuploadService;

    private $filePath = 'uploads/employee';

    public function __construct(FileUploadService $fileuploadService)
    {
        $this->fileuploadService = $fileuploadService->setPath($this->filePath);
    }

    public function index(Request $request)
    {
        $per_page  = $request->input('per_page');
        $search    = $request->input('search');
        $employees = Employee::withCount([
            'services_provided as completed_booking_count' => function ($query) {
                $query->where('status', 'Completed');
            },
        ])->where('provider_id', Auth::id())->when($search, function ($query) use ($search) {$query->where('name', 'LIKE', '%' . $search . '%');})->latest('id')->paginate($per_page);
        return $this->responseSuccess($employees, 'My employee retrieved successfully');
    }
    public function store(StoreEmployeeRequest $request)
    {
        try {
            $employee              = new Employee();
            $employee->provider_id = Auth::user()->id;
            if ($request->hasFile('image')) {
                $image           = $this->fileuploadService->saveOptimizedImage($request->file('image'), 40, null, null, true);
                $employee->image = $image;
            } else {
                $image           = $this->fileuploadService->generateUserAvatar($request->name);
                $employee->image = $image;
            }
            $employee->name     = $request->name;
            $employee->phone    = $request->phone;
            $employee->location = $request->location;
            $employee->save();
            return $this->responseSuccess($employee, 'Employee create successfully');
        } catch (Exception $e) {
            return $this->responseError(null, $e->getMessage());
        }
    }
    public function show($employee_id)
    {
        try {
            $employee = Employee::with('services_provided', 'services_provided.review', 'services_provided.booking_items.package', 'services_provided.user:id,name,kyc_status')->findOrFail($employee_id);
            return $this->responseSuccess($employee, 'Employee detail retrieved successfully');
        } catch (Exception $e) {
            return $this->responseError(null, $e->getMessage());
        }
    }
    public function update(StoreEmployeeRequest $request, $employee_id)
    {
        try {
            $employee = Employee::findOrFail($employee_id);
            if ($request->hasFile('image')) {
                $image           = $this->fileuploadService->updateOptimizedImage($request->file('image'), $employee->image, 40, null, null, true);
                $employee->image = $image;
            }
            $employee->name     = $request->name;
            $employee->phone    = $request->phone;
            $employee->location = $request->location;
            $employee->save();
            return $this->responseSuccess($employee, 'Employee update successfully');
        } catch (Exception $e) {
            return $this->responseError(null, $e->getMessage());
        }
    }

    public function delete($employee_id)
    {
        try {
            $employee = Employee::findOrFail($employee_id);
    
            $employee->delete();
            return $this->responseSuccess($employee, 'Employee deleted successfully');
        } catch (Exception $e) {
            return $this->responseError(null, $e->getMessage());
        }
    }

    public function assignEmployee(AssignEmployeeRequest $request)
    {
        try {
            $assign_employee = EmployeeServiceCompletion::create([
                'booking_id'  => $request->booking_id,
                'employee_id' => $request->employee_id,
            ]);
            return $this->responseSuccess($assign_employee, 'Employee assign successfully');
        } catch (Exception $e) {
            return $this->responseError(null, $e->getMessage());
        }
    }
}

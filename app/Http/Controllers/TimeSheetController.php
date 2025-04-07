<?php

namespace App\Http\Controllers;

use App\Exports\TimesheetExport;
use App\Imports\EmployeeImport;
use App\Imports\TimesheetImport;
use App\Models\Employee;
use App\Models\TimeSheet;
use App\Models\User;
use App\Models\Project;
use App\Models\ProjectEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class TimeSheetController extends Controller
{
    public function index(Request $request)
    {
        $employeesFind = Employee::where('user_id', \Auth::user()->id)->first();
        $employeeId = $employeesFind ? $employeesFind->id : null;
        $userId = auth()->id();



        if (\Auth::user()->can('Manage TimeSheet')) {
            $employeesList = [];
            $projects = [];

            // Parse selected month
            
            $selectedMonth = $request->month ?? now()->format('Y-m');
            $startOfMonth = $selectedMonth ? Carbon::parse($selectedMonth)->startOfMonth()->toDateString() : null;
            $endOfMonth = $selectedMonth ? Carbon::parse($selectedMonth)->endOfMonth()->toDateString() : null;

            if (\Auth::user()->type == 'employee') {
                $employeesDetails = Employee::where('created_by', \Auth::user()->creatorId())->first();

                $timesheets = TimeSheet::where('created_by', \Auth::user()->creatorId());

                if ($startOfMonth && $endOfMonth) {
                    $timesheets->whereBetween('date', [$startOfMonth, $endOfMonth]);
                }

                if (!empty($employeesDetails->user_id)) {

                    if (!empty($request->employee) && $request->employee == 'all') {
                        
                    }else if (!empty($request->employee) && $request->employee != 'all') {
                        $timesheets->where('employee_id', $request->employee);
                    }else{
                        $timesheets->where('employee_id', \Auth::user()->id);
                    }

                    
                }

                if (!empty($request->project_id)) {
                    $timesheets->where('project_id', $request->project_id);
                }

                $timeSheets = $timesheets->orderBy('created_at', 'desc')->get();

                $projects = Project::whereHas('employees', function ($query) use ($employeeId) {
                    $query->where('project_employee.employee_id', $employeeId);
                })->pluck('name', 'id');

                if (!empty($request->project_id)) {
                    // Show only employees related to the selected project

                    // Fetch the project
                    $project = Project::find($request->project_id);

                    // If current user is in the project_manager_ids array
                    if ($project && in_array($userId, ($project->project_manager_ids ? $project->project_manager_ids : []))) {
                        $employeeIds = \App\Models\ProjectEmployee::where('project_id', $request->project_id)->pluck('employee_id')->toArray();

                        $employeesList = Employee::whereIn('id', $employeeIds)->pluck('name', 'user_id')->prepend('All', 'all');
                    }


                    
                } else {
                    // Show all employees
                    $employeesList = [];
                }

            } else {
                $projects = Project::pluck('name', 'id');
                // $employeesList = Employee::where('created_by', \Auth::user()->creatorId())->pluck('name', 'user_id')->prepend('All', '');

                if (!empty($request->project_id)) {
                    // Show only employees related to the selected project
                    $employeeIds = \App\Models\ProjectEmployee::where('project_id', $request->project_id)
                        ->pluck('employee_id')
                        ->toArray();

                    $employeesList = Employee::whereIn('id', $employeeIds)
                        ->pluck('name', 'user_id')
                        ->prepend('All', '');
                } else {
                    // Show all employees
                    $employeesList = Employee::where('created_by', \Auth::user()->creatorId())
                        ->pluck('name', 'user_id')
                        ->prepend('All', '');
                }

                $timesheets = TimeSheet::where('created_by', \Auth::user()->creatorId());

                if ($startOfMonth && $endOfMonth) {
                    $timesheets->whereBetween('date', [$startOfMonth, $endOfMonth]);
                }

                if (!empty($request->employee)) {
                    $timesheets->where('employee_id', $request->employee);
                }

                if (!empty($request->project_id)) {
                    $timesheets->where('project_id', $request->project_id);
                }

                $timeSheets = $timesheets->orderBy('created_at', 'desc')->get();
            }


            // Grouped summary
            $groupedTimeSheets = [];

            foreach ($timeSheets as $sheet) {
                $projectName = $sheet->project->name ?? 'N/A';
                $employeeName = $sheet->employee->name ?? 'N/A';

                if (!isset($groupedTimeSheets[$projectName][$employeeName])) {
                    $groupedTimeSheets[$projectName][$employeeName] = 0;
                }

                $groupedTimeSheets[$projectName][$employeeName] += $sheet->hours;
            }

            //echo "<pre>";print_r($groupedTimeSheets);exit;

            return view('timeSheet.index', compact('timeSheets', 'employeesList', 'projects','groupedTimeSheets'));
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }

    public function getEmployees(Request $request)
    {
        $projectId = $request->get('project_id');

        if (!$projectId) {
            return response()->json([]);
        }

        $employeeIds = ProjectEmployee::where('project_id', $projectId)->pluck('employee_id');

        $employees = Employee::whereIn('id', $employeeIds)->pluck('name', 'id');

        return response()->json($employees);
    }



    public function createOld()
    {

        if (\Auth::user()->can('Create TimeSheet')) {
            $employees = Employee::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'user_id');

            return view('timeSheet.create', compact('employees'));
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }

    public function create()
    {
        if (\Auth::user()->can('Create TimeSheet')) {
            $user = \Auth::user(); // Get logged-in user
            $employees = Employee::where('created_by', '=', $user->creatorId())->get()->pluck('name', 'user_id');

            // Check if the user is a "company" or an assigned employee
            if ($user->type == 'company') {
                // Company users can see all projects
                $projects = \App\Models\Project::pluck('name', 'id');
            } else {
                // Get the Employee ID of the logged-in user
                $employee = Employee::where('user_id', $user->id)->value('id'); // Directly get ID

                if ($employee) {
                    // Get only projects assigned to this employee
                    $projects = \App\Models\Project::whereHas('employees', function ($query) use ($employee) {
                        $query->where('project_employee.employee_id', $employee);
                    })->pluck('name', 'id');
                } else {
                    // If no employee record exists, return an empty list
                    $projects = collect();
                }
            }

            $milestones = \App\Models\Milestone::pluck('name', 'id');

            // Get ID of "Development" Milestone
            $defaultMilestoneId = \App\Models\Milestone::where('name', 'Development')->value('id');

            return view('timeSheet.create', compact('employees', 'projects', 'milestones', 'defaultMilestoneId'));
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('Create TimeSheet')) {
            $timeSheet = new Timesheet();
            if (\Auth::user()->type == 'employee') {
                $timeSheet->employee_id = \Auth::user()->id;
            } else {
                $timeSheet->employee_id = $request->employee_id;
            }

            $timeSheetCheck = TimeSheet::where('date', $request->date)->where('employee_id', $timeSheet->employee_id)->first();
            /*
            if (!empty($timeSheetCheck)) {
                return redirect()->back()->with('error', __('Timesheet already created in this day.'));
            }
            */

            $timeSheet->date       = $request->date;
            $timeSheet->hours      = $request->hours;
            $timeSheet->remark     = $request->remark;
            $timeSheet->project_id = $request->project_id;
            $timeSheet->milestone_id = $request->milestone_id;
            $timeSheet->task_name = $request->task_name;
            $timeSheet->created_by = \Auth::user()->creatorId();
            $timeSheet->save();

            return redirect()->route('timesheet.index')->with('success', __('Timesheet successfully created.'));
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }

    public function storeNew(Request $request)
    {

        if (\Auth::user()->can('Create TimeSheet')) {
            $request->validate([
                'employee_id' => 'required|exists:employees,user_id',
                'project_id' => 'required|exists:projects,id',
                'date' => 'required|date',
                'hours' => 'required|numeric',
                'remark' => 'nullable|string',
            ]);

            echo "hello";exit;
            $timeSheet = new Timesheet();
            $timeSheet->employee_id = (\Auth::user()->type == 'employee') ? \Auth::user()->id : $request->employee_id;
            $timeSheet->project_id = $request->project_id;
            $timeSheet->date = $request->date;
            $timeSheet->hours = $request->hours;
            $timeSheet->remark = $request->remark;
            $timeSheet->created_by = \Auth::user()->creatorId();

            $timeSheetCheck = Timesheet::where('date', $request->date)
                ->where('employee_id', $timeSheet->employee_id)
                ->first();

            if (!empty($timeSheetCheck)) {
                return redirect()->back()->with('error', __('Timesheet already created for this day.'));
            }

            $timeSheet->save();

            return redirect()->route('timesheet.index')->with('success', __('Timesheet successfully created.'));
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }

    public function show(TimeSheet $timeSheet)
    {
        //
    }

    public function editOld(TimeSheet $timeSheet, $id)
    {

        if (\Auth::user()->can('Edit TimeSheet')) {
            $employees = Employee::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'user_id');
            $timeSheet = Timesheet::find($id);

            return view('timeSheet.edit', compact('timeSheet', 'employees'));
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }

    public function edit(TimeSheet $timeSheet, $id)
    {
        if (\Auth::user()->can('Edit TimeSheet')) {
            $user = \Auth::user(); // Get logged-in user
            $employees = Employee::where('created_by', '=', $user->creatorId())->get()->pluck('name', 'user_id');

            // Fetch the existing TimeSheet entry
            $timeSheet = TimeSheet::findOrFail($id);

            // Check if the user is a "company" or an assigned employee
            if ($user->type == 'company') {
                // Company users can see all projects
                $projects = \App\Models\Project::pluck('name', 'id');
            } else {
                // Get the Employee ID of the logged-in user
                $employee = Employee::where('user_id', $user->id)->first();
                $employeeId = $employee ? $employee->id : null;

                if ($employeeId) {
                    // Get only projects assigned to this employee
                    $projects = \App\Models\Project::whereHas('employees', function ($query) use ($employeeId) {
                        $query->where('project_employee.employee_id', $employeeId);
                    })->pluck('name', 'id');
                } else {
                    // If no employee record exists, return an empty list
                    $projects = collect();
                }
            }

            // Fetch milestones
            $milestones = \App\Models\Milestone::pluck('name', 'id');

            return view('timeSheet.edit', compact('timeSheet', 'employees', 'projects', 'milestones'));
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }


    public function updateOld(Request $request, $id)
    {
        if (\Auth::user()->can('Edit TimeSheet')) {

            $timeSheet = Timesheet::find($id);
            if (\Auth::user()->type == 'employee') {
                $timeSheet->employee_id = \Auth::user()->id;
            } else {
                $timeSheet->employee_id = $request->employee_id;
            }

            $timeSheetCheck = TimeSheet::where('date', $request->date)->where('employee_id', $timeSheet->employee_id)->first();

            if (!empty($timeSheetCheck) && $timeSheetCheck->id != $id) {
                return redirect()->back()->with('error', __('Timesheet already created in this day.'));
            }

            $timeSheet->date   = $request->date;
            $timeSheet->hours  = $request->hours;
            $timeSheet->remark = $request->remark;
            $timeSheet->save();

            return redirect()->route('timesheet.index')->with('success', __('TimeSheet successfully updated.'));
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }

    public function update(Request $request, $id)
    {
        if (\Auth::user()->can('Edit TimeSheet')) {
            // $request->validate([
            //     'employee_id' => 'required|exists:employees,user_id',
            //     'project_id' => 'required|exists:projects,id',
            //     'date' => 'required|date',
            //     'hours' => 'required|numeric',
            //     'remark' => 'nullable|string',
            // ]);

            $timeSheet = Timesheet::find($id);
            $timeSheet->employee_id = (\Auth::user()->type == 'employee') ? \Auth::user()->id : $request->employee_id;
            $timeSheet->project_id = $request->project_id;
            $timeSheet->milestone_id = $request->milestone_id;
            $timeSheet->task_name = $request->task_name;
            $timeSheet->date = $request->date;
            $timeSheet->hours = $request->hours;
            $timeSheet->remark = $request->remark;

            $timeSheetCheck = Timesheet::where('date', $request->date)
                ->where('employee_id', $timeSheet->employee_id)
                ->first();
            /*
            if (!empty($timeSheetCheck) && $timeSheetCheck->id != $id) {
                return redirect()->back()->with('error', __('Timesheet already created for this day.'));
            } */

            $timeSheet->save();

            return redirect()->route('timesheet.index')->with('success', __('Timesheet successfully updated.'));
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }

    public function destroy($id)
    {
        if (\Auth::user()->can('Delete TimeSheet')) {
            $timeSheet = Timesheet::find($id);
            $timeSheet->delete();

            return redirect()->route('timesheet.index')->with('success', __('TimeSheet successfully deleted.'));
        } else {
            return redirect()->back()->with('error', 'Permission denied.');
        }
    }

    public function export(Request $request)
    {
        $name = 'Timesheet_' . date('Y-m-d i:h:s');
        // $data = Excel::download(new TimesheetExport(), $name . '.xlsx');
        // return $data;
        return Excel::download(new TimesheetExport($request->start_date, $request->end_date, $request->employee_id, $request->project_id), $name . '.xlsx');
    }
    public function importFile(Request $request)
    {
        return view('timeSheet.import');
    }
    public function import(Request $request)
    {
        $rules = [
            'file' => 'required|mimes:csv,txt',
        ];
        $validator = \Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }

        try {
            $timesheet = (new TimesheetImport())->toArray(request()->file('file'))[0];

            $totalTimesheet = count($timesheet) - 1;
            $errorArray    = [];
            for ($i = 1; $i <= $totalTimesheet; $i++) {
                $timesheets = $timesheet[$i];
                $timesheetData = TimeSheet::where('employee_id', $timesheets[0])->where('date', $timesheets[1])->first();

                if (!empty($timesheetData)) {
                    $errorArray[] = $timesheetData;
                } else {
                    $time_sheet = new TimeSheet();

                    $time_sheet->employee_id = $timesheets[0];
                    $time_sheet->date = $timesheets[1];
                    $time_sheet->hours = $timesheets[2];
                    $time_sheet->remark = $timesheets[3];
                    $time_sheet->created_by = Auth::user()->id;
                    $time_sheet->save();
                }
            }
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('Something went wrong please try again.'));
        }

        if (empty($errorArray)) {
            $data['status'] = 'success';
            $data['msg']    = __('Record successfully imported');
        } else {

            $data['status'] = 'error';
            $data['msg']    = count($errorArray) . ' ' . __('Record imported fail out of' . ' ' . $totalTimesheet . ' ' . 'record');

            foreach ($errorArray as $errorData) {
                $errorRecord[] = implode(',', $errorData->toArray());
            }

            \Session::put('errorArray', $errorRecord);
        }

        return redirect()->back()->with($data['status'], $data['msg']);
    }
}

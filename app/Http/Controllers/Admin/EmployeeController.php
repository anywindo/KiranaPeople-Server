<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use App\Services\FileUploadService;
use App\Exports\Admin\EmployeeExport;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::with(['roles', 'location']);
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 10);
        $employees = $query->paginate($perPage)->appends($request->all());
        return view('admin.employees.index', compact('employees'));
    }

    public function create()
    {
        $rolesQuery = Role::where('guard_name', 'web');
        if (!auth()->user()->hasRole('super-admin')) {
            $rolesQuery->where('name', '!=', 'super-admin');
        }
        $roles = $rolesQuery->get();

        if (!$roles->contains('name', 'Employee')) {
            $employeeRole = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
            $roles->push($employeeRole);
        }

        $locations = \App\Models\Location::orderBy('name')->get();
        return view('admin.employees.create', compact('roles', 'locations'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'nullable|string|max:50|unique:users,employee_id',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'nik' => 'nullable|string|max:50|unique:users',
            'dob' => 'required|date',
            'password' => 'nullable|string|min:8|confirmed',
            'location_id' => 'required|exists:locations,id',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'dob.required' => 'Tanggal lahir (DOB) wajib diisi.',
            'dob.date' => 'Format tanggal lahir tidak valid.',
            'location_id.required' => 'Lokasi kerja wajib dipilih.',
            'location_id.exists' => 'Lokasi kerja yang dipilih tidak valid.',
        ]);

        $dob = $request->dob;
        if (!$dob && $request->nik) {
            $dob = User::parseDobDateFromNik($request->nik);
        }

        $passwordString = $request->filled('password')
            ? $request->password
            : User::computeDefaultPassword($dob, $request->nik);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'nik' => $request->nik,
            'dob' => $dob,
            'password' => Hash::make($passwordString),
            'location_id' => $request->location_id,
            'is_active' => $request->has('is_active'),
        ];

        if ($request->filled('employee_id')) {
            $data['employee_id'] = $request->employee_id;
        }

        if ($request->hasFile('avatar')) {
            $fileUploadService = new FileUploadService();
            $uploaded = $fileUploadService->uploadImageWithBase64($request->file('avatar'), 'avatars');
            $data['avatar'] = $uploaded['path'];
            $data['avatar_base64'] = $uploaded['base64'];
        }

        $employee = User::create($data);

        if($request->has('role')) {
            $employee->syncRoles([$request->role]);
        }

        return redirect()->route('admin.employees.index')->with('success', 'Employee created successfully.');
    }

    public function edit(User $employee)
    {
        $rolesQuery = Role::where('guard_name', 'web');
        if (!auth()->user()->hasRole('super-admin')) {
            $rolesQuery->where('name', '!=', 'super-admin');
        }
        $roles = $rolesQuery->get();

        if (!$roles->contains('name', 'Employee')) {
            $employeeRole = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
            $roles->push($employeeRole);
        }

        $locations = \App\Models\Location::orderBy('name')->get();
        return view('admin.employees.edit', compact('employee', 'roles', 'locations'));
    }

    public function update(Request $request, User $employee)
    {
        $request->validate([
            'employee_id' => 'nullable|string|max:50|unique:users,employee_id,'.$employee->id,
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$employee->id,
            'nik' => 'nullable|string|max:50|unique:users,nik,'.$employee->id,
            'dob' => 'required|date',
            'location_id' => 'required|exists:locations,id',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'dob.required' => 'Tanggal lahir (DOB) wajib diisi.',
            'dob.date' => 'Format tanggal lahir tidak valid.',
            'location_id.required' => 'Lokasi kerja wajib dipilih.',
            'location_id.exists' => 'Lokasi kerja yang dipilih tidak valid.',
        ]);

        $dob = $request->dob;
        if (!$dob && $request->nik) {
            $dob = User::parseDobDateFromNik($request->nik);
        }

        $data = $request->only(['name', 'email', 'nik', 'employee_id', 'location_id']);
        $data['dob'] = $dob;
        $data['is_active'] = $request->has('is_active');
        
        if ($request->hasFile('avatar')) {
            if ($employee->avatar) {
                try { Storage::disk('r2')->delete($employee->avatar); } catch (\Throwable $e) {}
                try { Storage::disk('public')->delete($employee->avatar); } catch (\Throwable $e) {}
            }
            $fileUploadService = new FileUploadService();
            $uploaded = $fileUploadService->uploadImageWithBase64($request->file('avatar'), 'avatars');
            $data['avatar'] = $uploaded['path'];
            $data['avatar_base64'] = $uploaded['base64'];
        }
        
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $employee->update($data);

        if($request->has('role')) {
            $employee->syncRoles([$request->role]);
        }

        return redirect()->route('admin.employees.index')->with('success', 'Employee updated successfully.');
    }

    public function destroy(User $employee)
    {
        if ($employee->id == auth()->id()) {
            return redirect()->route('admin.employees.index')->with('error', 'Cannot delete yourself.');
        }

        if ($employee->hasRole('super-admin')) {
            return redirect()->route('admin.employees.index')->with('error', 'Cannot delete a Superadmin account.');
        }

        $employee->delete();
        return redirect()->route('admin.employees.index')->with('success', 'Employee deleted successfully.');
    }

    public function export()
    {
        $filename = 'Data_Karyawan_' . date('Y-m-d_H-i-s') . '.xlsx';
        return Excel::download(new EmployeeExport, $filename);
    }

    public function downloadTemplate()
    {
        return Excel::download(new \App\Exports\EmployeeTemplateExport, 'Employee_Import_Template.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls|max:2048',
        ]);

        try {
            Excel::import(new \App\Imports\EmployeesImport, $request->file('file'));
            return redirect()->route('admin.employees.index')->with('success', 'Employees imported successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.employees.index')->with('error', 'Error importing employees: ' . $e->getMessage());
        }
    }
}

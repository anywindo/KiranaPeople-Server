<?php

namespace App\Http\Controllers\Admin;

use App\Exports\Admin\EmployeeExport;
use App\Exports\EmployeeTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\EmployeesImport;
use App\Models\Location;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

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
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        $perPage = $request->input('per_page', 10);
        $employees = $query->paginate($perPage)->appends($request->all());

        return view('admin.employees.index', compact('employees'));
    }

    public function create()
    {
        $rolesQuery = Role::where('guard_name', 'web');
        if (! auth()->user()->hasRole('super-admin')) {
            $rolesQuery->where('name', '!=', 'super-admin');
        }
        $roles = $rolesQuery->get();

        if (! $roles->contains('name', 'Employee')) {
            $employeeRole = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
            $roles->push($employeeRole);
        }

        $locations = Location::getAllCached();

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
            'role' => $this->roleValidationRules(),
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'dob.required' => 'Tanggal lahir (DOB) wajib diisi.',
            'dob.date' => 'Format tanggal lahir tidak valid.',
            'location_id.required' => 'Lokasi kerja wajib dipilih.',
            'location_id.exists' => 'Lokasi kerja yang dipilih tidak valid.',
            'role.not_in' => 'Hanya Superadmin yang dapat menetapkan role Superadmin.',
        ]);

        $dob = $request->dob;
        if (! $dob && $request->nik) {
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
            $fileUploadService = new FileUploadService;
            $data['avatar'] = $fileUploadService->uploadImage($request->file('avatar'), 'avatars');
        }

        $employee = User::create($data);

        if ($request->filled('role')) {
            $employee->syncRoles([$request->role]);
        }

        return redirect()->route('admin.employees.index')->with('success', 'Employee created successfully.');
    }

    public function edit(User $employee)
    {
        if ($employee->hasRole('super-admin') && ! auth()->user()->hasRole('super-admin')) {
            return redirect()->route('admin.employees.index')->with('error', 'Hanya Superadmin yang dapat mengubah akun Superadmin.');
        }

        $rolesQuery = Role::where('guard_name', 'web');
        if (! auth()->user()->hasRole('super-admin')) {
            $rolesQuery->where('name', '!=', 'super-admin');
        }
        $roles = $rolesQuery->get();

        if (! $roles->contains('name', 'Employee')) {
            $employeeRole = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
            $roles->push($employeeRole);
        }

        $locations = Location::getAllCached();

        return view('admin.employees.edit', compact('employee', 'roles', 'locations'));
    }

    public function update(Request $request, User $employee)
    {
        if ($employee->hasRole('super-admin') && ! auth()->user()->hasRole('super-admin')) {
            return redirect()->route('admin.employees.index')->with('error', 'Hanya Superadmin yang dapat mengubah akun Superadmin.');
        }
        $request->validate([
            'employee_id' => 'nullable|string|max:50|unique:users,employee_id,'.$employee->id,
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$employee->id,
            'nik' => 'nullable|string|max:50|unique:users,nik,'.$employee->id,
            'dob' => 'required|date',
            'location_id' => 'required|exists:locations,id',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'role' => $this->roleValidationRules(),
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'dob.required' => 'Tanggal lahir (DOB) wajib diisi.',
            'dob.date' => 'Format tanggal lahir tidak valid.',
            'location_id.required' => 'Lokasi kerja wajib dipilih.',
            'location_id.exists' => 'Lokasi kerja yang dipilih tidak valid.',
            'role.not_in' => 'Hanya Superadmin yang dapat menetapkan role Superadmin.',
        ]);

        $dob = $request->dob;
        if (! $dob && $request->nik) {
            $dob = User::parseDobDateFromNik($request->nik);
        }

        $data = $request->only(['name', 'email', 'nik', 'employee_id', 'location_id']);
        $data['dob'] = $dob;
        $data['is_active'] = $request->has('is_active');

        if ($request->hasFile('avatar')) {
            if ($employee->avatar) {
                try {
                    Storage::disk('r2')->delete($employee->avatar);
                } catch (\Throwable $e) {
                }
                try {
                    Storage::disk('public')->delete($employee->avatar);
                } catch (\Throwable $e) {
                }
            }
            $fileUploadService = new FileUploadService;
            $data['avatar'] = $fileUploadService->uploadImage($request->file('avatar'), 'avatars');
        }

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $employee->update($data);

        if ($request->filled('role')) {
            $employee->syncRoles([$request->role]);
        }

        return redirect()->route('admin.employees.index')->with('success', 'Employee updated successfully.');
    }

    /**
     * Build validation rules for role assignment, preventing privilege escalation.
     *
     * @return array<int, mixed>
     */
    private function roleValidationRules(): array
    {
        $rules = [
            'nullable',
            'string',
            Rule::exists('roles', 'name')->where('guard_name', 'web'),
        ];

        if (! auth()->user()?->hasRole('super-admin')) {
            $rules[] = Rule::notIn(['super-admin']);
        }

        return $rules;
    }

    public function destroy(User $employee)
    {
        if ($employee->id == auth()->id()) {
            return redirect()->route('admin.employees.index')->with('error', 'Tidak dapat menghapus akun Anda sendiri.');
        }

        if ($employee->hasRole('super-admin')) {
            return redirect()->route('admin.employees.index')->with('error', 'Akun Superadmin tidak dapat dihapus.');
        }

        try {
            $employee->delete();
        } catch (\DomainException $e) {
            return redirect()->route('admin.employees.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.employees.index')->with('success', 'Karyawan berhasil dihapus.');
    }

    public function export()
    {
        $filename = 'Data_Karyawan_'.date('Y-m-d_H-i-s').'.xlsx';

        return Excel::download(new EmployeeExport, $filename);
    }

    public function downloadTemplate()
    {
        return Excel::download(new EmployeeTemplateExport, 'Employee_Import_Template.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls|max:2048',
        ]);

        try {
            Excel::import(new EmployeesImport, $request->file('file'));

            return redirect()->route('admin.employees.index')->with('success', 'Employees imported successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.employees.index')->with('error', 'Error importing employees: '.$e->getMessage());
        }
    }
}

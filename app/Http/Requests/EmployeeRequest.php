<?php

namespace App\Http\Requests;

use App\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->hasPermission('employees.manage')) {
            return false;
        }

        $employee = $this->route('employee');
        $employeeRole = $employee?->user?->role;

        return $employeeRole === null || $user->role->canAssign($employeeRole);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');
        $requiresInitialPassword = $this->isMethod('post') || $employee?->user_id === null;

        return [
            'employee_code' => ['required', 'string', 'max:30', Rule::unique('employees')->ignore($this->route('employee'))],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($employee?->user_id),
                Rule::unique('employees', 'email')->ignore($employee),
            ],
            'address' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'position' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'join_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'role' => ['required', Rule::enum(UserRole::class)->only($this->user()->role->assignableRoles())],
            'password' => [$requiresInitialPassword ? 'required' : 'nullable', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_code.required' => 'Kode karyawan wajib diisi.',
            'employee_code.unique' => 'Kode karyawan sudah digunakan.',
            'name.required' => 'Nama karyawan wajib diisi.',
            'email.required' => 'Email login wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan oleh akun atau karyawan lain.',
            'photo.image' => 'Foto karyawan harus berupa gambar.',
            'photo.mimes' => 'Foto karyawan harus berformat JPG, JPEG, PNG, atau WebP.',
            'photo.max' => 'Ukuran foto karyawan maksimal 2 MB.',
            'position.required' => 'Jabatan wajib diisi.',
            'department.required' => 'Departemen wajib diisi.',
            'join_date.date' => 'Tanggal masuk tidak valid.',
            'status.required' => 'Status karyawan wajib dipilih.',
            'role.required' => 'Role login wajib dipilih.',
            'role.enum' => 'Role login tersebut tidak dapat dipilih.',
            'password.required' => 'Password awal wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sesuai.',
        ];
    }
}

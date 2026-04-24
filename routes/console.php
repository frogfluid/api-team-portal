<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use App\Enums\UserRole;
use App\Enums\Department;

Artisan::command('db:testusers', function () {
    if (app()->environment('production')) {
        $this->error('This command is not allowed in production.');
        return 1;
    }

    $usersToUpdate = [
        3 => ['role' => UserRole::MEMBER->value, 'department' => Department::MARKETING->value],
        5 => ['role' => UserRole::MEMBER->value, 'department' => Department::MARKETING->value],
        4 => ['role' => UserRole::MEMBER->value, 'department' => Department::MARKETING->value],
        7 => ['role' => UserRole::MANAGER->value, 'department' => Department::ENGINEERING->value],
        6 => ['role' => UserRole::MANAGER->value, 'department' => Department::OPERATIONS->value],
        1 => ['role' => UserRole::ADMIN->value, 'department' => Department::EXECUTIVE->value],
        2 => ['role' => UserRole::ADMIN->value, 'department' => Department::EXECUTIVE->value]
    ];

    foreach ($usersToUpdate as $id => $data) {
        User::where('id', $id)->update($data);
    }

    $testUsers = [
        ['name' => 'CEO Test (高层)', 'email' => 'ceo@test.com', 'role' => UserRole::ADMIN, 'department' => Department::EXECUTIVE],
        ['name' => 'Manager Test (中层)', 'email' => 'manager@test.com', 'role' => UserRole::MANAGER, 'department' => Department::ENGINEERING],
        ['name' => 'Member Test (普通)', 'email' => 'member@test.com', 'role' => UserRole::MEMBER, 'department' => Department::MARKETING],
        ['name' => 'PartTime Test (兼职)', 'email' => 'parttime@test.com', 'role' => UserRole::MEMBER, 'department' => Department::DESIGN],
        ['name' => 'Contractor Test (外包)', 'email' => 'contractor@test.com', 'role' => UserRole::MEMBER, 'department' => Department::OPERATIONS],
    ];

    foreach ($testUsers as $u) {
        $user = User::where('email', $u['email'])->first();
        if (!$user) {
            $user = User::create([
                'email' => $u['email'],
                'name' => $u['name'],
                'password' => Hash::make('Password123!'),
                'role' => $u['role'],
                'department' => $u['department'],
                'is_active' => true,
            ]);
        }

        Employee::firstOrCreate(['user_id' => $user->id]);
    }

    $this->info("Users updated successfully.");
});

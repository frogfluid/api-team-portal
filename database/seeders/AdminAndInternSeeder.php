<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminAndInternSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Admin user
        User::updateOrCreate(
            ['email' => 'kato-s@samurai-plus.com'],
            [
                'name' => 'Shuhey Kato',
                'password' => Hash::make('rcWz3X9YLgxW'),
                'role' => UserRole::ADMIN->value,     // users.role は varchar なので文字列でOK
                'is_active' => 1,
            ]
        );

        User::updateOrCreate(
            ['email' => 'hayashi@samurai-plus.com'],
            [
                'name' => 'Daiju Hayashi',
                'password' => Hash::make('cC5QUvqf8yg5'),
                'role' => UserRole::ADMIN->value,     // users.role は varchar なので文字列でOK
                'is_active' => 1,
            ]
        );


        $interns = [
            [
                'name' => 'Jeff',
                'legal_name' => 'Tan Wei Hao',
                'email' => 'jeffbarrage@gmail.com',
                'employee_no' => '26-0001',
                'phone' => '+601111162811',
                'address' => 'Lucentia Residence No.2, Jalan Hang Tuah, Pudu, 55100 Kuala Lumpur',
                'date_of_birth' => '2004-10-29',
                'password' => Hash::make('B5DrcKGjNrx2'),
            ],
            [
                'name' => 'Haris',
                'legal_name' => 'Muhammad haris raza',
                'email' => 'haris.2we2@gmail.com',
                'employee_no' => '26-0002',
                'phone' => '+60176856903',
                'address' => 'Casa ria apartments unit 269-1-5, jalan jejaka, cheras',
                'date_of_birth' => '2005-01-02',
                'password' => Hash::make('Tzn2tgNFycjN'),
            ],
            [
                'name' => 'Sora',
                'legal_name' => 'Sora Hashimoto',
                'email' => 'csioerla522152@gmail.com',
                'employee_no' => '26-0003',
                'phone' => '+60175221693',
                'address' => 'City Of Green Menara D, Jalan Pbs 14/2, Taman Perindustrian Bukit Serdang, Seri Kembangan, Petaling, Selangor, Malaysia, 43300',
                'date_of_birth' => '2006-12-25',
                'password' => Hash::make('62NDrhntPHCA'),
            ],
            [
                'name' => 'Chihiro',
                'legal_name' => 'Chihiro Kasai',
                'email' => 'kasachihi1228@icloud.com',
                'employee_no' => '26-0004',
                'phone' => '+60105786055',
                'address' => '15-18 BR2, DK Senza Residence, Jalan Taylors, PJS 7, Bandar Sunway, 47500 Subang Jaya, Selangor, Malaysia ',
                'date_of_birth' => '2004-12-28',
                'password' => Hash::make('BvUXYhE4FK5z'),
            ],
            [
                'name' => 'Archie',
                'legal_name' => 'YIXIAO SUN',
                'email' => 'archiesuny3@gmail.com',
                'employee_no' => '26-0005',
                'phone' => '+601127723433',
                'address' => 'Jalan Hang Tuah 2 Mitsui Serviced Suites,Unit 1103 Bukit Bintang, Kuala Lumpur Malaysia',
                'date_of_birth' => '2002-12-06',
                'password' => Hash::make('ABShMEQ3pKKp'),
            ],
        ];


        foreach ($interns as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $data['password'],
                    'role' => 'member',
                    'is_active' => 1,
                ]
            );

            Employee::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'employee_no' => $data['employee_no'] ?? null,
                    'employment_type' => 'intern',
                    'legal_name' => $data['legal_name'],
                    'phone' => $data['phone'],
                    'address' => $data['address'],
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'status' => 'active',
                    'joined_on' => '2026-03-01',
                ]
            );
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Xóa các tài khoản admin cũ trước khi tạo lại tài khoản mặc định mới
        User::where('role', 'admin')
            ->where('email', '!=', 'admin@code.com')
            ->delete();

        User::updateOrCreate(
            ['email' => 'admin@code.com'],
            [
                'name' => 'Hardcoded Admin',
                'password' => Hash::make('admin123'), // Mã hóa mật khẩu mặc định admin
                'role' => 'admin',
            ]
        );
        
        $this->command->info('Tài khoản Admin mặc định đã được tạo / cập nhật thành công!');
    }
}
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    // Nếu bảng tài khoản trong SQL của bạn KHÔNG phải là 'users' (ví dụ: 'tai_khoan', 'sinh_vien'...), 
    // hãy bỏ comment và sửa lại dòng dưới đây:
    // protected $table = 'ten_bang_cua_ban';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role', // Dùng để phân biệt Admin và Student
        // Thêm các cột khác từ database của bạn (ví dụ: mssv, class...)
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}

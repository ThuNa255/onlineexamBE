<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    // Lấy danh sách sinh viên
    public function index(Request $request)
    {
        // Khởi tạo query chỉ lấy những user có role là 'student'
        $query = User::where('role', 'student');

        // Nếu frontend có truyền từ khóa tìm kiếm (theo tên hoặc email)
        if ($request->has('search') && $request->search != '') {
            $searchTerm = '%' . $request->search . '%';
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm) // Nếu bảng của bạn dùng 'mssv' thì đổi 'name' thành 'mssv'
                  ->orWhere('email', 'like', $searchTerm);
            });
        }

        // Trả về danh sách, sắp xếp mới nhất lên đầu
        $students = $query->orderBy('created_at', 'desc')->get();
        
        return response()->json($students);
    }

    // Thêm sinh viên mới
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email', // Đảm bảo email không trùng lặp
            'password' => 'required|string|min:6',
        ]);

        // Mã hóa mật khẩu bằng Bcrypt trước khi lưu
        $validated['password'] = Hash::make($validated['password']);
        $validated['role'] = 'student'; // Mặc định gán role sinh viên

        $student = User::create($validated);

        return response()->json([
            'message' => 'Thêm sinh viên thành công',
            'student' => $student
        ], 201);
    }

    // Cập nhật thông tin sinh viên
    public function update(Request $request, $id)
    {
        // Tìm sinh viên (đảm bảo người này phải có role student)
        $student = User::where('role', 'student')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            // Kiểm tra email không trùng với người khác, ngoại trừ chính sinh viên này
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
        ]);

        // Nếu admin có nhập mật khẩu mới thì mã hóa lại, nếu không thì bỏ qua
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $student->update($validated);

        return response()->json([
            'message' => 'Cập nhật sinh viên thành công',
            'student' => $student
        ]);
    }

    // Xóa sinh viên
    public function destroy($id)
    {
        $student = User::where('role', 'student')->findOrFail($id);
        
        // Lưu ý: Nếu database của bạn có thiết lập khóa ngoại (Foreign Key) 
        // ràng buộc với bảng kết quả (results), bạn có thể cần xóa kết quả thi 
        // của sinh viên này trước, hoặc set cascade on delete trong database.
        $student->delete();

        return response()->json([
            'message' => 'Đã xóa sinh viên'
        ]);
    }
}

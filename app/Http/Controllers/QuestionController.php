<?php

namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuestionController extends Controller
{
    // Lấy danh sách câu hỏi (có hỗ trợ lọc theo subject và difficulty)
    public function index(Request $request)
    {
        $query = Question::query();

        // Xử lý bộ lọc từ frontend truyền lên
        if ($request->has('subject') && $request->subject !== 'all') {
            $query->where('subject_id', $request->subject);
        }

        if ($request->has('difficulty') && $request->difficulty !== 'all') {
            $query->where('difficulty', $request->difficulty);
        }

        // Nếu bảng SQL của bạn không có cột created_at, hãy xóa hàm orderBy này
        $questions = $query
            ->select('questions.*', 'subjects.name as subject_name')
            ->leftJoin('subjects', 'questions.subject_id', '=', 'subjects.id')
            ->orderBy('questions.created_at', 'desc')
            ->get();
        
        return response()->json($questions);
    }

    // Thêm câu hỏi mới
    public function store(Request $request)
    {
        // 1. Validate dữ liệu từ React gửi lên
        $validated = $request->validate([
            'exam_id' => 'nullable|integer', // Có thể thuộc bài thi hoặc nằm trong ngân hàng chung
            'content' => 'required|string',
            'option_a' => 'required|string',
            'option_b' => 'required|string',
            'option_c' => 'required|string',
            'option_d' => 'required|string',
            'correct_answer' => 'required|in:A,B,C,D',
            'subject_id' => 'required|integer|exists:subjects,id',
            'difficulty' => 'required|in:easy,medium,hard'
        ]);

        // 2. Lưu vào DB theo đúng cấu trúc của xdpmw_test.sql
        $question = Question::create($validated);

        return response()->json([
            'message' => 'Thêm câu hỏi thành công',
            'question' => $question
        ], 201);
    }

    // Cập nhật câu hỏi
    public function update(Request $request, $id)
    {
        Log::info('Update question request:', ['id' => $id, 'data' => $request->all()]);

        // Tìm theo ID (hoặc khóa chính tương ứng trong DB của bạn)
        $question = Question::findOrFail($id);

        $validated = $request->validate([
            'content' => 'sometimes|required|string',
            'option_a' => 'sometimes|required|string',
            'option_b' => 'sometimes|required|string',
            'option_c' => 'sometimes|required|string',
            'option_d' => 'sometimes|required|string',
            'correct_answer' => 'sometimes|required|in:A,B,C,D',
            'subject_id' => 'sometimes|required|integer|exists:subjects,id',
            'difficulty' => 'sometimes|required|in:easy,medium,hard'
        ]);

        $question->update($validated);

        return response()->json([
            'message' => 'Cập nhật câu hỏi thành công',
            'question' => $question
        ]);
    }

    // Xóa câu hỏi
    public function destroy($id)
    {
        $question = Question::findOrFail($id);
        $question->delete();

        return response()->json([
            'message' => 'Đã xóa câu hỏi'
        ]);
    }
}
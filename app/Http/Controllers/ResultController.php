<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Result;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    // Hàm nộp bài và chấm điểm tự động
    public function submit(Request $request, $examId)
    {
        $request->validate([
            'answers' => 'required|array', // Mảng các câu trả lời từ React
        ]);

        $exam = Exam::with('questions')->findOrFail($examId);
        $user = $request->user();

        // 1. Kiểm tra xem sinh viên đã nộp bài này chưa
        $existingResult = Result::where('user_id', $user->id)
                                ->where('exam_id', $examId)
                                ->first();
        if ($existingResult) {
            return response()->json(['message' => 'Bạn đã hoàn thành bài thi này rồi'], 400);
        }

        // 2. Chấm điểm
        $correctCount = 0;
        $totalQuestions = $exam->questions->count();
        $submittedAnswers = $request->answers;

        foreach ($exam->questions as $question) {
            // Lấy ID của câu hỏi. Đảm bảo ID này khớp với key mà React gửi lên
            $qId = $question->id; 
            
            // Nếu sinh viên có chọn đáp án và đáp án đó đúng
            if (isset($submittedAnswers[$qId]) && $submittedAnswers[$qId] === $question->correct_answer) {
                $correctCount++;
            }
        }

        // Tính điểm hệ 100 (làm tròn 2 chữ số thập phân)
        $score = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0;

        // 3. Lưu kết quả vào Database
        $result = Result::create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'score' => $score,
            'total_correct' => $correctCount,
            'total_questions' => $totalQuestions,
            'submitted_at' => now(),
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Nộp bài thành công',
            'result' => $result
        ]);
    }

    // Lấy lịch sử điểm của sinh viên đang đăng nhập
    public function myResults(Request $request)
    {
        $results = Result::with('exam')
                         ->where('user_id', $request->user()->id)
                         ->orderBy('completed_at', 'desc')
                         ->get();

        return response()->json($results);
    }
    public function index(Request $request)
    {
        // Sử dụng eager loading ('with') để lấy luôn thông tin User và Exam đi kèm
        $query = Result::with(['user', 'exam']);

        // Xử lý tìm kiếm (theo tên/email sinh viên hoặc tên bài thi)
        if ($request->has('search') && $request->search != '') {
            $searchTerm = '%' . $request->search . '%';
            
            $query->whereHas('user', function($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhere('email', 'like', $searchTerm);
            })->orWhereHas('exam', function($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhere('subject', 'like', $searchTerm);
            });
        }

        // Xử lý lọc theo một bài thi cụ thể (nếu frontend có dropdown lọc)
        if ($request->has('exam_id') && $request->exam_id != 'all') {
            $query->where('exam_id', $request->exam_id);
        }

        // Sắp xếp mới nhất lên đầu
        $results = $query->orderBy('completed_at', 'desc')->get();

        // Format lại dữ liệu trả về cho Frontend dễ đọc hơn
        $formattedResults = $results->map(function ($result) {
            return [
                'id' => $result->id,
                'studentName' => $result->user->name ?? 'N/A',
                'studentEmail' => $result->user->email ?? 'N/A',
                'examName' => $result->exam->name ?? 'N/A',
                'subject' => $result->exam->subject ?? 'N/A',
                'score' => $result->score,
                'total_correct' => $result->total_correct,
                'completedAt' => $result->completed_at,
            ];
        });

        return response()->json($formattedResults);
    }

    // Xóa một kết quả thi (nếu Admin cần tính năng hủy bài thi của sinh viên)
    public function destroy($id)
    {
        $result = Result::findOrFail($id);
        $result->delete();

        return response()->json([
            'message' => 'Đã xóa kết quả thi thành công'
        ]);
    }
}
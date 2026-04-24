<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Result;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ResultController extends Controller
{
    // Hàm nộp bài và chấm điểm tự động
    public function submit(Request $request, $examId)
    {
        $request->validate([
            'answers' => 'required|array', // Mảng các câu trả lời từ React
            'question_ids' => 'required|array', // Danh sách ID câu hỏi đã nhận
        ]);

        $exam = Exam::findOrFail($examId);
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Kiểm tra thời gian thi hợp lệ
        $now = now();
        $startTime = \Carbon\Carbon::parse($exam->start_time);
        $endTime = \Carbon\Carbon::parse($exam->end_time);

        if ($now < $startTime) {
            return response()->json(['message' => 'Bài thi chưa bắt đầu'], 400);
        }

        if ($now > $endTime) {
            return response()->json(['message' => 'Đã quá thời gian nộp bài'], 400);
        }

        // 1. Kiểm tra xem sinh viên đã nộp bài này chưa
        $existingResult = Result::where('user_id', $user->id)
                                ->where('exam_id', $examId)
                                ->first();
        if ($existingResult) {
            return response()->json(['message' => 'Bạn đã hoàn thành bài thi này rồi'], 400);
        }

        // 2. Chấm điểm dựa trên question_ids đã lưu
        $submittedAnswers = $request->answers;
        $questionIds = $request->question_ids;

        $questions = Question::whereIn('id', $questionIds)->get()->keyBy('id');

        $correctCount = 0;
        foreach ($questionIds as $qId) {
            if (isset($submittedAnswers[$qId]) && isset($questions[$qId]) &&
                $submittedAnswers[$qId] === $questions[$qId]->correct_answer) {
                $correctCount++;
            }
        }

        $totalQuestions = count($questionIds);
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
            'question_ids' => json_encode($questionIds),
            'answers' => json_encode($submittedAnswers),
        ]);

        return response()->json([
            'message' => 'Nộp bài thành công',
            'result' => $result
        ]);
    }

    // Lấy kết quả của một bài thi cụ thể cho sinh viên
    public function showForStudent(Request $request, $examId)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $result = Result::with('exam.subject')
            ->where('user_id', $user->id)
            ->where('exam_id', $examId)
            ->first();

        if (!$result) {
            return response()->json(['message' => 'Không tìm thấy kết quả hoặc bạn chưa hoàn thành bài thi này.'], 404);
        }

        $exam = $result->exam;

        // Decode JSON fields
        $questionIds = is_string($result->question_ids) ? json_decode($result->question_ids, true) : $result->question_ids;
        $answers = is_string($result->answers) ? json_decode($result->answers, true) : $result->answers;

        // Lấy chi tiết câu hỏi và đáp án để xem lại
        $questions = Question::whereIn('id', $questionIds ?? [])->get();
        $questionDetails = [];

        foreach ($questions as $question) {
            $studentAnswer = $answers[$question->id] ?? null;
            $isCorrect = $studentAnswer === $question->correct_answer;

            $questionDetails[] = [
                'id' => $question->id,
                'questionText' => $question->content,
                'options' => [
                    'A' => $question->option_a,
                    'B' => $question->option_b,
                    'C' => $question->option_c,
                    'D' => $question->option_d,
                ],
                'correct_answer' => $question->correct_answer,
                'student_answer' => $studentAnswer,
                'is_correct' => $isCorrect,
            ];
        }

        return response()->json([
            'id' => $result->id,
            'exam_id' => $result->exam_id,
            'exam_name' => $exam?->name,
            'subject_name' => $exam?->subject?->name ?? $exam?->subject_name ?? 'N/A',
            'subject' => $exam?->subject?->name ?? $exam?->subject_name ?? 'N/A',
            'score' => $result->score,
            'total_correct' => $result->total_correct,
            'total_questions' => $result->total_questions,
            'duration' => $exam?->duration ?? 0,
            'duration_minutes' => $exam?->duration ?? 0,
            'completed_at' => $result->completed_at ?? $result->submitted_at,
            'submitted_at' => $result->submitted_at,
            'questions' => $questionDetails,
        ]);
    }

    // Lấy danh sách kết quả cho admin
    public function index(Request $request)
    {
        $query = Result::with(['user', 'exam.subject']);

        if ($request->has('search') && $request->search != '') {
            $searchTerm = '%' . $request->search . '%';

            $query->whereHas('user', function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhere('email', 'like', $searchTerm);
            })->orWhereHas('exam', function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhereHas('subject', function ($q2) use ($searchTerm) {
                      $q2->where('name', 'like', $searchTerm);
                  });
            });
        }

        if ($request->has('exam_id') && $request->exam_id != 'all') {
            $query->where('exam_id', $request->exam_id);
        }

        $results = $query->orderBy('completed_at', 'desc')->get();

        return response()->json($results->map(function ($result) {
            return [
                'id' => $result->id,
                'studentName' => $result->user->name ?? 'N/A',
                'studentEmail' => $result->user->email ?? 'N/A',
                'examName' => $result->exam->name ?? 'N/A',
                'subject' => $result->exam->subject?->name ?? $result->exam->subject ?? 'N/A',
                'score' => $result->score,
                'total_correct' => $result->total_correct,
                'completed_at' => $result->completed_at,
            ];
        }));
    }

    // Lấy lịch sử điểm của sinh viên đang đăng nhập
    public function myResults(Request $request)
    {
        // Sử dụng eager loading ('with') để lấy luôn thông tin User và Exam đi kèm
        $query = Result::with(['user', 'exam.subject']);

        // Xử lý tìm kiếm (theo tên/email sinh viên hoặc tên bài thi)
        if ($request->has('search') && $request->search != '') {
            $searchTerm = '%' . $request->search . '%';
            
            $query->whereHas('user', function($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhere('email', 'like', $searchTerm);
            })->orWhereHas('exam', function($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhereHas('subject', function ($q2) use ($searchTerm) {
                      $q2->where('name', 'like', $searchTerm);
                  });
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
                'subject' => $result->exam->subject?->name ?? $result->exam->subject ?? 'N/A',
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
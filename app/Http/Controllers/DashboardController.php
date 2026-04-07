<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Exam;
use App\Models\Result;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. LẤY SỐ LIỆU THỐNG KÊ (Stats)
        // Giả sử bảng users phân biệt sinh viên bằng cột 'role' = 'student'
        $totalStudents = User::where('role', 'student')->count(); 
        $totalExams = Exam::count();
        $activeExams = Exam::where('status', 'ongoing')->count();
        
        // Tính điểm trung bình của tất cả kết quả
        $avgScoreRaw = DB::table('results')->avg('score') ?? 0;
        $averageScore = number_format($avgScoreRaw, 1);

        // 2. DỮ LIỆU BIỂU ĐỒ CỘT: Số bài thi theo môn học
        // Trả về dạng: [{ subject: 'CTDL', count: 5 }, ...]
        $examsBySubject = Exam::select('subjects.name as subject', DB::raw('count(*) as count'))
            ->join('subjects', 'exams.subject_id', '=', 'subjects.id')
            ->groupBy('subjects.name')
            ->get();

        // 3. DỮ LIỆU BIỂU ĐỒ ĐƯỜNG: Điểm trung bình 7 ngày gần nhất
        // Trả về dạng: [{ date: '15/03', avgScore: 75 }, ...]
        $recentResults = DB::table('results')
            ->select(DB::raw('DATE(completed_at) as date_val'), DB::raw('ROUND(AVG(score), 1) as avgScore'))
            ->where('completed_at', '>=', Carbon::now()->subDays(7))
            ->groupBy('date_val')
            ->orderBy('date_val', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => Carbon::parse($item->date_val)->format('d/m'), 
                    'avgScore' => (float) $item->avgScore
                ];
            });

        // 4. HOẠT ĐỘNG GẦN ĐÂY: 5 kết quả mới nhất
        $recentActivity = DB::table('results')
            ->join('users', 'results.user_id', '=', 'users.id')
            ->join('exams', 'results.exam_id', '=', 'exams.id')
            ->select('users.name as studentName', 'exams.name as examName', 'results.score', 'results.completed_at as completedAt')
            ->orderBy('results.completed_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($result) {
                return [
                    'studentName' => $result->studentName,
                    'examName' => $result->examName,
                    'score' => $result->score,
                    'completedAt' => $result->completedAt
                ];
            });

        // Đóng gói toàn bộ trả về cho Frontend
        return response()->json([
            'stats' => [
                'totalStudents' => $totalStudents,
                'totalExams' => $totalExams,
                'activeExams' => $activeExams,
                'averageScore' => $averageScore,
            ],
            'charts' => [
                'examsBySubject' => $examsBySubject,
                'recentResults' => $recentResults,
            ],
            'recentActivity' => $recentActivity
        ]);
    }
    public function studentDashboard(Request $request)
    {
        $userId = $request->user()->id;

        // 1. Số liệu thống kê (Stats)
        $totalCompleted = Result::where('user_id', $userId)->count();
        $avgScore = Result::where('user_id', $userId)->avg('score') ?? 0;
        $highestScore = Result::where('user_id', $userId)->max('score') ?? 0;

        // Đếm số bài thi Đang diễn ra (ongoing) mà sinh viên CHƯA LÀM
        $completedExamIds = Result::where('user_id', $userId)->pluck('exam_id');
        $pendingExams = Exam::where('status', 'ongoing')
                            ->whereNotIn('id', $completedExamIds)
                            ->count();

        // 2. Dữ liệu biểu đồ (Tiến độ học tập qua 5 bài thi gần nhất)
        // Sắp xếp asc (cũ -> mới) để đồ thị đi từ trái qua phải
        $chartData = Result::with('exam')
            ->where('user_id', $userId)
            ->orderBy('completed_at', 'asc')
            ->take(5)
            ->get()
            ->map(function ($result) {
                return [
                    // Cắt ngắn tên môn học nếu quá dài để hiển thị biểu đồ cho đẹp
                    'subject' => substr($result->exam->subject ?? 'N/A', 0, 10),
                    'score' => $result->score
                ];
            });

        // 3. Lịch sử làm bài gần đây
        $recentHistory = Result::with('exam')
            ->where('user_id', $userId)
            ->orderBy('completed_at', 'desc')
            ->take(5)
            ->get()
            ->map(function($result) {
                return [
                    'id' => $result->id,
                    'examName' => $result->exam->name ?? 'Bài thi đã xóa',
                    'subject' => $result->exam->subject ?? '',
                    'score' => $result->score,
                    'completedAt' => $result->completed_at
                ];
            });

        return response()->json([
            'stats' => [
                'totalCompleted' => $totalCompleted,
                'averageScore' => number_format($avgScore, 1),
                'highestScore' => $highestScore,
                'pendingExams' => $pendingExams
            ],
            'chartData' => $chartData,
            'recentHistory' => $recentHistory
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Exam;
use App\Models\Result;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. LẤY SỐ LIỆU THỐNG KÊ (Stats)
        $totalStudents = User::where('role', 'student')->count(); 
        $totalExams = Exam::count();
        $activeExams = Exam::where('status', 'ongoing')->count();
        
        $avgScoreRaw = DB::table('results')->avg('score') ?? 0;
        $averageScore = number_format($avgScoreRaw, 1);

        // 2. DỮ LIỆU BIỂU ĐỒ CỘT: Số bài thi theo môn học
        if (Schema::hasTable('subjects') && Schema::hasColumn('exams', 'subject_id')) {
            $examsBySubject = DB::table('exams')
                ->join('subjects', 'exams.subject_id', '=', 'subjects.id')
                ->select('subjects.name as subject', DB::raw('COUNT(exams.id) as count'))
                ->groupBy('subjects.id', 'subjects.name') // FIX: PostgreSQL bắt buộc group by ID
                ->get();
        } elseif (Schema::hasColumn('exams', 'subject')) {
            $examsBySubject = DB::table('exams')
                ->select('subject', DB::raw('COUNT(id) as count'))
                ->groupBy('subject')
                ->get();
        } else {
            $examsBySubject = collect([]);
        }

        // 3. DỮ LIỆU BIỂU ĐỒ ĐƯỜNG: Điểm trung bình 7 ngày gần nhất
        // FIX: Xử lý gom nhóm bằng PHP thay vì Raw SQL để chống lỗi trên Postgres
        $last7Days = Carbon::now()->subDays(7);
        
        $resultsLast7Days = DB::table('results')
            ->where('completed_at', '>=', $last7Days)
            ->get();

        // Nhóm dữ liệu theo ngày bằng Collection của Laravel
        $groupedResults = $resultsLast7Days->groupBy(function ($item) {
            return Carbon::parse($item->completed_at)->format('Y-m-d');
        })->sortKeys();

        $recentResults = collect([]);
        foreach ($groupedResults as $date => $group) {
            $recentResults->push([
                'date' => Carbon::parse($date)->format('d/m'), 
                'avgScore' => round($group->avg('score'), 1)
            ]);
        }

        // 4. HOẠT ĐỘNG GẦN ĐÂY: 5 kết quả mới nhất
        $recentActivity = DB::table('results')
            ->join('users', 'results.user_id', '=', 'users.id')
            ->join('exams', 'results.exam_id', '=', 'exams.id')
            ->select('users.name as studentName', 'exams.name as examName', 'results.score', 'results.completed_at as completedAt')
            ->orderBy('results.completed_at', 'desc')
            ->take(5)
            ->get();

        // Đóng gói trả về Frontend
        return response()->json([
            'stats' => [
                'totalStudents' => $totalStudents,
                'totalExams' => $totalExams,
                'activeExams' => $activeExams,
                'averageScore' => $averageScore,
            ],
            'charts' => [
                'examsBySubject' => $examsBySubject,
                'recentResults' => $recentResults->values(),
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

        $completedExamIds = Result::where('user_id', $userId)->pluck('exam_id');
        $pendingExams = Exam::where('status', 'ongoing')
                            ->whereNotIn('id', $completedExamIds)
                            ->count();

        // 2. Dữ liệu biểu đồ (Tiến độ học tập qua 5 bài thi gần nhất)
        $chartData = Result::with('exam.subject')
            ->where('user_id', $userId)
            ->orderBy('completed_at', 'asc')
            ->take(5)
            ->get()
            ->map(function ($result) {
                // FIX: Xử lý an toàn khi subject là một Object thay vì String
                $subjectName = is_object($result->exam?->subject) 
                    ? $result->exam->subject->name 
                    : ($result->exam?->subject ?? $result->exam?->subject_name ?? 'N/A');

                return [
                    // Dùng mb_substr để cắt tiếng Việt không bị lỗi font
                    'subject' => mb_substr((string)$subjectName, 0, 10), 
                    'score' => (float)$result->score
                ];
            });

        // 3. Lịch sử làm bài gần đây
        $recentHistory = Result::with('exam.subject')
            ->where('user_id', $userId)
            ->orderBy('completed_at', 'desc')
            ->take(5)
            ->get()
            ->map(function($result) {
                $subjectName = is_object($result->exam?->subject) 
                    ? $result->exam->subject->name 
                    : ($result->exam?->subject ?? $result->exam?->subject_name ?? 'N/A');

                return [
                    'id' => $result->id,
                    'examName' => $result->exam->name ?? 'Bài thi đã xóa',
                    'subject' => (string)$subjectName,
                    'score' => (float)$result->score,
                    'completedAt' => $result->completed_at
                ];
            });

        return response()->json([
            'stats' => [
                'totalCompleted' => $totalCompleted,
                'averageScore' => number_format($avgScore, 1),
                'highestScore' => number_format($highestScore, 1),
                'pendingExams' => $pendingExams
            ],
            'chartData' => $chartData,
            'recentHistory' => $recentHistory
        ]);
    }
}

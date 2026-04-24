<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ExamController extends Controller
{
    private function mapExam(Exam $exam): array
    {
        // Calculate the actual status based on current time
        $calculatedStatus = $this->calculateStatus($exam->start_time, $exam->end_time);
        $subjectName = $exam->subjectRelation?->name ?? $exam->subject_name ?? $exam->getAttribute('subject') ?? null;

        return [
            'id' => $exam->id,
            'subject_id' => $exam->subject_id ?? null,
            'subject' => $subjectName,
            'subject_name' => $subjectName,
            'name' => $exam->name,
            'start_time' => $exam->start_time,
            'end_time' => $exam->end_time,
            'duration' => $exam->duration,
            'total_questions' => $exam->total_questions,
            'password' => $exam->password,
            'status' => $calculatedStatus,
            'created_at' => $exam->created_at,
            'updated_at' => $exam->updated_at,
        ];
    }

    private function calculateStatus(string $startTime, string $endTime): string
    {
        $now = now();
        $start = \Carbon\Carbon::parse($startTime);
        $end = \Carbon\Carbon::parse($endTime);

        if ($now < $start) {
            return 'upcoming';
        }

        if ($now >= $start && $now < $end) {
            return 'ongoing';
        }

        return 'completed';
    }

    // Lấy danh sách toàn bộ bài thi (Dành cho Admin)
    public function index()
    {
        $exams = Exam::with('subjectRelation')->orderBy('created_at', 'desc')->get();
        return response()->json($exams->map(fn (Exam $exam) => $this->mapExam($exam)));
    }

    // Tạo bài thi mới
    public function store(Request $request)
    {
        $validated = $request->validate(array_merge([
            'name' => 'required|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'duration' => 'required|integer|min:1',
            'total_questions' => 'required|integer|min:1',
            'password' => 'nullable|string'
        ], Schema::hasColumn('exams', 'subject_id') ? [
            'subject_id' => 'required|integer|exists:subjects,id',
        ] : [
            'subject' => 'required|string|max:255',
        ]));

        $validated['status'] = $this->calculateStatus($validated['start_time'], $validated['end_time']);

        $exam = Exam::create($validated);

        // Lấy ngẫu nhiên các câu hỏi từ ngân hàng (chưa thuộc bài thi nào)
        $questionQuery = Question::query();
        if (Schema::hasColumn('questions', 'subject_id') && isset($validated['subject_id'])) {
            $questionQuery->where('subject_id', $validated['subject_id']);
        } else {
            $questionQuery->where('subject', $validated['subject'] ?? null);
        }
        $questionQuery->whereNull('exam_id');

        $questions = $questionQuery->inRandomOrder()->limit($validated['total_questions'])->get();

        // Nhân bản các câu hỏi và gắn cố định vào bài thi này
        foreach ($questions as $question) {
            $newQuestion = $question->replicate();
            $newQuestion->exam_id = $exam->id;
            $newQuestion->save();
        }

        return response()->json([
            'message' => 'Tạo bài thi thành công',
            'exam' => $this->mapExam($exam->load('subjectRelation'))
        ], 201);
    }

    // Xem chi tiết 1 bài thi
    public function show($id)
    {
        $exam = Exam::with('subjectRelation')->findOrFail($id);
        return response()->json($this->mapExam($exam));
    }

    // Cập nhật bài thi
    public function update(Request $request, $id)
    {
        $exam = Exam::findOrFail($id);

        $validated = $request->validate(array_merge([
            'name' => 'sometimes|required|string|max:255',
            'start_time' => 'sometimes|required|date',
            'end_time' => 'sometimes|required|date|after:start_time',
            'duration' => 'sometimes|required|integer|min:1',
            'total_questions' => 'sometimes|required|integer|min:1',
            'password' => 'nullable|string'
        ], Schema::hasColumn('exams', 'subject_id') ? [
            'subject_id' => 'sometimes|nullable|integer|exists:subjects,id',
        ] : [
            'subject' => 'sometimes|required|string|max:255',
        ]));

        if (!$request->filled('password')) {
            unset($validated['password']);
        }

        if (array_key_exists('start_time', $validated) || array_key_exists('end_time', $validated)) {
            $start = $validated['start_time'] ?? $exam->start_time;
            $end = $validated['end_time'] ?? $exam->end_time;
            $validated['status'] = $this->calculateStatus($start, $end);
        }

        $exam->update($validated);

        return response()->json([
            'message' => 'Cập nhật bài thi thành công',
            'exam' => $this->mapExam($exam->load('subjectRelation'))
        ]);
    }

    // Xóa bài thi
    public function destroy($id)
    {
        $exam = Exam::findOrFail($id);
        $exam->delete();

        return response()->json([
            'message' => 'Đã xóa bài thi'
        ]);
    }
    public function studentExams(Request $request)
    {
        $user = $request->user();
        $exams = Exam::with('subjectRelation')->get();
        $completedExamIds = \App\Models\Result::where('user_id', $user->id)->pluck('exam_id')->toArray();

        return response()->json($exams->map(function (Exam $exam) use ($completedExamIds) {
            $mapped = $this->mapExam($exam);
            $mapped['is_completed'] = in_array($exam->id, $completedExamIds);
            return $mapped;
        }));
    }

    // Sinh viên lấy đề thi để làm
    public function showForStudent(Request $request, $id)
    {
        $exam = Exam::with('subjectRelation')->findOrFail($id);

        // Kiểm tra trạng thái bài thi
        $calculatedStatus = $this->calculateStatus($exam->start_time, $exam->end_time);
        if ($calculatedStatus !== 'ongoing') {
            return response()->json(['message' => 'Bài thi chưa bắt đầu hoặc đã kết thúc.'], 403);
        }

        // Kiểm tra mật khẩu nếu có
        if ($exam->password) {
            $password = $request->input('password');
            if (!$password || $password !== $exam->password) {
                return response()->json(['message' => 'Mật khẩu không đúng.'], 403);
            }
        }

        if ($exam->questions()->exists()) {
            // Lấy danh sách câu hỏi đã được fix cố định cho bài thi này
            $questions = $exam->questions;
        } else {
            // Backward compatibility: Lấy ngẫu nhiên theo môn học nếu bài thi chưa được gán câu hỏi cố định
            $questionQuery = Question::query();
            if (Schema::hasColumn('questions', 'subject_id') && $exam->subject_id !== null) {
                $questionQuery->where('subject_id', $exam->subject_id);
            } else {
                $questionQuery->where('subject', $exam->subject ?? $exam->subject_name);
            }
            $questions = $questionQuery
                ->inRandomOrder()
                ->limit($exam->total_questions)
                ->get();
        }

        // BẢO MẬT: Ẩn đáp án đúng trước khi gửi về cho React
        $questions->makeHidden(['correct_answer']);

        $data = $this->mapExam($exam);
        $data['questions'] = $questions;
        $data['question_ids'] = $questions->pluck('id')->toArray(); // Thêm danh sách ID câu hỏi

        // Kiểm tra xem sinh viên đã làm bài này chưa
        $user = $request->user();
        $isCompleted = false;
        if ($user) {
            $isCompleted = \App\Models\Result::where('user_id', $user->id)
                ->where('exam_id', $exam->id)
                ->exists();
        }
        $data['is_completed'] = $isCompleted;

        return response()->json($data);
    }
    
}
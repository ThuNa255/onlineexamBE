<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Subject;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    private function mapExam(Exam $exam): array
    {
        return [
            'id' => $exam->id,
            'subject_id' => $exam->subject_id,
            'subject_name' => $exam->subject?->name ?? '',
            'name' => $exam->name,
            'start_time' => $exam->start_time,
            'end_time' => $exam->end_time,
            'duration' => $exam->duration,
            'total_questions' => $exam->total_questions,
            'password' => $exam->password,
            'status' => $exam->status,
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
        $exams = Exam::with('subject')->orderBy('created_at', 'desc')->get();
        return response()->json($exams->map(fn (Exam $exam) => $this->mapExam($exam)));
    }

    // Tạo bài thi mới
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject_id' => 'required|integer|exists:subjects,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'duration' => 'required|integer|min:1',
            'total_questions' => 'required|integer|min:1',
            'password' => 'nullable|string'
        ]);

        $validated['status'] = $this->calculateStatus($validated['start_time'], $validated['end_time']);

        $exam = Exam::create($validated);

        return response()->json([
            'message' => 'Tạo bài thi thành công',
            'exam' => $this->mapExam($exam->load('subject'))
        ], 201);
    }

    // Xem chi tiết 1 bài thi
    public function show($id)
    {
        $exam = Exam::with('subject')->findOrFail($id);
        return response()->json($this->mapExam($exam));
    }

    // Cập nhật bài thi
    public function update(Request $request, $id)
    {
        $exam = Exam::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'subject_id' => 'sometimes|required|integer|exists:subjects,id',
            'start_time' => 'sometimes|required|date',
            'end_time' => 'sometimes|required|date|after:start_time',
            'duration' => 'sometimes|required|integer|min:1',
            'total_questions' => 'sometimes|required|integer|min:1',
            'password' => 'nullable|string'
        ]);

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
            'exam' => $this->mapExam($exam->load('subject'))
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
    public function studentExams()
    {
        $exams = Exam::with('subject')->where('status', 'ongoing')->get();
        return response()->json($exams->map(fn (Exam $exam) => $this->mapExam($exam)));
    }

    // Sinh viên lấy đề thi để làm
    public function showForStudent($id)
    {
        $exam = Exam::with(['questions', 'subject'])->findOrFail($id);

        // BẢO MẬT: Ẩn đáp án đúng đi trước khi gửi về cho React
        $exam->questions->makeHidden(['correct_answer']);

        $data = $this->mapExam($exam);
        $data['questions'] = $exam->questions;

        return response()->json($data);
    }
    
}
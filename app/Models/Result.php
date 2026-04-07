<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    // Cập nhật tên bảng nếu trong xdpmw_test.sql khác 'results'
    protected $table = 'results';

    protected $fillable = [
        'user_id',      // ID của sinh viên
        'exam_id',      // ID của bài thi
        'score',        // Điểm số (ví dụ: hệ 10 hoặc hệ 100)
        'total_correct',// Số câu đúng
        'total_questions',
        'submitted_at',
        'completed_at'  // Thời gian hoàn thành bài thi
    ];

    // Mối quan hệ
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
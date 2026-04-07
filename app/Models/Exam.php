<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    // Cập nhật tên bảng cho đúng với file xdpmw_test.sql của bạn nếu cần
    // protected $table = 'exams';

    protected $fillable = [
        'name',
        'subject_id',
        'start_time',
        'end_time',
        'duration',
        'total_questions',
        'password',
        'status'
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    // Mối quan hệ: Một bài thi có nhiều câu hỏi
    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    // Mối quan hệ: Một bài thi có nhiều kết quả (của nhiều sinh viên)
    public function results()
    {
        return $this->hasMany(Result::class);
    }
}

<?php

namespace App\Models;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    // BẮT BUỘC SỬA: Thay 'questions' bằng tên bảng chính xác trong xdpmw_test.sql
    protected $table = 'questions'; 

    // Nếu khóa chính không phải là 'id' (ví dụ: 'question_id', 'ma_cau_hoi'), hãy khai báo:
    // protected $primaryKey = 'question_id';

    // Nếu bảng trong SQL của bạn KHÔNG có 2 cột created_at và updated_at, hãy thêm dòng này:
    // public $timestamps = false;

    // Khai báo CHÍNH XÁC tên các cột trong file SQL
    protected $fillable = [
        'exam_id', 
        'content',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'correct_answer',
        'subject_id',
        'difficulty'
    ];

    // Quan hệ: Câu hỏi thuộc về bài thi nào
    public function exam()
    {
        // 'exam_id' là tên cột khóa ngoại trong bảng questions
        return $this->belongsTo(Exam::class, 'exam_id'); 
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}

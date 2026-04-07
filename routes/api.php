<?php

use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\UserController;
// Tuyến đường công khai (Không cần token)
Route::post('/login', [AuthController::class, 'login']);

// Tuyến đường được bảo vệ (Bắt buộc phải truyền Bearer Token trong Header)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout']);
});

Route::prefix('student')->group(function () {
        Route::get('/exams', [ExamController::class, 'studentExams']); 
        Route::get('/exams/{id}', [ExamController::class, 'showForStudent']); 
        Route::post('/exams/{id}/submit', [ResultController::class, 'submit']); 
        Route::get('/results', [ResultController::class, 'myResults']); 
        Route::get('/dashboard', [DashboardController::class, 'studentDashboard']);
    });

Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
        // apiResource sẽ tự tạo các route: GET /exams, POST /exams, GET /exams/{id}, PUT /exams/{id}, DELETE /exams/{id}
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::apiResource('subjects', SubjectController::class);
        Route::apiResource('exams', ExamController::class);
        Route::apiResource('questions', QuestionController::class);
        Route::apiResource('students', StudentController::class);
        Route::apiResource('users', UserController::class);
        Route::get('/results', [ResultController::class, 'index']);
        Route::delete('/results/{id}', [ResultController::class, 'destroy']);
    });
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

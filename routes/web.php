<?php

use App\Events\TestReverbEvent;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuizMatchController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\UserController;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/quiz/search-user', [QuizMatchController::class, 'searchUser'])->name('quiz.search');
    Route::post('/quiz/send-challenge', [QuizMatchController::class, 'sendChallenge'])->name('quiz.sendChallenge');
    Route::post('/quiz/respond-challenge', [QuizMatchController::class, 'respondChallenge'])->name('quiz.respondChallenge');

    Route::get('/quiz/match/{session}', [QuizMatchController::class, 'showMatch'])->name('quiz.match');
    Route::get('/quiz/match/{session}/questions', [QuizMatchController::class, 'getQuestions'])->name('quiz.questions');
    Route::post('/quiz/match/{session}/submit', [QuizMatchController::class, 'submitAnswers'])->name('quiz.submit');
});

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::resource('users', UserController::class)->except(['create', 'show', 'edit']);

    Route::resource('categories', CategoryController::class)->except(['create', 'show', 'edit']);
    Route::resource('questions', QuestionController::class)->except(['create', 'show', 'edit']);
    Route::get('questions/sample-csv', [QuestionController::class, 'downloadSampleCsv'])->name('questions.sample-csv');
    Route::post('questions/import-csv', [QuestionController::class, 'importCsv'])->name('questions.import-csv');
    Route::resource('questions', QuestionController::class)->except(['create', 'show', 'edit']);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/admin', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('admin.dashboard');
});

Route::get('/fire-event', function () {
    $message = request('message', 'QuizBattle Reverb + Echo is working perfectly!');
    
    TestReverbEvent::dispatch($message);

    return response()->json([
        'status' => 'Event Dispatched Successfully!',
        'message' => $message,
    ]);
});

// Pure API Driven Vue Client Views
Route::get('/app/login', function () {
    return Inertia::render('Auth/ApiLogin');
})->name('app.login');

// Route::get('/app/dashboard', function () {
//     return Inertia::render('Quiz/ApiDashboard');
// })->name('app.dashboard');

// ⚔️ Match Arena View Route
// Route::get('/app/quiz/match/{session}', function ($session) {
//     return Inertia::render('Quiz/ApiMatchArena', [
//         'sessionId' => (int) $session,
//     ]);
// })->name('app.quiz.match');

Route::get('/app/quiz/match/{session}', function ($session) {
    return Inertia::render('Quiz/ApiMatchArena', [
        'sessionId' => (int) $session,
    ]);
})->name('app.quiz.match');

Route::get('/app/dashboard', function () {
    return Inertia::render('Quiz/OnboardingDashboard');
});

require __DIR__.'/auth.php';

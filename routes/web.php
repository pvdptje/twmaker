<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\PageHtmlDownloadController;
use App\Http\Controllers\ProjectHtmlDownloadController;
use App\Livewire\Builder\Workspace\Workspace;
use App\Livewire\Projects\ProjectDashboard\ProjectDashboard;
use App\Livewire\Projects\ProjectList\ProjectList;
use App\Livewire\Setup\LlmSetup;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/', ProjectList::class)->name('projects.index');
    Route::get('/setup/llm', LlmSetup::class)->name('setup.llm');
    Route::get('/projects/{project}', ProjectDashboard::class)->name('projects.show');
    Route::get('/projects/{project}/download-html', ProjectHtmlDownloadController::class)->name('builder.projects.download-html');
    Route::get('/projects/{project}/pages/{page}', Workspace::class)->name('builder.workspace');
    Route::get('/projects/{project}/pages/{page}/download-html', PageHtmlDownloadController::class)->name('builder.pages.download-html');
});

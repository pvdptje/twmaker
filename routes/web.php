<?php

use App\Http\Controllers\PageHtmlDownloadController;
use App\Http\Controllers\ProjectHtmlDownloadController;
use App\Livewire\Builder\Workspace\Workspace;
use App\Livewire\Projects\ProjectDashboard\ProjectDashboard;
use App\Livewire\Projects\ProjectList\ProjectList;
use App\Livewire\Setup\LlmSetup;
use Illuminate\Support\Facades\Route;

Route::get('/', ProjectList::class)->name('projects.index');
Route::get('/setup/llm', LlmSetup::class)->name('setup.llm');
Route::get('/projects/{project}', ProjectDashboard::class)->name('projects.show');
Route::get('/projects/{project}/download-html', ProjectHtmlDownloadController::class)->name('builder.projects.download-html');
Route::get('/projects/{project}/pages/{page}', Workspace::class)->name('builder.workspace');
Route::get('/projects/{project}/pages/{page}/download-html', PageHtmlDownloadController::class)->name('builder.pages.download-html');

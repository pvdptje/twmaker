<?php

use App\Livewire\Builder\Workspace\Workspace;
use App\Livewire\Projects\ProjectDashboard\ProjectDashboard;
use App\Livewire\Projects\ProjectList\ProjectList;
use Illuminate\Support\Facades\Route;

Route::get('/', ProjectList::class)->name('projects.index');
Route::get('/projects/{project}', ProjectDashboard::class)->name('projects.show');
Route::get('/projects/{project}/pages/{page}', Workspace::class)->name('builder.workspace');

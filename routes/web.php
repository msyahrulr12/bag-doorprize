<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
Route::get('/draw/{uuid}', \App\Livewire\Public\GrandDrawing::class)->name('public.draw');
Route::get('/draw-bulk/{uuid}', \App\Livewire\Public\BulkDrawing::class)->name('public.draw-bulk');
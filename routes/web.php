<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response('<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title></title></head><body></body></html>', 200, ['Content-Type' => 'text/html']));

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// A notes-only MCP token (ability `notes`) opens /mcp/notes and nothing else, so this asks for a full one.
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum', \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class.':mcp:use']);

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BrigadeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            //
        } catch (\Exception $e) {
            Log::error('Error in Api\BrigadeController@index: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            //
        } catch (\Exception $e) {
            Log::error('Error in Api\BrigadeController@store: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            //
        } catch (\Exception $e) {
            Log::error('Error in Api\BrigadeController@show: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            //
        } catch (\Exception $e) {
            Log::error('Error in Api\BrigadeController@update: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            //
        } catch (\Exception $e) {
            Log::error('Error in Api\BrigadeController@destroy: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

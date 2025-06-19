<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BrigadeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // This can be used for a full page view if needed
        return view('brigade.show', ['id' => $id]);
    }
    
    /**
     * Get brigade data via API
     */
    public function getBrigadeData($id)
    {
        \Log::info('Начало обработки запроса для бригады ID: ' . $id);
        try {
            // Получаем данные о бригаде
            $brigade = DB::table('brigades')
                ->where('id', $id)
                ->first();

            if (!$brigade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Бригада не найдена'
                ], 404);
            }

            // Получаем всех членов бригады с их должностями
            $members = DB::table('brigade_members')
                ->join('employees', 'brigade_members.employee_id', '=', 'employees.id')
                ->leftJoin('positions', 'employees.position_id', '=', 'positions.id')
                ->where('brigade_members.brigade_id', $id)
                ->select(
                    'employees.*',
                    'positions.name as position_name'
                )
                ->get();

            // Получаем данные о бригадире
            $leader = null;
            if ($brigade->leader_id) {
                $leader = DB::table('employees')
                    ->leftJoin('positions', 'employees.position_id', '=', 'positions.id')
                    ->where('employees.id', $brigade->leader_id)
                    ->select('employees.*', 'positions.name as position_name')
                    ->first();
            }
            
            // Добавляем информацию о том, кто является лидером в списке участников
            $members = $members->map(function($member) use ($brigade) {
                $member->is_leader = ($member->id == $brigade->leader_id);
                return $member;
            });

            return response()->json([
                'success' => true,
                'brigade' => $brigade,
                'leader' => $leader,
                'members' => $members
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting brigade data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении данных бригады'
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

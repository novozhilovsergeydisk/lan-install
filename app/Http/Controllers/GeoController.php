<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GeoController extends Controller
{
    public function getAddresses()
    {
        $data = DB::select("
            SELECT a.id, a.street, a.houses, a.district, c.name as city, r.name as region
            FROM addresses a
            JOIN cities c ON a.city_id = c.id
            LEFT JOIN regions r ON c.region_id = r.id
            ORDER BY c.name, a.street, a.houses
        ");

        return response()->json($data);
    }

    public function getCities()
    {
        $cities = DB::select('SELECT id, name FROM cities ORDER BY name');
        return response()->json($cities);
    }

    public function getRegions()
    {
        $regions = DB::select('SELECT id, name FROM regions ORDER BY name');
        return response()->json($regions);
    }

    public function addAddress(Request $request)
    {
        $request->validate([
            'street' => 'required|string|max:255',
            'houses' => 'required|string|max:255',
            'district' => 'required|string|max:255',
            'city_id' => 'required|exists:cities,id',
            'responsible_person' => 'nullable|string|max:100',
            'comments' => 'nullable|string'
        ]);

        // Добавляем адрес и получаем его ID
        $addressId = DB::table('addresses')->insertGetId([
            'street' => $request->street,
            'houses' => $request->houses,
            'district' => $request->district,
            'city_id' => $request->city_id,
            'responsible_person' => $request->responsible_person,
            'comments' => $request->comments
        ]);
        
        // Получаем информацию о городе
        $city = DB::table('cities')->where('id', $request->city_id)->first();
        $cityName = $city ? $city->name : '';
        
        // Создаем объект с информацией о добавленном адресе
        $addressInfo = [
            'id' => $addressId,
            'city' => $cityName,
            'district' => $request->district,
            'street' => $request->street,
            'houses' => $request->houses,
            'responsible_person' => $request->responsible_person,
            'comments' => $request->comments
        ];

        return response()->json([
            'success' => true, 
            'message' => 'Адрес добавлен',
            'address' => $addressInfo
        ]);
    }

    public function addCity(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'region_id' => 'required|exists:regions,id'
        ]);

        DB::insert('INSERT INTO cities (name, region_id) VALUES (?, ?)', [
            $request->name,
            $request->region_id
        ]);

        return response()->json(['success' => true, 'message' => 'Город добавлен']);
    }

    public function addRegion(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        DB::insert('INSERT INTO regions (name) VALUES (?)', [
            $request->name
        ]);

        return response()->json(['success' => true, 'message' => 'Регион добавлен']);
    }
}


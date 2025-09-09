<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PhotoReportController extends Controller
{
    /**
     * Display a listing of the photo reports.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_id' => 'required|exists:requests,id',
        ]);

        // if ($validator->fails()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Неверный ID заявки',
        //     ]);
        // }

        // $requestId = $validator->validated()['request_id'];

        // Используем raw SQL запрос для корректной работы с массивами PostgreSQL
        $sql = "
            SELECT 
                r.id AS request_id,
                r.number AS request_number,
                c.id AS comment_id,
                c.comment AS comment_text,
                c.created_at AS comment_created_at,
                json_agg(p.path) AS photo_paths,
                json_agg(p.original_name) AS photo_names
            FROM requests r
            JOIN request_comments rc ON r.id = rc.request_id
            JOIN comments c ON rc.comment_id = c.id
            JOIN comment_photos cp ON c.id = cp.comment_id
            JOIN photos p ON cp.photo_id = p.id
            GROUP BY r.id, r.number, c.id, c.comment, c.created_at
            ORDER BY r.id DESC, c.id
        ";

        $results = DB::select($sql);
        
        $groupedResults = [];
        
        foreach ($results as $row) {
            $requestId = $row->request_id;
            
            if (!isset($groupedResults[$requestId])) {
                $groupedResults[$requestId] = [
                    'id' => $row->request_id,
                    'number' => $row->request_number,
                    'comments' => []
                ];
            }
            
            // Декодируем JSON массивы
            $photoPaths = json_decode($row->photo_paths, true) ?: [];
            $photoNames = json_decode($row->photo_names, true) ?: [];
            
            $photos = [];
            foreach ($photoPaths as $index => $path) {
                $photoName = $photoNames[$index] ?? basename($path);
                $photoUrl = str_starts_with($path, 'http') ? $path : asset('storage/' . ltrim($path, '/'));
                
                $photos[] = [
                    'url' => $photoUrl,
                    'path' => $path,
                    'original_name' => $photoName,
                    'created_at' => $row->comment_created_at
                ];
            }
            
            $groupedResults[$requestId]['comments'][] = [
                'id' => $row->comment_id,
                'text' => $row->comment_text,
                'created_at' => $row->comment_created_at,
                'photos' => $photos
            ];
        }
        
        $photos = array_values($groupedResults);

        return response()->json([
            'success' => true,
            'data' => $photos,
        ]);

        // return view('photo-reports.index', compact('photos'));
    }

    /**
     * Format file size to human readable format
     *
     * @param int $bytes
     * @return string
     */
    private function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            return $bytes . ' bytes';
        } elseif ($bytes == 1) {
            return '1 byte';
        } else {
            return '0 bytes';
        }
    }
}

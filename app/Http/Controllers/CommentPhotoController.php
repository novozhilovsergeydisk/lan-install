<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommentPhotoController extends Controller
{
    /**
     * Получить фотографии для комментария
     *
     * @param  int  $commentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($commentId)
    {
        try {
            // Validate comment_id
            if (!is_numeric($commentId) || $commentId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid comment ID'
                ], 400);
            }

            $photos = DB::table('comment_photos')
                ->join('photos', 'comment_photos.photo_id', '=', 'photos.id')
                ->where('comment_photos.comment_id', $commentId)
                ->select([
                    'photos.id',
                    'photos.path',
                    'photos.original_name',
                    'photos.file_size',
                    'photos.mime_type',
                    'photos.width',
                    'photos.height',
                    'photos.created_at',
                ])
                ->get()
                ->map(function($photo) {
                    return [
                        'id' => $photo->id,
                        'url' => asset('storage/' . $photo->path),
                        'path' => $photo->path,
                        'original_name' => $photo->original_name,
                        'file_size' => $photo->file_size,
                        'mime_type' => $photo->mime_type,
                        'width' => $photo->width,
                        'height' => $photo->height,
                        'created_at' => $photo->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $photos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Не удалось загрузить фотографии',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    
    /**
     * Получить файлы для комментария
     *
     * @param  int  $commentId
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Получить файлы для комментария
     *
     * @param  int  $commentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCommentFiles($commentId)
    {
        try {
            // Validate comment_id
            if (!is_numeric($commentId) || $commentId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid comment ID'
                ], 400);
            }

            $files = DB::table('comment_files')
                ->join('files', 'comment_files.file_id', '=', 'files.id')
                ->where('comment_files.comment_id', $commentId)
                ->select([
                    'files.id',
                    'files.path',
                    'files.original_name',
                    'files.file_size',
                    'files.mime_type',
                    'files.extension',
                    'files.created_by',
                    'files.created_at',
                    'files.updated_at',
                ])
                ->get()
                ->map(function($file) {
                    return [
                        'id' => $file->id,
                        'url' => asset('storage/' . $file->path),
                        'path' => $file->path,
                        'original_name' => $file->original_name,
                        'file_size' => $file->file_size,
                        'mime_type' => $file->mime_type,
                        'extension' => $file->extension,
                        'created_by' => $file->created_by,
                        'created_at' => $file->created_at,
                        'updated_at' => $file->updated_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $files
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Не удалось загрузить файлы',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

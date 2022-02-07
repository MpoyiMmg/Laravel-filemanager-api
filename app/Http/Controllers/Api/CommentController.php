<?php

namespace App\Http\Controllers\Api;

use App\Comment;
use App\Mail\MentionMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class CommentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index($id) {
        $commentsQuery = "SELECT users.*, comments.message, comments.created_at
                            FROM comments INNER JOIN users ON users.id = comments.user_id
                            WHERE comments.doc_id = ? ORDER BY comments.created_at DESC";
        $comments = DB::select($commentsQuery, [$id]);
        return response()->json($comments);
    }

    public function store(Request $request) {
        try {
            $savedComment = Comment::Create([
                'doc_id' => $request->doc_id,
                'user_id' => Auth::id(),
                'message' => $request->message,
            ]);
            $savedCommentsQuery = "SELECT users.*, comments.message, comments.created_at
                                    FROM comments INNER JOIN users ON users.id = comments.user_id
                                    WHERE comments.doc_id = ? AND comments.user_id = ? AND comments.id = ?
                                    ORDER BY comments.created_at DESC";
            $comment = DB::select($savedCommentsQuery, [$request->doc_id, Auth::id(), $savedComment->id]);
            return response()->json($comment, 201);
        } catch (Exception $e) {
            return response()->json(
               [
                 'errors' => $e->getMessage()
               ], 500);
        }
    }
}

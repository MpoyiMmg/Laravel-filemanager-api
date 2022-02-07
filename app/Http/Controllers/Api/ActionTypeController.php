<?php

namespace App\Http\Controllers\Api;

use App\ActionType;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ActionTypeController extends Controller
{
    public function __construct() {
        $this->middleware('auth:sanctum');
    }
    
    public function index()
    {
        try {
            $actionType = ActionType::all();
        } catch (Exception $e) {
            return response()->json(
                [
                    'errors' => $e->getMessage()
                ], 
                500);
        }

        return response()->json($actionType, 200);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Action;
use App\ActionTypes;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ActionController extends Controller
{
  public function __construct() {
    $this->middleware('auth:sanctum');
  }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
      try {
        $action = DB::select(" 
        SELECT actions.*, action_types.name action_name from actions
        inner join action_types on action_types.id = actions.actionType_id");
        return response()->json(["actions" => $action], 200);
      } catch (Exception $e) {
        return response()->json(
          [
            'errors' => $e->getMessage()
          ], 500);
      }
    }  
  

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {   
        $validated = Validator::make($request->all(), [
            'name' => "required|unique:actions",
            'actionType_id' => "required"
        ]); 

        if ($validated->fails()) {
            return response()->json(
                [
                    'errors' => $validated->errors()
                ], 422);
        }

        else {
            try {
                $action = new Action;
                $action->name = $request->name;
                $action->actionType_id = $request->actionType_id;
                $action->save();

                $data = ['actionCreated' => $action];
            } catch (Exception $e) {
               return response()->json(
                   [
                       'errors' => $e->getMessage()
                   ], 500);
            }
        }
        return response()->json($data, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Action  $action
     * @return \Illuminate\Http\Response
     */
    public function show(Action $action)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Action  $action
     * @return \Illuminate\Http\Response
     */
    public function edit(Action $action)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Action  $action
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Action $action)
    {
        { $validated = Validator::make($request->all(), [
            'actionType_id' => "required"
          ]);
      
          if ($validated->fails()) {
            return response()->json(
              [
                'errors' => $validated->errors()
              ], 422);
          }
          else {
            try {
              $action = $action->find($request->id);
        
              if ($action !== null) {
                $action->name = $request->name;
                $action->actionType_id = $request->actionType_id;
                $action->save();
              }
              else {
                return response()->json(
                  [
                    'message' => 'L action renseignée est inéxistante'
                  ], 404);
              }
            } catch (Exception $e) {
              return response()->json(
                [
                  'errors' => $e->getMessage()
                ], 500);
            }
          }
      
          $data = ['actionUpdated' => $action];
          return response()->json($data, 200);
        }
      
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Action  $action
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request , Action $action)
    {
      try {
        $action_id = $request->id;
        Action::where('id', $action_id)->delete();

     } catch (Exception $exception) {
        return response()->json([
            'error' => $exception->getMessage(),
        ], 500);
     }
  }
}

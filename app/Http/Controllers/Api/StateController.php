<?php

namespace App\Http\Controllers\Api;

use App\State;
use App\Process;
use App\Profile;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class StateController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $states = State::all();
            $data = ['states' => $states];
        } catch (Exception $e) {
            return response()->json(
                [
                    'errors' => $e->getMessage()
                ], 
                500);
        }

        return response()->json($data, 200);
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
        $data = $request->all();
         
        foreach ($data as $dt) {
            $data_validators = [
                'name' => $dt['name'],
                'process_id' => $dt['process_id']
            ];
            $validated = Validator::make($data_validators, [
                'name' => "required|unique:states",
                'process_id' => 'required'
            ]);
        }
         

        if ($validated->fails()) {
            return response()->json(
                [
                    'errors' => $validated->errors()
                ], 422);
        }

        else {
            try {
                $state_created = [];
                foreach ($data as $dt) {
                    $state = new State;
                    $process = Process::find($dt['process_id']);

                    if ($process === null) {
                        return response()->json(
                            [
                                'message' => 'Le process renseigné est inéxistant'
                            ], 404);
                    }
                    else {
                        $state->name = $dt['name'];
                        $state->process_id = $dt['process_id'];
                        $state->save();
                        
                        array_push($state_created, $state);
                    }
                }
                $data = ['stateCreated' => $state_created];
            } catch (Exception $e) {
               return response()->json(
                   [
                       'errors' => $e->getMessage()
                   ], 500);   
            }
        }
        return response()->json($data, 201);
    }

    
    public function set_target_profile(Request $request, $id) {
        $validated = Validator::make($request->all(), [
            'target_profile' => 'required'
        ]);

        if ($validated->fails()) {
            return response()->json(
                [
                    'errors' => $validated->errors()
                ], 422);
        }
        else {
            try {
                $state = State::find($id);
                $profile = Profile::find($request->target_profile);

                if ($state !== null || $profile !== null) {
                    $state->profile_id = $profile->id;
                    $state->save();

                    $data = ['stateUpdated' => $state];
                }
                else {
                    return response()->json(
                        [
                            'message' => 'Le state renseigné est inéxistant'
                        ], 404);
                } 
            } catch (Exception $e) {
                return response()->json(
                    [
                        'errors' => $e->getMessage()
                    ], 500);
            }

            return response()->json($data, 200);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\State  $state
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, State $state)
    {
        try {
            $state = $state->find($request->id);

            if ($state !== null) {
                $data = ['state' => $state];
            }
            else {
                return response()->json(
                    [
                        'message' => 'Le state renseigné est inéxistant'
                    ], 404);
            }
        } catch (Exception $e) {
            return response()->json(
                [
                    'errors' => $e->getMessage()
                ], 500);
        }
        
        return response()->json($data, 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\State  $state
     * @return \Illuminate\Http\Response
     */
    public function edit(State $state)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\State  $state
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, State $state)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\State  $state
     * @return \Illuminate\Http\Response
     */
    public function destroy(State $state)
    {
        //
    }
}

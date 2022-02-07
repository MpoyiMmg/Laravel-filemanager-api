<?php

namespace App\Http\Controllers\Api;

use App\State;
use App\Transition;
use App\TransitionsAction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class TransitionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
        $transitions = $request->all();

        foreach ($transitions as $transition) {
            $validated = Validator::make($transition, [
                'previous_state' => "required",
                'next_state' => "required",
                'action' => "required"
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
                $transitions_created = [];
                $transitions_action_created = [];

                foreach ($transitions as $trans) {
                    $transition  = new Transition;
                    $transition_action = new TransitionsAction;
                    
                    $previous_state = State::find($trans['previous_state']);
                    $next_state = State::find($trans['next_state']);

                    if($previous_state === null || $next_state === null) {
                        return response()->json(
                            [
                                'message' => 'Le(s) state(s) referencés n\'existe(nt) pas'
                            ], 404);
                    }
                    elseif ($action === null) {
                        return response()->json(
                            [
                                'message' => 'L\'action renseigné n\'existe pas'
                            ], 404);
                    }
                    else {
                        $transition->previousState = $previous_state->id;
                        $transition->nextState = $next_state->id;
                        $transition->save();
                        
                        $transition_action->transition_id = $transition->id;
                    }
                    $transition_action->save();

                    array_push($transitions_created, $transition);
                    array_push($transitions_action_created, $transition_action);
                } 
                $data = [
                           'transitionCreated' => $transitions_created,
                           'transitionActionCreated' => $transitions_action_created
                        ];
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
     * @param  \App\Transition  $transition
     * @return \Illuminate\Http\Response
     */
    public function show(Transition $transition)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Transition  $transition
     * @return \Illuminate\Http\Response
     */
    public function edit(Transition $transition)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Transition  $transition
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Transition $transition)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Transition  $transition
     * @return \Illuminate\Http\Response
     */
    public function destroy(Transition $transition)
    {
        //
    }
}

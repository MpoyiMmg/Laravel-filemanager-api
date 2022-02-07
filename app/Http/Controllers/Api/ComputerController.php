<?php

namespace App\Http\Controllers\Api;

use Validator;
use App\Computer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Exception;

class ComputerController extends Controller
{
    
    /**
     * Construct of ComputerController
     *
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return $request->user()->computers;
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
        $user_id = $request->user()->id;
        
        try {
            
            $this->validate($request, [
                'name' => 'required|max:255',
                'model' => 'required',
                'generation' => 'required',
                'price' => 'required'
            ]);

            $computer = Computer::Create([
                'name' => $request->name,
                'model' => $request->model,
                'generation' => $request->generation,
                'price' => $request->price,
                'user_id' => $user_id,
            ]);

        } catch(Exception $exception) {

            return response()->json([
                'error' => [
                    "message" => "Les données saisies sont invalides.",
                    "type" => "ValidationException."
                ],
            ], 422);
        }
        
        return response()->json([
            'data' => $computer
        ], 201);

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Computer  $computer
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
      return Computer::find($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Computer  $computer
     * @return \Illuminate\Http\Response
     */
    public function edit(Computer $computer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Computer  $computer
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Computer $computer)
    {
        $id = $request->route('id');

        try {
            
            $this->validate($request, [
                'name' => 'required|max:255',
                'model' => 'required',
                'generation' => 'required',
                'price' => 'required'
            ]);

            try {

                $computer = Computer::findOrFail($id);
    
                $computer->name = $request->name;
                $computer->model = $request->model;
                $computer->generation = $request->generation;
                $computer->price = $request->price;
    
                $computer->save();

            }catch (Exception $exception) {

                return response()->json([
                    'error' => [
                        "message" => "Aucun ordinateur n'a pour id $id",
                        "type" => "ModelNotFoundException"
                    ],
                ], 404);
            }

        } catch(Exception $exception) {

            return response()->json([
                'error' => [
                    "message" => "Les données saisies sont invalides.",
                    "type" => "ValidationException."
                ],
            ], 422);

        }
 
        return response()->json([
            'data' => $computer
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Computer  $computer
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Computer::where('id', '=', $id)->delete();
    }
}

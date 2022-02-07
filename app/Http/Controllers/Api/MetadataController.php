<?php

namespace App\Http\Controllers\Api;

use App\Metadata;
use App\DocumentMetadata;
use Illuminate\Http\Request;
use App\DocumentTypeMetadata;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\MetadataOptions;
use App\Option;
use Illuminate\Support\Facades\Validator;

class MetadataController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        try {
            if (!$request->user()->can('list-metadata')) {
                $data = ['error' => 'Action non autorisée à cet utilisateur'];
                return response()->json($data, 403);
            }
        } catch (Exceotion $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
        $data = Metadata::orderBy('created_at', 'desc')->get();

        return response()->json($data, 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            if (!$request->user()->can('create-metadata')) {
                $data = ['error' => 'Action non autorisée à cet utilisateur'];
                return response()->json($data, 403);
            }

            $validator = Validator::make($request->all(), [
                'label' => 'required|max:25',
                'type' => 'required',
                'required' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors(),
                ], 422);
            }

            $usedName = Metadata::where('label', $request->label)->first();

            if ($usedName) {
                return response()->json([
                    'errors' => [],
                ], 422);
            }

            $metadata = Metadata::Create([
                'label' => $request->label,
                'type' => $request->type,
                'required' => $request->required == "true" ? true : false,
            ]);

            if (isset($request->options)) {
                $options = json_decode($request->options);
                foreach ($options as $option) {
                    $metadataOption = new MetadataOptions;
                    $metadataOption->metadata_id = $metadata->id;
                    $metadataOption->option_id = $option->id;
                    $metadataOption->save();
                }
            }
        } catch (Exception $exception) {

            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'metadata' => $metadata,
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        try {
            $data = Metadata::find($request->id);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
        return response()->json($data, 200);
    }

    public function metadataOptions(Request $request)
    {
        $metadataOptions = MetadataOptions::where('metadata_id', $request->id)->get();
        $options = [];
        if (!empty($metadataOptions)) {
            foreach ($metadataOptions as $metadataOption) {
                $option = Option::find($metadataOption);
                array_push($options, $option[0]);
            }
        }
        return response()->json($options, 200);
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $metadata_id = $request->id;
        try {
            if (!$request->user()->can('update-metadata')) {
                $data = ['error' => 'Action non autorisée à cet utilisateur'];
                return response()->json($data, 403);
            }
            $validator = Validator::make($request->all(), [
                'label' => 'required|max:25',
                'type' => 'required',
                'required' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors(),
                ], 422);
            }

            if ($request->type !== 'list') {
                MetadataOptions::where('metadata_id', $metadata_id)->delete();
            }
            
            if (isset($request->options)) {
                $options = json_decode($request->options);
                MetadataOptions::where('metadata_id', $metadata_id)->delete();
                
                foreach ($options as $option) {
                    $metadataOption = new MetadataOptions;
                    $metadataOption->metadata_id = $metadata_id;
                    $metadataOption->option_id = $option->id;
                    $metadataOption->save();
                }
            }

            $metadata = Metadata::findOrFail($metadata_id);
            $metadata->label = $request->label;
            $metadata->type = $request->type;
            $metadata->required = $request->required == 1 ? true : false;
            $metadata->save();
        } catch (Exception $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'metadata' => $metadata,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        try {
            if (!$request->user()->can('delete-metadata')) {
                $data = ['error' => 'Action non autorisée à cet utilisateur'];
                return response()->json($data, 403);
            }
            $metadata_id = $request->id;
            MetadataOptions::where('metadata_id', $metadata_id)->delete();
            DocumentTypeMetadata::where('metadata_id', $metadata_id)->delete();
        } catch (Exception $exception) {

            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        }
        Metadata::where('id', $metadata_id)->delete();
    }
}

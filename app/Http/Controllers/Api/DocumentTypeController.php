<?php

namespace App\Http\Controllers\Api;

use App\DocumentType;
use Illuminate\Http\Request;
use App\DocumentTypeMetadata;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class DocumentTypeController extends Controller
{
    public function __construct() {
        $this->middleware('auth:sanctum');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            // if (!$request->user()->can('list-document-types')) {
            //     $data = ['error' => 'Action non autorisée à cet utilisateur'];
            //     return response()->json($data, 403);
            // }
            $documentTypes = DocumentType::all();
        } catch (Exception  $e) {
            return response()->json(
                [
                    'error' => $e->getMessage()
                ], 500);
        }

        return response()->json(
            [
                'data' => $documentTypes
            ], 200);
    }
    
    public function countDocumentTypes(Request $request)
    {
        $documentstypes = DocumentType::all();

        $documetstypeswithCount= [
            'documentstypes' => $documentstypes,
            'documetstypes_count' => $documentstypes->count()
        ];

        return $documetstypeswithCount;
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
        if (!$request->user()->can('create-document-types')) {
            $data = ['error' => 'Action non autorisée à cet utilisateur'];
            return response()->json($data, 403);
        }
        $validated = Validator::make($request->all(), [
            'name' => 'required|unique:document_types|max:30',
            'description' => 'required|max:255'
        ]);

        if ($validated->fails()) {
            return response()->json(
                [
                    'errors' => $validated->errors()
                ], 422);
        }

        else {
            try {
                $metadata = json_decode($request->metadata);
                $documentType = new DocumentType;

                $documentType->name = $request->name;
                $documentType->description = $request->description;
                $documentType->save();
                
                for ($i = 0; $i <= count($metadata) - 1; $i++) {
                    $documentTypeMetadata = new DocumentTypeMetadata;
                    $documentTypeMetadata->document_type_id = $documentType->id;
                    $documentTypeMetadata->metadata_id = $metadata[$i]->id;

                    $documentTypeMetadata->save();
                }
            } catch (Exception $e) {
                return response()->json(
                    [
                        'errors' => $e->getMessage()
                    ], 500);
            }

            return response()->json(
                [
                    'data' => $documentType
                ], 201);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\DocumentType  $documentType
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, DocumentType $documentType)
    { 
        try {

            $documentType= DocumentType::find($request->id);

            if ($documentType === null) {
                return response()->json(
                    [
                        'message' => 'Ce type de document n\'existe pas'
                    ], 404);
            }
            $documentTypeMetadata = DB::table('metadata')
                                    ->join('document_type_metadata', 'document_type_metadata.metadata_id', 'metadata.id')
                                    ->join('document_types', 'document_type_metadata.document_type_id', 'document_types.id')
                                    ->select(
                                        'metadata.id', 'metadata.label', 'metadata.type'
                                    )
                                    ->where('document_type_metadata.document_type_id', $request->id)
                                    ->get();
            $metadata = $documentTypeMetadata->map(function ($metadata) {
                if ($metadata->type === 'list') {
                    $options = DB::table('options')
                                ->join('metadata_options', 'metadata_options.option_id', 'options.id')
                                ->join('metadata', 'metadata_options.metadata_id', 'metadata.id')
                                ->where('metadata_options.metadata_id', $metadata->id)
                                ->select('options.label as option_label')
                                ->get();
                    return [
                        'id' => $metadata->id,
                        'label' => $metadata->label,
                        'type' => $metadata->type,
                        'options' => $options,
                    ];
                }
                return $metadata;
            });
            
            return response()->json(
                [
                    "Metadata" => $metadata,
                    "DocumentType" => $documentType
                ], 200);

        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage() 
            ], 500);
        }
        
    }
    public function countStat()
    {
        try {
            $stat = DB:: select ("SELECT document_types.id, document_types.name, 
                                 (SELECT COUNT(documents.id) total FROM documents 
                                  WHERE documents.document_type_id = document_types.id) as total FROM document_types");
            return response()->json(
              [
                "Stat" => $stat,
              ],
               200);
          } catch (Exception $e) {
            return response()->json(
              [
                'errors' => $e->getMessage()
              ], 500);
          }
          
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\DocumentType  $documentType
     * @return \Illuminate\Http\Response
     */
    public function edit(DocumentType $documentType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\DocumentType  $documentType
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        try {
            if (!$request->user()->can('update-document-types')) {
                $data = ['error' => 'Action non autorisée à cet utilisateur'];
                return response()->json($data, 403);
            }
            $validated = Validator::make($request->all(), [
                'name' => 'required|max:30',
                'description' => 'required|max:255'
            ]);

            if ($validated->fails()) {
                return response()->json(
                    [
                        'errors' => $validated->errors()
                    ], 422);
            }
            else {
                $documentType = new DocumentType;


                if ($documentType === null) {
                    return response()->json(
                        [
                            'message' => 'Ce document n\'existe pas'
                        ], 404);
                }
                else {
                    $documentType = $documentType->find($request->id);
                    $documentType->name = $request->name;
                    $documentType->description = $request->description;
                    $documentType->save();

                    $documentTypeMetadata = DocumentTypeMetadata::where('document_type_id', $request->id)->get();
                    if (!empty($documentTypeMetadata)) {
                        DocumentTypeMetadata::where('document_type_id', $request->id)->delete();
                    }

                    $metadata = json_decode($request->metadata);

                    for ($i = 0; $i <= count($metadata) - 1; $i++) {
                        $documentTypeMetadata = new DocumentTypeMetadata;
                        $documentTypeMetadata->document_type_id = $documentType->id;
                        $documentTypeMetadata->metadata_id = $metadata[$i]->id;
                        $documentTypeMetadata->save();
                    }
                }
            }
        }
        catch(Exception $e) {
            return response()->json(
                [
                    "error" => $e->getMessage()
                ], 500);
        }

        return response()->json(
            [
                'documentTypeUpdated' => $documentType,
                'documentTypeMetadataUpdated' => $documentTypeMetadata
            ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\DocumentType  $documentType
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, DocumentType $documentType)
    {
        try{
            if (!$request->user()->can('delete-document-types')) {
                $data = ['error' => 'Action non autorisée à cet utilisateur'];
                return response()->json($data, 403);
            }
            $documentType = $documentType->find($request->id);
            
            if (empty($documentType)) {
                return response()->json(
                    [
                        'message' => 'Ce document n\'existe pas'
                    ], 404);
            }
            else {
                DocumentTypeMetadata::where('document_type_id', $request->id)->delete();
                $documentType->delete();
            }
        }
        catch (Exception  $e) {
            return response()->json(
                [
                    "error" => $e->getMessage()
                ], 500);
        }
        return response()->json(
            [
                "message" => "deleted"
            ], 200);
    }
}

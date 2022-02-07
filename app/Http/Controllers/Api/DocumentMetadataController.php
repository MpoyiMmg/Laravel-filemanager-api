<?php

namespace App\Http\Controllers\Api;

use App\Document;
use App\Metadata;
use App\DocumentMetadata;
use App\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\MetadataOptions;
use Dompdf\Options;

class DocumentMetadataController extends Controller
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
        $identities = (array) $request->data;
        $document= $request->document;
        $results = [];

        foreach ($identities as $identitie) {
            
            $metadata = Metadata::where('id', $identitie['id'])->first();
            $temp_result = [
                'metadata_label' => $metadata->label,
                'value' => $identitie['newValue']
            ];
            
            $documentMetadata = new DocumentMetadata();

            $documentMetadata->document_id = $document;
            $documentMetadata->metadata_id = $metadata->id;
            $documentMetadata->value = $identitie['newValue'];

            $documentMetadata->save();
                

            array_push($results, $temp_result);
        }

        return response()->json(
            [
                'documentMetadata' => $results
            ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\DocumentMetadata  $documentMetadata
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        try {

            $document= Document::find($request->id);

            if ($document === null) {
                return response()->json(
                    [
                        'message' => "Aucun document ne correspond à cet Id"
                    ], 404);
            }

            $metadataValues = DB::table('document_metadata')
                        ->join('documents', 'document_metadata.document_id','documents.id')
                        ->join('metadata', 'document_metadata.metadata_id', 'metadata.id')
                        ->select(
                            "documents.id", "document_metadata.metadata_id", 
                            "metadata.label", "document_metadata.value", 
                            "metadata.type", "metadata.created_at"
                            )
                        ->where("documents.id", $request->id)
                        ->orderBy("document_metadata.created_at", "DESC")
                        ->get();            

           $metadataWithOptions = $metadataValues->map(function($metadata) {
                                    if($metadata->type === 'list') {
                                        $options = DB::table('options')
                                                        ->join('metadata_options', 'metadata_options.option_id', 'options.id')
                                                        ->join('metadata', 'metadata_options.metadata_id', 'metadata.id')
                                                        ->where('metadata_options.metadata_id', $metadata->metadata_id)
                                                        ->select('options.label as option_label')
                                                        ->get();
                                                        
                                        return [
                                            'id' => $metadata->id,
                                            'label' => $metadata->label,
                                            'type' => $metadata->type,
                                            'value' => $metadata->value,
                                            'metadata_id' => $metadata->metadata_id,
                                            'created_at' => $metadata->created_at,
                                            'options' => $options
                                        ];
                                    }
                                    return $metadata;
                                });
                                                    
            return response()->json(
                [
                    "MetadataValues" => $metadataWithOptions,
                    "DocumentType" => $document,
                ], 200);

        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage() 
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\DocumentMetadata  $documentMetadata
     * @return \Illuminate\Http\Response
     */
    public function edit(DocumentMetadata $documentMetadata)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\DocumentMetadata  $documentMetadata
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $docMetadataUpdated = [];
        foreach ($request->all() as $data) {
            $docMetadata = DocumentMetadata::where('document_id', $data['id'])
                                            ->where('metadata_id', $data['metadata_id'])
                                            ->first();
                                           
            $docMetadata->value = $data['value'];
            $docMetadata->save();

            array_push($docMetadataUpdated, $docMetadata);
        }

        return response()->json([
            'message' => 'Modifié avec succès',
            'DocumentMetadataUpdated' => $docMetadataUpdated
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\DocumentMetadata  $documentMetadata
     * @return \Illuminate\Http\Response
     */
    public function destroy(DocumentMetadata $documentMetadata)
    {
        //
    }
}

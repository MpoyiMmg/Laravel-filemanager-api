<?php

namespace App\Http\Controllers\Api;

use App\Log;
use App\User;
use DateTime;
use App\LatestDocument;
use App\DocumentMetadata;
use Illuminate\Http\Request;
use App\FileManager\FileManager;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class LogController extends Controller
{
    public $fm;
    public function __construct(FileManager $fm) {
        $this->fm = $fm;
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        if (!$request->user()->can('list-logs')) {
            $data = ['error' => 'Action non autorisée à cet utilisateur'];
            return response()->json($data, 403);
        }
        $log_array = [];    
        $logs = Log::orderBy('id', 'DESC')->filter()->paginate($request->perPage);

        foreach ($logs as $log) {
            $user = User::find($log->user_id);
            $log_obj = [
                'event' => $log->event,
                'description' => $log->description,
                'user' => $user->firstname.' '.$user->lastname,
                'date' => $log->created_at,
                'type' => $log->type
            ];

            array_push($log_array, $log_obj);
        }

        return response()->json(
            [
                'data' => $log_array,
                'totalOfElements' => $logs->total(),
                'currentPage' => $logs->currentPage(),
                'lastPage' => $logs->lastPage()
            ]
        );
    }

    public function CountLogs(Request $request)
    {
        $log_array = [];    

        if ($request->user()->is_admin) {
            $logs = Log::orderBy('id', 'DESC')->take(5)->get();
            foreach ($logs as $log) {
                $user = User::find($log->user_id);
                $log_obj = [
                    'event' => $log->event,
                    'description' => $log->description,
                    'user' => $user->firstname.' '.$user->lastname,
                    'date' => $log->created_at,
                    'type' => $log->type
                ];
                array_push($log_array, $log_obj);
            }
        }
        else {
            $logs = Log::orderBy('id', 'DESC')
                            ->where('user_id', $request->user()->id)
                            ->take(5)->get();
            foreach ($logs as $log) {
                $user = User::find($log->user_id);
                $log_obj = [
                    'event' => $log->event,
                    'description' => $log->description,
                    'user' => $user->firstname.' '.$user->lastname,
                    'date' => $log->created_at,
                    'type' => $log->type
                ];
                array_push($log_array, $log_obj);
            }
        }

        return $log_array;
    }

    public function allLogs() {
        $all = Log::all();
        $logs = $all->groupBy('event');
        return response()->json($logs);
    }

    public function latestDocument() {
        $query = "SELECT documents.*, latest_documents.created_at date, document_types.name as type FROM documents
                            INNER JOIN latest_documents ON latest_documents.document_id = documents.id 
                            INNER JOIN document_types ON document_types.id = documents.document_type_id
                            WHERE latest_documents.user_id = ? ORDER BY latest_documents.created_at DESC";
        $documents = DB::select($query, [Auth::id()]);
        foreach ($documents as $document) {
            $metadatas = DocumentMetadata::where('document_id', $document->id)->get();
            if($this->fm->getDocumentPath($document->id) !== null){
                $path = $this->fm->getDocumentPath($document->id).'/'.$document->name;
            }
            else {
                $path = $document->name;
            }
            $filename = $document->name;
            $documentParts = explode('.', $document->name);
            $extension = end($documentParts);
            $document->basename = $filename;
            $document->document_type = $document->type;
            $document->doc_id = $document->id;
            $document->extension = $extension;
            $document->path = $path;
            $document->metadatas = $metadatas;
            $document->type = 'file';
            $document->type_id = $document->document_type_id;

        }
        return response()->json($documents);
    }
}



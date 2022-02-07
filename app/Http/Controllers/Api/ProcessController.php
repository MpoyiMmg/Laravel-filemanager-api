<?php

namespace App\Http\Controllers\Api;

use App\User;
use stdClass;
use App\State;
use App\Action;
use App\Process;
use App\Metadata;
use App\StateType;
use App\ActionType;
use App\Transition;
use App\Notification;
use App\DocumentState;
use App\DocumentMetadata;
use App\TransitionsAction;
use Illuminate\Http\Request;
use App\Jobs\ProcessNotifyUser;
use App\FileManager\FileManager;
use App\Jobs\ProcessUserMailJob;
use App\Events\NotificationEvent;
use App\Mail\NotificationProcess;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProcessController extends Controller
{
  public $fm;
    public function __construct(FileManager $fm) {
        $this->fm = $fm;
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
      if(!$request->user()->can('list-process')) {
        $data = ['error' => 'Action non autorisée à cet utilisateur'];
        return response()->json($data, 403);
      }
      $processes = Process::orderBy('created_at', 'desc')->get();
      $data = ['processes' => $processes];
    } catch (Exception $e) {
      return response()->json(
        [
          'errors' => $e->getMessage()
        ], 500);
    }
    
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
    $validated = Validator::make($request->all(), [
      'name' => "required|unique:processes"
    ]); 

    if ($validated->fails()) {
      return response()->json(
        [
          'errors' => $validated->errors()
        ], 422);
    }

    else {
      try {
        $process = new Process;
        $process->name = $request->name;
        $process->save();
        $data = ['processCreated' => $process];
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
   * @param  \App\Process  $process
   * @return \Illuminate\Http\Response
   */
  public function show(Request $request)
  {
    return Process::find($request->id);
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \App\Process  $process
   * @return \Illuminate\Http\Response
   */
  public function update(Request $request, Process $process)
  {
    $validated = Validator::make($request->all(), [
      'name' => 'required'
    ]);

    if ($validated->fails()) {
      return response()->json(
        [
          'errors' => $validated->errors()
        ], 422);
    }
    else {
      try {
        $process = $process->find($request->id);
  
        if ($process !== null) {
          $process->name = $request->name;
          $process->save();
        }
        else {
          return response()->json(
            [
              'message' => 'Le process renseigné est inéxistant'
            ], 404);
        }
      } catch (Exception $e) {
        return response()->json(
          [
            'errors' => $e->getMessage()
          ], 500);
      }
    }

    $data = ['processUpdated' => $process];
    return response()->json($data, 200);
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  \App\Process  $process
   * @return \Illuminate\Http\Response
   */
  public function destroy(Request $request, Process $process)
  {
    try {
      $process = $process->find($request->id);
      $states = State::where('process_id', $process->id)->get();
      foreach ($states as $state) {
        $documentState = DocumentState::where('state_id', $state->id)->get();
        if (count($documentState) > 0) {
          return response()->json(
            ['error' => 'Ce process est lié à un document'],
            500
          );
        }
      }
      $process->delete();
    } catch (Exception $e) {
      return response()->json(
        [
          'errors' => $e->getMessage()
        ], 500);
    }
    return response()->json(['message' => 'deleted'], 200);
  }

  public function checkDocument(Request $request, $id) {
    $check = DocumentState::where('document_id', $id)->first();
    if ($check) {
      return response()->json([
        'check' => true
      ]);
    }
    return response()->json([
      'check' => false
    ]);
  }

  public function getDocumentStateActions(Request $request, $id) {
    $query = "SELECT actions.id, actions.name
              FROM transitions, actions
              WHERE transitions.action_id = actions.id
              AND  transitions.previousState = (SELECT document_states.state_id id
                                                FROM document_states, documents
                                                WHERE document_states.document_id = documents.id
                                                AND document_states.document_id = ?
                                                AND document_states.date_out IS NULL)";
   $data = DB::select($query, [$id]);
   return $data;
  }

  private function getStateTypeByName($name) {
    return StateType::where('name', $name)->first();
  }

  private function getStateType($type) {
    switch ($type) {
      case 'approve':
        return $this->getStateTypeByName('Approved');
      case 'reject':
        return $this->getStateTypeByName('Rejected');
      case 'cancel':
        return $this->getStateTypeByName('Cancelled');
    }
  }

  private function getNextStateFromCondition() {

  }

  public function initiateProcess(Request $request) {
    $initialState = State::where('process_id', $request->process)
                          ->where('name', 'Start')
                          ->first();
    if(\is_null($initialState)) {
      return \response()->json([
        'warning' => true
      ], 422);
    }
    $startType = StateType::where('name', 'Start')->first()->id;
    $waintingType = StateType::where('name', 'Waiting')->first()->id;
    $transition = Transition::where('previousState', $initialState->id)
                            ->first();
    $nextState = State::where('id', $transition->nextState)->first();    
        
    $initialDocumentState = DocumentState::create([
      'document_id' => $request->documentId,
      'state_id' => $initialState->id,
      'comment' => ' ',
      'date_in' => now(),
      'date_out' => now(),
      'state_type_id' => $startType,
      'user_id' => Auth::user()->id
    ]);

    if ($nextState->type === 'condition') {
      $metadataType = Metadata::find($nextState->metadata_id)->type;
      $docMetadata = DocumentMetadata::where('document_id', $request->documentId)
                                ->where('metadata_id', $nextState->metadata_id)
                                ->first();
      if (!$docMetadata) {
        return response()->json(["context" => "unindexed"], 400);
      }
      $value = $docMetadata->value;
      $lastValue = $metadataType == 'number' ? (int) $value : $value;
      $metadataValue = $metadataType == 'number' ? (int) $nextState->metadataValue : $nextState->metadataValue;
      $transitionId = null;
      switch ($nextState->operator) {
        case '==':
          $transitionId = $lastValue == $metadataValue ?  $nextState->transition_if_true : $nextState->transition_if_false;
          break;
        case '!=':
          $transitionId = $lastValue != $metadataValue ?  $nextState->transition_if_true : $nextState->transition_if_false;
          break;
        case '>':        
            $transitionId = $lastValue > $metadataValue ?  $nextState->transition_if_true : $nextState->transition_if_false;
          break;
        case '<':
          $transitionId = $lastValue < $metadataValue ?  $nextState->transition_if_true : $nextState->transition_if_false;
          break;
        case '>=':
          $transitionId = $lastValue >= $metadataValue ?  $nextState->transition_if_true : $nextState->transition_if_false;
          break;
        case '<=':
          $transitionId = $lastValue >= $metadataValue ?  $nextState->transition_if_true : $nextState->transition_if_false;
          break;
      }
      $FinalNextState = State::findOrFail(Transition::findOrFail($transitionId)->nextState);
    } else {
      $FinalNextState = $nextState;
    }
    
    $pendingState = DocumentState::create([
      'document_id' => $request->documentId,
      'state_id' => $FinalNextState->id,
      'comment' => ' ',
      'date_in' => now(),
      'date_out' => null,
      'state_type_id' => $waintingType,
      'user_id' => $FinalNextState->user_id
    ]);

    $user = User::findOrFail($FinalNextState->user_id);

    $notification = new Notification;
    $notification->title = "PROCESS";
    $notification->user_id = $user->id;
    $notification->doc_id = $request->documentId;
    $notification->content = "Vous a mentionné dans un commentaire";
    $notification->sender_id = Auth::id();
    $notification->save();

    $event = new NotificationEvent($notification);
    broadcast($event)->toOthers();
    
    ProcessNotifyUser::dispatch($user);

    return $pendingState;
  }

  public function changeDocumentState(Request $request) {
    // TO DO : INCLUDE ATTACHEMENTS
    // TO DO : INCLUDE GROUPS OF APPROVERS
    $action = Action::where('id', $request->actionId)->first();
    $type = ActionType::where('id', $action->actionType_id)->first();
    $stateType = $this->getStateType($type->name);        
    $waintingType = $this->getStateTypeByName('Waiting');
    $endType = $this->getStateTypeByName('End');

    $StateIdQuery =  "SELECT document_states.state_id id
                      FROM document_states, documents
                      WHERE document_states.document_id = documents.id
                      AND document_states.document_id = ?
                      AND document_states.date_out IS NULL";
    
    $StateId = DB::select($StateIdQuery, [$request->documentId])[0]->id;
    
    $docState = DocumentState::where('state_id', $StateId)
                                    ->whereNull('date_out')
                                    ->first();
    
    $nextStateId = Transition::where('previousState', $docState->state_id)
                              ->where('action_id', $request->actionId)
                              ->first()->nextState;
    // CHECK IF NEXT STATE IS CONDITIONAL TEST THE CONDITION AND GET THE APPROPRIATE NEXT STATE
    // CHECK IF NEXT STATE IS START AND GET INITIATOR

    $nextState = State::where('id', $nextStateId)->first();    

    if ($nextState->type == 'start') {
      // get the initiator of the process
      // close current state
      $docState->comment = $request->comment;
      $docState->state_type_id = $stateType->id;
      $docState->date_out = now();
      $docState->save();
      $initiatorId = DocumentState::where('state_id', $nextState->id)->first()->user_id;
      $newDocumentState = DocumentState::create([
        'document_id' => $request->documentId,
        'state_id' => $nextState->id,
        'comment' => ' ',
        'date_in' => now(),
        'date_out' => null,
        'state_type_id' => $waintingType->id,
        'user_id' => $initiatorId
      ]);
    } elseif ($nextState->type == 'end') {
      // close current state
      $docState->comment = $request->comment;
      $docState->state_type_id = $stateType->id;
      $docState->date_out = now();
      $docState->save();
      $newDocumentState = DocumentState::create([
        'document_id' => $request->documentId,
        'state_id' => $nextState->id,
        'comment' => ' ',
        'date_in' => now(),
        'date_out' => now(),
        'state_type_id' => $endType->id,
        'user_id' => Auth::user()->id
      ]);
    } elseif ($nextState->type == 'condition') {
      $metadataType = Metadata::find($nextState->metadata_id)->type;
      $docMetadata = DocumentMetadata::where('document_id', $request->documentId)
                                ->where('metadata_id', $nextState->metadata_id)
                                ->first();
      if (!$docMetadata) {
        return response()->json(["context" => "unindexed"], 400);
      }
      $value = $docMetadata->value;
      $lastValue = $metadataType == 'number' ? (int) $value : $value;
      $metadataValue = $metadataType == 'number' ? (int) $nextState->metadataValue : $nextState->metadataValue;
      $transitionId = null;
      $docState->comment = $request->comment;
      $docState->state_type_id = $stateType->id;
      $docState->date_out = now();
      $docState->save();
      switch ($nextState->operator) {
        case '==':
          $transitionId = $lastValue == $metadataValue ?  $nextState->transition_if_true : $nextState->transition_if_false;
          break;
        case '!=':
          $transitionId = $lastValue != $metadataValue ?  $nextState->transition_if_true : $nextState->transition_if_false;
          break;
        case '>':        
            $transitionId = $lastValue > $metadataValue ?  $nextState->transition_if_true : $nextState->transition_if_false;
          break;
        case '<':
          $transitionId = $lastValue < $metadataValue ?  $nextState->transition_if_true : $nextState->transition_if_false;
          break;
        case '>=':
          $transitionId = $lastValue >= $metadataValue ?  $nextState->transition_if_true : $nextState->transition_if_false;
          break;
        case '<=':
          $transitionId = $lastValue >= $metadataValue ?  $nextState->transition_if_true : $nextState->transition_if_false;
          break;
      }
      $FinalNextState = State::findOrFail(Transition::findOrFail($transitionId)->nextState);
      $newDocumentState = DocumentState::create([
        'document_id' => $request->documentId,
        'state_id' => $FinalNextState->id,
        'comment' => ' ',
        'date_in' => now(),
        'date_out' => null,
        'state_type_id' => $waintingType->id,
        'user_id' => $FinalNextState->user_id
      ]);
    } else {
      // close current state
      $docState->comment = $request->comment;
      $docState->state_type_id = $stateType->id;
      $docState->date_out = now();
      $docState->save();
      $newDocumentState = DocumentState::create([
        'document_id' => $request->documentId,
        'state_id' => $nextState->id,
        'comment' => ' ',
        'date_in' => now(),
        'date_out' => null,
        'state_type_id' => $waintingType->id,
        'user_id' => $nextState->user_id
      ]);
    }
    return $newDocumentState;
  }

  public function getDocumentStates(Request $request, $id) {
    $statesQuery = "SELECT documents.*, document_states.comment, document_states.date_in,
                    document_states.date_out, users.firstname, users.lastname,
                    IF(document_states.date_out IS NULL AND document_states.user_id = ?, true, false) can_approve,
                    states.name, users.picture_small, state_types.name state_type
                    FROM documents, document_states, users, states, state_types
                    WHERE document_states.document_id = documents.id 
                    AND document_states.user_id = users.id
                    AND document_states.state_type_id = state_types.id
                    AND documents.id = ?
                    AND document_states.state_id = states.id
                    ORDER BY document_states.date_in";
    $states = DB::select($statesQuery, [Auth::user()->id,$id]);
    
    $metadata = DB::table('document_metadata')
                  ->join('documents', 'document_metadata.document_id','documents.id')
                  ->join('metadata', 'document_metadata.metadata_id', 'metadata.id')
                  ->select(
                      "documents.id", "document_metadata.metadata_id", 
                      "metadata.label", "document_metadata.value", 
                      "metadata.type", "metadata.created_at")
                  ->where("documents.id", $id)
                  ->orderBy("document_metadata.created_at", "DESC")
                  ->get();
    return response()->json([
      'states' => $states,
      'metadata' => $metadata
    ]);
  }

  public function getUserTasks(Request $request) {
    $query = "SELECT documents.id,documents.name, states.id as state_id, document_states.id docStateId,
              document_types.name as type, document_states.id as document_state, states.name as state
              FROM documents, document_states, document_types, states, folders
              WHERE document_types.id = documents.document_type_id
              AND folders.id = documents.folder_id
              AND document_states.document_id = documents.id
              AND document_states.state_id = states.id
              AND document_states.user_id = ?
              AND document_states.date_out IS NULL
              ORDER BY document_states.created_at DESC";
    $data = DB::select($query, [Auth::user()->id]);
    if(!empty($data)) {
      foreach($data as $d) {
        if($this->fm->getDocumentPath($d->id) !== null){
            $path = $this->fm->getDocumentPath($d->id).'/'.$d->name;
        }
        else {
            $path = $d->name;
        }
        $documentParts = explode('.', $d->name);
        $extension = end($documentParts);
        $d->basename = $d->name;
        $d->doc_id = $d->id;
        $d->path = $path;
        $d->type = 'file';
        $d->extension = $extension;
      }
    }
    return $data;
  }

  public function storeProcess(Request $request) {
    // TODO: Wrap the code around a try catch block & database transactions
    // return $request;
    try {
      foreach ($request->nodes as $state) {
        // CHECK STATE TYPE (OPERATION | CONDITION)
        // CHECK CONDITION TRANSITION
        if ($state['type'] === 'condition') {
          State::create([
            'name' => $state['name'],
            'process_id' => $request->id,
            'user_id' => null,
            'xCoordinate' => $state['x'],
            'yCoordinate' => $state['y'],
            'type' => $state['type'],
            'reference' => $state['id'],
            'metadata_id' => $state['metadata'],
            'operator' => $state['operator'],
            'metadataValue' => $state['value']
          ]);
        } else {
          State::create([
            'name' => $state['name'],
            'process_id' => $request->id,
            'user_id' => count($state['approvers']) > 0 ? $state['approvers'][0]['id'] : null,
            'xCoordinate' => $state['x'],
            'yCoordinate' => $state['y'],
            'type' => $state['type'],
            'reference' => $state['id'],
          ]);
        }
      }
  
      foreach ($request->connections as $transition) {
  
        $previousState = State::where('reference', $transition['source']['id'])->first();
        $nextState = State::where('reference', $transition['destination']['id'])->first();      
  
        $createdTransition = Transition::create([
          'process_id' => $request->id,
          'previousState' => $previousState->id,
          'nextState' => $nextState->id,
          'reference' => $transition['id'],
          'action_id' => $transition['action'] ?? null,
          'sourcePosition' => $transition['source']['position'],
          'destinationPosition' => $transition['destination']['position'],
          'source_ref' => $transition['source']['id'],
          'destination_ref' => $transition['destination']['id'],
        ]);
  
        // CHECK IF PREVIOUS STATE IS CONDITIONAL AND UPDATE
        // 1) SET THE PREVIOUS STATE TRANSITION_IF_TRUE 
        // 2) SET THE PREVIOUS STATE TRANSITION_IF_FALSE
  
        if ($previousState->type === 'condition') {
          if ($transition['source']['position'] === 'top' || $transition['source']['position'] === 'right') {
            $previousState->transition_if_true = $createdTransition->id;
            $previousState->save();
          }
          if ($transition['source']['position'] === 'bottom') {
            $previousState->transition_if_false = $createdTransition->id;
            $previousState->save();
          }
        }
      }
      return ['success', $request];
    } catch (\Throwable $e) {
      return $e->getMessage();
    }    
  }

  public function showProcess(Request $request, $id) {
    // TODO: Algorithm that returns the workflow structure
    // return Process::find($id);
    $nodes = [];
    $connections = [];
    $states = State::where('process_id', $id)->get();
    $transitions = Transition::where('process_id', $id)->get();
    if ($states && $transitions) {
      foreach ($states as $state) {
        $user = User::where('id', $state->user_id)->first();
        $width = $state->type == 'start' || $state->type == 'end' ? 120 : 120; 
        $height = $state->type == 'start' || $state->type == 'end' ? 60 : 120; 
        if ($user) {
          $node = array(
            "id" => $state->reference,
            "approvers" => array(array(
              "id" => $user->id,
              "name" => $user->firstname,
              "url" => $user->picture_medium
            )),
            "width" => $width,
            "height" => $height,
            "name" => $state->name,
            "type" => $state->type,
            "x" => (int) $state->xCoordinate,
            "y" => (int) $state->yCoordinate
          );
        } else {
          $node = array(
            "id" => $state->reference,
            "approvers" => [],
            "width" => $width,
            "height" => $height,
            "name" => $state->name,
            "type" => $state->type,
            "x" => (int) $state->xCoordinate,
            "y" => (int) $state->yCoordinate,
            "metadata" => $state->metadata_id,
            "operator" => $state->operator,
            "value" => $state->metadataValue
          );
        }
        array_push($nodes, $node);
      }
      foreach ($transitions as $transition) {        
        $type = '';
        if ($transition->action_id !== null) {
          $action = Action::where('id', $transition->action_id)->first();
          $actionType = ActionType::where('id', $action->actionType_id)->first();
          $type = $actionType->name === 'approve' || $action === null ? 'pass' : 'reject';
        }
        $connection = array(
          "id" => $transition->reference,
          "action" => $transition->action_id,
          "name" => "Pass",
          "type" => $type,
          "source" => array(
            "id" => $transition->source_ref,
            "position" => $transition->sourcePosition
          ),
          "destination" => array(
            "id" => $transition->destination_ref,
            "position" => $transition->destinationPosition
          ),
        );
        array_push($connections, $connection);
      }
    }

    return array(
      "nodes" => $nodes,
      "connections" => $connections
    );    
  }

  public function updateProcess(Request $request) {
    //TODO: Algorithm to update a process
  }
}

<?php

namespace App\Http\Controllers\Api;

use App\User;
use App\Notification;
use App\Mail\MentionMail;
use Illuminate\Http\Request;
use Mockery\Matcher\NotAnyOf;
use App\Events\NotificationEvent;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class NotificationsController extends Controller
{
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
        $notificationsQuery = "SELECT notifications.*, users.picture_small FROM notifications 
                                LEFT JOIN users ON notifications.sender_id = users.id
                                WHERE notifications.user_id = ?
                                ORDER BY notifications.created_at DESC";
        $notifications = DB::select($notificationsQuery, [Auth::id()]);
        return response()->json($notifications);                             
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
        $validator = Validator::make($request->all(), [
            'title' => 'required|max:255',
            'content' => 'required|max:255'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $notification = new Notification;
        $notification->title = $request->title;
        $notification->user_id = $request->user_id;
        $notification->doc_id = $request->doc_id;
        $notification->content = $request->content;
        $notification->sender_id = Auth::id();
        $notification->save();

        $event = new NotificationEvent($notification);
        broadcast($event)->toOthers();
        $mentionedUsers = $request->mentions;
        if($mentionedUsers) {
            foreach ($mentionedUsers as $user) {
                $this->notifyUser($user, $notification->id);
            }
        }
        return response()->json([$notification], 201);
    }

    public function notifyUser($user, $notif_id) {
        Mail::to($user["email"])->send(new MentionMail($notif_id));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Notification  $notification
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $notification_id = $request->id;
        $notificationsQuery = "SELECT notifications.*, users.picture_medium FROM notifications 
                                LEFT JOIN users ON notifications.sender_id = users.id
                                WHERE notifications.id = ?";
        $notification = DB::select($notificationsQuery, [$notification_id]);
        return response()->json($notification[0]); 
        
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Notification  $notification
     * @return \Illuminate\Http\Response
     */
    public function edit(Notification $notification)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Notification  $notification
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Notification $notification)
    {   
        $notification_id = $request->id;

        $notification = Notification::findOrFail($notification_id);
        if ($notification->status == false) {
            $notification->status = true;
        }
        $notification->save();
        return response()->json([
            'data' => $notification->status
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Notification  $notification
     * @return \Illuminate\Http\Response
     */
    public function destroy(Notification $notification)
    {
        //
    }
}

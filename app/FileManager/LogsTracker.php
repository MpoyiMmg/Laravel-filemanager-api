<?php

namespace App\FileManager;

use App\Log;
use Illuminate\Support\Facades\Auth;

class LogsTracker
{

    public function logsTracker($logData) {

        Log::create([
            "event" => $logData['event'],
            "description" => $logData['description'],
            "user_id" => $logData['user_id'],
            'type' => $logData['type']
        ]);
    }

    public function connect($user_id) {

        $data = [
            "event" => "login",
            "description" => "-",
            "user_id" => $user_id,
            'type' => "sys"
        ];

        $this->logsTracker($data);
    }

    public function disconnect($user_id) {

        $data = [
            "event" => "logout",
            "description" => "-",
            "user_id" => $user_id,
            'type' => "sys"
        ];

        $this->logsTracker($data);
    }

    public function createFolder($path) {
        $data = [
            'event' => "create",
            'description' => $path,
            'user_id' => Auth::id(),
            'type' => "dir"
        ];

        $this->logsTracker($data);
    }

    public function createDocument($path) {
        $data = [
            'event' => "create",
            'description' => $path,
            'user_id' => Auth::id(),
            'type' => "file"
        ];

        $this->logsTracker($data);
    }

    public function officeEditSave($filename) {
        $data = [
            'event' => "officeEditSave",
            'description' => $filename,
            'user_id' => Auth::id(),
            'type' => "file"
        ];

        $this->logsTracker($data);
    }

    public function renameFolder($oldPath, $newPath) {

        $data = [
            "event" => "rename",
            "description" => $oldPath." => ".$newPath,
            "user_id" => Auth::id(),
            'type' => "dir"
        ];

        $this->logsTracker($data);
        
    }

    public function renameDocument($oldName, $newName) {

        $data = [
            "event" => "rename",
            "description" => $oldName." => ".$newName,
            "user_id" => Auth::id(),
            'type' => "file"
        ];

        $this->logsTracker($data);
        
    }

    public function copyFolder($path) {
        $data = [
            "event" => "copy",
            "description" => $path,
            "user_id" => Auth::id(),
            'type' => "file"
        ];

        $this->logsTracker($data);
    }

    public function copyDocument($path) {
        $data = [
            "event" => "copy",
            "description" => $path,
            "user_id" => Auth::id(),
            'type' => "file"
        ];

        $this->logsTracker($data);
    }

    public function moveFolder() {

        $data = [
            "event" => "move",
            "description" => "DOSSIER",
            "user_id" => Auth::id(),
            'type' => "dir"
        ];

        $this->logsTracker($data); 

    }

    public function moveDocument() {

        $data = [
            "event" => "move",
            "description" => "DOCUMENT",
            "user_id" => Auth::id(),
            'type' => "file"
        ];

        $this->logsTracker($data); 

    }

    public function deleteFolder($path) {
        $data = [
            "event" => "delete",
            "description" => $path,
            "user_id" => Auth::id(),
            'type' => "dir"
        ];

        $this->logsTracker($data);
    }

    public function modify($document) {
        $data = [
            "event" => "modify",
            "description" => $document,
            "user_id" => Auth::id(),
            'type' => "file"
        ];

        $this->logsTracker($data);
    }

    public function deleteDocument($path) {
        $data = [
            "event" => "delete",
            "description" => $path,
            "user_id" => Auth::id(),
            'type' => "file"
        ];

        $this->logsTracker($data);
    }

    public function uploadDocument($path, $filename) {
        $data = [
            'event' => "upload",
            'description' => $path."/".$filename,
            'user_id' => Auth::id(),
            'type' => "file"
        ];

        $this->logsTracker($data);
    }
}
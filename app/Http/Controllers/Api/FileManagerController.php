<?php

namespace App\Http\Controllers\Api;

use App\User;
use DateTime;
use App\Document;
use App\Notification;
use Illuminate\Http\Request;
use App\FileManager\FileManager;
use App\FileManager\Events\Paste;
use App\FileManager\Services\Zip;
use App\FileManager\Events\Rename;
use App\FileManager\Events\Deleting;
use App\FileManager\Events\Download;
use App\Http\Controllers\Controller;
use function GuzzleHttp\Promise\all;
use Illuminate\Support\Facades\Auth;
use App\FileManager\Events\FileUpdate;
use App\FileManager\Events\FileCreated;
use App\FileManager\Events\DiskSelected;
use App\FileManager\Events\FileCreating;
use App\FileManager\Events\FilesUploaded;
use App\FileManager\Events\FilesUploading;

use App\FileManager\Events\DirectoryCreated;
use App\FileManager\Events\DirectoryCreating;
use App\FileManager\Requests\RequestValidator;
use App\FileManager\Events\BeforeInitialization;

class FileManagerController extends Controller
{
    /**
     * @var FileManager
     */
    public $fm;

    /**
     * FileManagerController constructor.
     *
     * @param FileManager $fm
     */
    public function __construct(FileManager $fm)
    {
        $this->fm = $fm;
    }

    /**
     * Initialize file manager
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function initialize()
    {
        event(new BeforeInitialization());

        return response()->json(
            $this->fm->initialize()
        );
    }

    /**
     * Get files and directories for the selected path and disk
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function content(RequestValidator $request)
    {
        return response()->json(
            $this->fm->content(
                $request->input('disk'),
                $request->input('path')
            )
        );
    }

    /**
     * Search for files and directories in the selected disk
     * 
     * @param Request $request
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        if ($request->searchPayload["type"] === 'simple') {
            return response()->json(
                $this->fm->simpleSearch(
                    $disk = 'public',
                    $path = '/',
                    $request->searchPayload
                )
            );
        }

        if ($request->searchPayload["type"] === 'advanced') {
            return response()->json(
                $this->fm->advancedSearch(
                    $disk = 'public',
                    $path = '/',
                    $request->searchPayload
                )
            );
        }

        if ($request->searchPayload["type"] === 'content') {
            return $this->fm->searchByContent(
                $request->searchPayload["filter"],
                $disk = 'public',
                $path = '/'
            );
        }
    }

    public function countDocuments(Request $request)
    {
        if ($request->user()->is_admin) {
            $documents = Document::all();
        } else {
            $documents = Document::where('user_id', $request->user()->id);
        }

        $alldocumentsWithCount = [
            'documents' => $documents,
            'documents_count' => $documents->count()
        ];

        return $alldocumentsWithCount;
    }

    public function Listdocuments(Request $request)
    {
        $listdocuments_array = [];
        $documents = Document::orderBy('id', 'DESC')->take(5)->get();

        foreach ($documents as $document) {
            $user = User::find($document->user_id);
            $date = new DateTime($document->created_at);
            $listdocuments_obj = [
                'Nom' => $document->name,
                'Utilisateur' => $user->firstname . ' ' . $user->lastname,
                'Date' => $date->format('d-m-Y  H:i:s')
            ];
            array_push($listdocuments_array, $listdocuments_obj);
        }

        return $listdocuments_array;
    }

    public function getDocumentById(Request $request)
    {
        return response()->json(
            $this->fm->getDocumentById(
                $request->id
            )
        );
    }

    public function getDocumentsSize()
    {
        return response()->json(
            $this->fm->getDocumentsSize(
                $disk = 'public',
                $path = '/'
            )
        );
    }

    /**
     * Directory tree
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function tree(RequestValidator $request)
    {
        return response()->json(
            $this->fm->tree(
                $request->input('disk'),
                $request->input('path')
            )
        );
    }

    /**
     * Check the selected disk
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function selectDisk(RequestValidator $request)
    {
        event(new DiskSelected($request->input('disk')));

        return response()->json([
            'result' => [
                'status'  => 'success',
                'message' => 'diskSelected'
            ],
        ]);
    }


    /**
     * get old versions of documents
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function oldVersions(Request $request)
    {
        $old_versions = $this->fm->oldVersions(
            $request->id
        );

        return \response()->json($old_versions);
    }
    /**
     * modify files
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function modify(RequestValidator $request)
    {
        event(new FilesUploading($request));

        $uploadResponse = $this->fm->modify(
            $request->input('disk'),
            $request->input('path'),
            $request->file('files'),
            $request->input('overwrite'),
            $request->input('comment'),
            $request->document_id,
            null
        );

        // event(new FilesUploaded($request));

        return response()->json($uploadResponse);
    }

    public function modifyDocumentType(RequestValidator $request)
    {
        $updateDocumentType = $this->fm->modifyDocumentType(
            $request->input('document_id'),
            $request->input('docType_id')
        );
        return response()->json($updateDocumentType);
    }

    /**
     * Upload files
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(RequestValidator $request)
    {
        // return $request->all();
        $documentType = (array)($request->documentTypes);
        if (gettype($request->documentTypes) == "string") {
            $documentType = json_decode($request->documentTypes, true);
        }

        event(new FilesUploading($request));

        $uploadResponse = $this->fm->upload(
            $request->input('disk'),
            $request->input('path'),
            $request->file('files'),
            $request->input('overwrite'),
            $documentType
        );

        event(new FilesUploaded($request));

        return response()->json($uploadResponse);
    }

    /**
     * Delete files and folders
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(RequestValidator $request)
    {
        event(new Deleting($request));

        $deleteResponse = $this->fm->delete(
            $request->input('disk'),
            $request->input('items')
        );

        return response()->json($deleteResponse);
    }

    /**
     * Copy / Cut files and folders
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function paste(RequestValidator $request)
    {
        event(new Paste($request));

        return response()->json(
            $this->fm->paste(
                $request->input('disk'),
                $request->input('path'),
                $request->input('clipboard')
            )
        );
    }

    /**
     * Rename
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function rename(RequestValidator $request)
    {
        event(new Rename($request));

        return response()->json(
            $this->fm->rename(
                $request->input('disk'),
                $request->input('newName'),
                $request->input('oldName')
            )
        );
    }


    /**
     * Download file
     *
     * @param RequestValidator $request
     *
     * @return mixed
     */
    public function download(RequestValidator $request)
    {
        //return ["here" => "To the hero"];
        event(new Download($request));

        return $this->fm->download(
            $request->input('disk'),
            $request->input('path')
        );
    }

    public function previewFile(RequestValidator $request)
    {
        event(new Download($request));

        return $this->fm->previewFile(
            $request->input('user_id'),
            $request->input('disk'),
            $request->input('path')
        );
    }

    /**
     * Download file
     *
     * @param RequestValidator $request
     *
     * @return mixed
     */
    public function downloadOldVersion(Request $request)
    {
        event(new Download($request));

        return $this->fm->downloadOldVersion(
            $request->disk,
            $request->path
        );
    }

    public function deleteVersion(Request $request)
    {
        return $this->fm->deleteVersion($request->id);
    }

    /**
     * Download file
     *
     * @param RequestValidator $request
     *
     * @return mixed
     */
    public function officeEdit($path)
    {
        // $_SERVER['HTTP_USER_AGENT'];

        //TODO: Restrict User Agent to Microsoft Office
        $pathParts = explode("/", $path);
        $filename = array_pop($pathParts);
        $userId = array_shift($pathParts);
        return $this->fm->officeEdit($userId, $path);
    }

    /**
     * Save file from Office Client
     *
     * @param RequestValidator $request
     *
     * @return mixed
     */
    public function officeEditSave($request, $path)
    {
        $path;
        $dirPath = "/";
        $pathParts = explode("/", $path);
        $filename = array_pop($pathParts);
        $userId = array_shift($pathParts);
        $level = count($pathParts) + 1;
        $document = Document::where('name', $filename)
            ->where('level', $level)
            ->first();
        $documentId = $document->id;
        if (count($pathParts) > 0) {
            $dirPath = implode("/", $pathParts);
        }

        return $this->fm->modify(
            'public',
            $dirPath,
            array($request->file),
            1,
            $request->comment, // TO DO : Implement Comment from the plugin
            $documentId,
            $userId
        );
    }

    /**
     * Create thumbnails
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\Response|mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function thumbnails(RequestValidator $request)
    {
        return $this->fm->thumbnails(
            $request->input('disk'),
            $request->input('path')
        );
    }

    /**
     * Image preview
     *
     * @param RequestValidator $request
     *
     * @return mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function preview(RequestValidator $request)
    {
        return $this->fm->preview(
            $request->input('disk'),
            $request->input('path')
        );
    }

    /**
     * File url
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function url(RequestValidator $request)
    {
        return response()->json(
            $this->fm->url(
                $request->input('disk'),
                $request->input('path')
            )
        );
    }

    /**
     * Create new directory
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createDirectory(RequestValidator $request)
    {
        event(new DirectoryCreating($request));

        $createDirectoryResponse = $this->fm->createDirectory(
            $request->input('disk'),
            $request->input('path'),
            $request->input('name')
        );

        if ($createDirectoryResponse['result']['status'] === 'success') {
            event(new DirectoryCreated($request));
        }

        return response()->json($createDirectoryResponse);
    }

    /**
     * Create new file
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createFile(RequestValidator $request)
    {
        event(new FileCreating($request));

        $createFileResponse = $this->fm->createFile(
            $request->input('disk'),
            $request->input('path'),
            $request->input('name'),
            $request->input('type'),
            $request->input('officeType')
        );


        // if ($createFileResponse['result']['status'] === 'success') {
        //     event(new FileCreated($request));
        // }

        return response()->json($createFileResponse);
    }

    /**
     * Update file
     *
     * @param RequestValidator $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateFile(RequestValidator $request)
    {
        event(new FileUpdate($request));

        return response()->json(
            $this->fm->updateFile(
                $request->input('disk'),
                $request->input('path'),
                $request->file('file')
            )
        );
    }

    /**
     * Stream file
     *
     * @param RequestValidator $request
     *
     * @return mixed
     */
    public function streamFile(RequestValidator $request)
    {
        return $this->fm->streamFile(
            $request->input('disk'),
            $request->input('path')
        );
    }

    /**
     * Create zip archive
     *
     * @param RequestValidator $request
     * @param Zip              $zip
     *
     * @return array
     */
    public function zip(RequestValidator $request, Zip $zip)
    {
        return $zip->create();
    }

    /**
     * Extract zip atchive
     *
     * @param RequestValidator $request
     * @param Zip              $zip
     *
     * @return array
     */
    public function unzip(RequestValidator $request, Zip $zip)
    {
        return $zip->extract();
    }

    /**
     * Integration with ckeditor 4
     *
     * @param Request $request
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function ckeditor()
    {
        return view('file-manager::ckeditor');
    }

    /**
     * Integration with TinyMCE v4
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function tinymce()
    {
        return view('file-manager::tinymce');
    }

    /**
     * Integration with TinyMCE v5
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function tinymce5()
    {
        return view('file-manager::tinymce5');
    }

    /**
     * Integration with SummerNote
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function summernote()
    {
        return view('file-manager::summernote');
    }

    /**
     * Simple integration with input field
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function fmButton()
    {
        return view('file-manager::fmButton');
    }

    public function documentPath(Request $request)
    {
        return $this->fm->getDocumentPath(
            $request->id
        );
    }
}

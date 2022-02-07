<?php

namespace App\FileManager;

use File;
use Image;
use App\Log;
use App\User;
use Response;
use App\Binder;
use App\Folder;
use App\Comment;
use App\Convert;
use App\Safebox;
use App\Document;
use App\DocumentState;
use App\LatestDocument;
use App\DocumentVersion;
use App\DocumentMetadata;
use App\OfficeConversion;
use Spatie\PdfToText\Pdf;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\FileManager\LogsTracker;
use Illuminate\Support\Facades\DB;
use App\FileManager\Events\Deleted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\FileManager\Traits\PathTrait;
use App\FileManager\Traits\CheckTrait;
use Illuminate\Support\Facades\Storage;
use App\FileManager\Traits\ContentTrait;
use \PhpOffice\PhpWord\PhpWord as PhpWord;
use NcJoes\OfficeConverter\OfficeConverter;
use \PhpOffice\PhpWord\IOFactory as WordFactory;
use \PhpOffice\PhpWord\Settings as WordSettings;
use \PhpOffice\PhpSpreadsheet\Spreadsheet as Spreadsheet;
use \PhpOffice\PhpPresentation\Shape\RichText as RichText;
use App\FileManager\Services\TransferService\TransferFactory;
use \PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetFactory;
use \PhpOffice\PhpPresentation\IOFactory as PresentationFactory;
use \PhpOffice\PhpPresentation\PhpPresentation as PhpPresentation;
use App\FileManager\Services\ConfigService\DefaultConfigRepository;

class FileManager
{
  use PathTrait, ContentTrait, CheckTrait;

  /**
   * @var ConfigRepository
   */
  public $configRepository;
  private $searchResult = [
    'directories' => [],
    'files'       => []
  ];
  private $totalSize = 0;

  public $logsTracker;

  /**
   * FileManager constructor.
   *
   * @param  ConfigRepository  $configRepository
   */
  public function __construct(DefaultConfigRepository $configRepository)
  {
    $this->configRepository = $configRepository;

    $this->logsTracker = new LogsTracker();
  }

  /**
   * Initialize App
   *
   * @return array
   */
  public function initialize()
  {
    // if config not found
    if (!config()->has('file-manager')) {
      return [
        'result' => [
          'status'  => 'danger',
          'message' => 'noConfig'
        ],
      ];
    }


    $config = [
      'acl'           => $this->configRepository->getAcl(),
      'leftDisk'      => $this->configRepository->getLeftDisk(),
      'rightDisk'     => $this->configRepository->getRightDisk(),
      'leftPath'      => $this->configRepository->getLeftPath(),
      'rightPath'     => $this->configRepository->getRightPath(),
      'windowsConfig' => $this->configRepository->getWindowsConfig(),
      'hiddenFiles'   => $this->configRepository->getHiddenFiles(),
    ];

    // disk list
    foreach ($this->configRepository->getDiskList() as $disk) {
      if (array_key_exists($disk, config('filesystems.disks'))) {
        $config['disks'][$disk] = Arr::only(
          config('filesystems.disks')[$disk],
          ['driver']
        );
      }
    }

    // get language
    $config['lang'] = app()->getLocale();

    return [
      'result' => [
        'status'  => 'success',
        'message' => null,
      ],
      'config' => $config,
    ];
  }

  /**
   * Get files and directories for the selected path and disk
   *
   * @param $disk
   * @param $path
   *
   * @return array
   */
  public function content($disk, $path)
  {
    // get content for the selected directory
    $content = $this->getContent($disk, $path);

    return [
      'result'      => [
        'status'  => 'success',
        'message' => null,
      ],
      'directories' => $content['directories'],
      'files'       => $content['files'],
    ];
  }


  /**
   * simple Search for files in the selected disk and path recursively
   * Author: Kevin OLENGA
   * 
   * @param $disk
   * @param $path - default '/'
   * @param Request $payload
   * 
   * @return array
   */
  public function simpleSearch($disk, $path, $payload)
  {
    $searchText = $payload["searchText"];
    $content = $this->getContent($disk, $path);

    // decompose searchText values by " "
    $searchValues = explode(" ", $searchText);

    // search for matching file names
    foreach ($content['files'] as $file) {
      foreach ($searchValues as $key => $value) {
        $filename = strtoupper($file['filename']);
        if (strpos($filename, strtoupper($value)) !== false) {
          if (!in_array($file, $this->searchResult['files'])) {
            array_push(
              $this->searchResult['files'],
              $file
            );
          }
        }
      }
    }

    // search for matching directory names
    if (count($content['directories']) !== 0) {
      foreach ($content['directories'] as $directory) {
        foreach ($searchValues as $key => $value) {
          $dirname = strtoupper($directory['basename']);
          if (strpos($dirname, strtoupper($value)) !== false) {
            if (!in_array($directory, $this->searchResult['directories'])) {
              array_push(
                $this->searchResult['directories'],
                $directory
              );
            }
          }
        }
        $this->simpleSearch($disk, $directory['path'], $payload);
      }
    }

    return [
      'result'      => [
        'status'  => 'success',
        'message' => null,
      ],
      // 'directories' => $this->searchResult['directories'],
      'directories' => $this->searchResult['directories'],
      'files'       => $this->searchResult['files'],
    ];
  }


  public function countDocuments(Request $request)
  {
    $documents = Document::all();

    $alldocumentsWithCount = [
      'documents' => $documents,
      'documents_count' => $documents->count()
    ];

    return $alldocumentsWithCount;
  }
  // Count all documents

  public function Listdocuments(Request $request)
  {
    $listdocuments_array = [];
    $documents = Document::all();

    foreach ($documents as $document) {
      $user = User::find($document->user_id)->first();
      $date = new DateTime($document->created_at);
      $listdocuments_obj = [
        'Nom' => $document->name,
        'Utilisateur' => $user->firstname . ' ' . $user->lastname,
        'Date' => $date->format('d-m-Y  H:i:s')
      ];

      array_push($listdocuments_array, $listdocuments_obj);
    }

    return $listdocuments_array;;
  }

  public function getDocumentById($id)
  {
    $document = Document::find($id);
    $metadatas = DocumentMetadata::where('document_id', $document->id)->get();
    if ($this->getDocumentPath($id) !== null) {
      $path = $this->getDocumentPath($id) . '/' . $document->name;
    } else {
      $path = $document->name;
    }
    $filename = $document->name;
    $documentParts = explode('.', $document->name);
    $extension = end($documentParts);
    $document->basename = $filename;
    $document->document_type = $document->type;
    $document->doc_id = $document->id;
    $document->extension = $extension;
    $document->metadatas = $metadatas;
    $document->path = $path;
    $document->type = 'file';
    $document->type_id = $document->document_type_id;

    return $document;
  }

  public function getDocumentsSize($disk, $path = '/')
  {
    $content = $this->getContent($disk, $path);

    foreach ($content['files'] as $file) {
      $this->totalSize += $file['size'];
    }

    if (count($content['directories']) !== 0) {
      foreach ($content['directories'] as $directory) {
        if (!in_array($directory, $this->searchResult['directories'])) {
          array_push(
            $this->searchResult['directories'],
            $directory
          );
        }
        $this->getDocumentsSize($disk, $directory['path']);
      }
    }

    return $this->totalSize;
  }

  /**
   * Advanced search for files by metadata values
   * Author: Kevin OLENGA
   * 
   * @param $disk
   * @param $path - default '/'
   * @param Request $payload
   * 
   * @return array
   */
  public function advancedSearch($disk, $path, $payload)
  {
    $documentTypeId = $payload['documentTypeId'];
    $metadatas = $payload['metadatas'];
    $content = $this->getContent($disk, $path);

    // search for files matching
    // document type and metada values
    foreach ($content['files'] as $file) {
      if (!empty($file['type_id'])) {
        if ($file['type_id'] === $documentTypeId) {
          if (count($file['metadatas'])) {
            if ($this->hasMatchingMetadata($file['metadatas'], $metadatas)) {
              if (!in_array($file, $this->searchResult['files'])) {
                array_push(
                  $this->searchResult['files'],
                  $file
                );
              }
            }
          }
        }
      }
    }

    // search for matching directory names
    if (count($content['directories']) !== 0) {
      foreach ($content['directories'] as $directory) {
        $this->advancedSearch($disk, $directory['path'], $payload);
      }
    }

    return [
      'result'      => [
        'status'  => 'success',
        'message' => null,
      ],
      'directories' => [],
      'files'       => $this->searchResult['files'],
    ];
  }

  public function searchByContent($filter, $disk, $path)
  {
    $content = $this->getContent($disk, $path);

    if (count($content['files']) > 0) {
      foreach ($content['files'] as $file) {
        $document_exists = Storage::disk('public')->exists($file['path']);
        if ($document_exists) {
          // check extension of file
          if ($file['extension'] === "pdf") {
            $file_contents = Pdf::getText(Storage::disk('public')->path($file['path']));
          } else if (
            $file['extension'] === "docx" || $file['extension'] === "doc"  ||
            $file['extension'] === "pptx" || $file['extension'] === "ppt"
          ) {
            $officeConverter = new OfficeConversion(Storage::disk('public')->path($file['path']));
            $file_contents = $officeConverter->convertToText();
          } else {
            continue;
          }

          if (\preg_match("#" . \strtolower($filter) . "#", \strtolower($file_contents))) {
            array_push($this->searchResult['files'], $file);
          }
        }
      }
    }

    //  search for matching directory names
    if (count($content['directories']) !== 0) {
      foreach ($content['directories'] as $directory) {
        $this->searchByContent($filter, $disk, $directory['path']);
      }
    }

    return [
      'result'      => [
        'status'  => 'success',
        'message' => null,
      ],
      'directories' => [],
      'files'       => $this->searchResult['files'],
    ];
  }


  private function hasMatchingMetadata($fileMetadatas, $searchMetadatas)
  {
    $data = [];
    foreach ($fileMetadatas as $fileMetadata) {
      foreach ($searchMetadatas as $searchMetadata) {
        $searchText = strtoupper($searchMetadata["text"]);
        $metadataValue = strtoupper($fileMetadata->value);
        if (
          $fileMetadata->metadata_id === $searchMetadata["id"] &&
          strpos($metadataValue, $searchText) !== false
        ) {
          if (!in_array($searchMetadata, $data)) {
            array_push($data, $searchMetadata);
          }
        }
      }
    }
    return count($data) > 0 ? true : false;
  }


  /**
   * Get part of the directory tree
   *
   * @param $disk
   * @param $path
   *
   * @return array
   */
  public function tree($disk, $path)
  {
    $directories = $this->getDirectoriesTree($disk, $path);

    return [
      'result'      => [
        'status'  => 'success',
        'message' => null,
      ],
      'directories' => $directories,
    ];
  }

  /**
   * get old Versions
   *
   * @param $document_id
   *
   * @return array
   */
  public function oldVersions($document_id)
  {
    $actual_version = Document::find($document_id);
    $last_modifier = User::find($actual_version->last_modified_by);
    $old_versions = DocumentVersion::where('document_id', $document_id)
      ->join('users', 'document_versions.user_id', 'users.id')
      ->orderBy('document_versions.created_at', 'DESC')
      ->select("document_versions.*", "users.firstname", "users.lastname")
      ->get();
    return [
      'result' => [
        'status'  => 'succes',
        'old_versions' => $old_versions,
        'actual_version' => $actual_version,
        'last_modifier' => $last_modifier
      ],
    ];
  }

  public function deleteVersion($versionId)
  {
    $documentVersion = DocumentVersion::find($versionId);
    $deleted = $documentVersion->delete();
    if ($deleted) {
      Storage::disk('document_versions')->delete($documentVersion->path);
    }

    return [
      'result' => [
        'status' => 'success'
      ]
    ];
  }

  /**
   * modify file
   *
   * @param $disk
   * @param $path
   * @param $files
   * @param $overwrite
   *
   * @return array
   */
  public function modify($disk, $path, $files, $overwrite, $comment, $document_id, $userId)
  {
    $document = Document::find($document_id);
    $fileNotUploaded = false;
    $temporary_comment = $document->comment;
    $temporary_user_id = $document->last_modified_by;
    $user = $userId ?? \Auth::id();
    // update current version
    $document->comment = $comment;
    $document->last_modified_by = $user;
    $document->save();
    $old_path = $path;

    // path for new file
    $path = $this->newPath($path, $document->name);
    $document_exists = Storage::disk('public')->exists($path);
    if ($document_exists) {
      $document_contents = Storage::disk('public')->get($path);
      $old_version = Storage::disk('public')->path($path);
    } else {
      return [
        'result' => [
          'status'  => 'warning',
          'message' => 'fileNotFound',
        ],
      ];
    }
    $document_versions = DocumentVersion::where('document_id', $document_id)->get();
    $version_of_document = count($document_versions) + 1;
    $explode_name = \explode('.', $document->name);
    $new_name = $explode_name[0] . '_v_' . $version_of_document . '_.' . end($explode_name);
    $name = $document->name;
    $new_version = Storage::disk('document_versions')->path($new_name);
    // copy the old contents in the new file
    File::copy($old_version, $new_version);

    $old_doc_exists = Storage::disk('document_versions')->exists($new_name);
    if ($old_version) {
      $document_version = new DocumentVersion;
      $document_version->name = $new_name;
      $document_version->path = '/' . $new_name;
      $document_version->document_id = $document_id;
      $document_version->disk = 'document_versions';
      $document_version->comment = $temporary_comment;
      $document_version->user_id = $temporary_user_id ?? $user;
      $document_version->save();
    } else {
      return [
        'result' => [
          'status'  => 'warning',
          'message' => 'Old document could not find',
        ],
      ];
    }

    Storage::disk('public')->delete($path);

    // check file size if need
    if (
      $this->configRepository->getMaxUploadFileSize()
      && $files[0]->getSize() / 1024 > $this->configRepository->getMaxUploadFileSize()
    ) {
      $fileNotUploaded = true;
    }

    // check file type if need
    if (
      $this->configRepository->getAllowFileTypes()
      && !in_array(
        $files[0]->getClientOriginalExtension(),
        $this->configRepository->getAllowFileTypes()
      )
    ) {
      $fileNotUploaded = true;
    }

    // check file type if need
    if (
      $this->configRepository->getAllowFileTypes()
      && !in_array(
        $files[0]->getClientOriginalExtension(),
        $this->configRepository->getAllowFileTypes()
      )
    ) {
      $fileNotUploaded = true;
    }

    // overwrite or save file
    Storage::disk($disk)->putFileAs(
      $old_path,
      $files[0],
      $name
    );

    $pathParts = explode("/", $path);

    $document->name = $name;
    $document->save();



    if ($fileNotUploaded) {
      return [
        'result' => [
          'status'  => 'warning',
          'message' => 'notAllUploaded',
        ],
      ];
    }

    $this->logsTracker->modify($name);

    return [
      'result' => [
        'status'  => 'success',
        'message' => 'uploaded',
      ],
    ];
  }

  public function modifyDocumentType($document_id, $docType_id)
  {
    $document = Document::find($document_id);
    $document_metadata = DocumentMetadata::where('document_id', $document->id);
    if (!empty($document_metadata)) {
      $document_metadata->delete();
    }
    $document->document_type_id = $docType_id;
    $document->save();
    return [
      'result' => [
        'status'  => 'success',
        'message' => 'updated',
        'data' => $document
      ],
    ];
  }

  /**
   * Upload files
   *
   * @param $disk
   * @param $path
   * @param $files
   * @param $overwrite
   *
   * @return array
   */
  public function upload($disk, $path, $files, $overwrite, $documentTypes)
  {
    $level = count(explode("/", $path));
    $fileNotUploaded = false;
    for ($i = 0; $i <= count($files) - 1; $i++) {
      $fileName = $files[$i]->getClientOriginalName();
      $docType = $documentTypes[$i];
      // skip or overwrite files
      if (
        !$overwrite
        && Storage::disk($disk)
        ->exists($path . '/' . $fileName)
      ) {
        return response()->json([
          'result' => [
            'status'  => 'warning',
            'message' => 'notAllUploaded',
          ],
        ], 400);
      }

      // check file size if need
      if (
        $this->configRepository->getMaxUploadFileSize()
        && $files[$i]->getSize() / 1024 > $this->configRepository->getMaxUploadFileSize()
      ) {
        $fileNotUploaded = true;
        continue;
      }

      // check file type if need
      if (
        $this->configRepository->getAllowFileTypes()
        && !in_array(
          $files[$i]->getClientOriginalExtension(),
          $this->configRepository->getAllowFileTypes()
        )
      ) {
        $fileNotUploaded = true;
        continue;
      }

      // overwrite or save file
      Storage::disk($disk)->putFileAs(
        $path,
        $files[$i],
        $fileName
      );

      $pathParts = explode("/", $path);
      $folderName = $pathParts[count($pathParts) - 1];
      $folders = Folder::where('name', $folderName)->where('level', count($pathParts))->get();

      if (!is_null($folders)) {
        foreach ($folders as $folder) {
          $this->getFolderPath($folder->id);
          $tempPath = implode('/', $this->folderPath);
          if ($path === $tempPath) {
            $folderId = $folder->id;
            $level = count($pathParts) + 1;
          }
          $this->folderPath = [];
        }
      } else {
        $folderId = null;
        $level = 1;
      }

      // reference the file in the database
      Document::create([
        'name' => $fileName,
        'user_id' => \Auth::id(),
        'level' => $level,
        'document_type_id' => $docType,
        'folder_id' => $folderId
      ]);

      $this->logsTracker->uploadDocument($path, $fileName);
    }


    // If the some file was not uploaded
    if ($fileNotUploaded) {
      return [
        'result' => [
          'status'  => 'warning',
          'message' => 'notAllUploaded',
        ],
      ];
    }

    return [
      'result' => [
        'status'  => 'success',
        'message' => 'uploaded',
      ],
    ];
  }

  /**
   * Delete files and folders
   *
   * @param $disk
   * @param $items
   *
   * @return array
   */
  public function delete($disk, $items)
  {
    $deletedItems = [];

    foreach ($items as $item) {
      // check all files and folders - exists or no
      if (!Storage::disk($disk)->exists($item['path'])) {
        continue;
      } else {
        $level = count(explode('/', $item['path']));
        if ($item['type'] === 'dir') {
          if ($this->mainPath($item['path'])) {
            return response()->json(["message" => "forbidden"], 403);
          } else {
            $this->deleteDirectory($level, $item['path']);
          }

          Storage::disk('public')->deleteDirectory($item['path']);
        } else {

          if ($this->checkDocumentState($item['path'])) {
            return response()->json(["message" => "forbidden"], 401);
          }
          $this->deleteDocument($item['path']);
        }
      }

      // add deleted item
      $deletedItems[] = $item;
    }

    event(new Deleted($disk, $deletedItems));

    return [
      'result' => [
        'status'  => 'success',
        'message' => 'deleted',
      ],
    ];
  }

  public function deleteDocumentVersions($document)
  {
    $itemParts = explode('/', $document);
    $count = count($itemParts);
    $itemName = $itemParts[$count - 1];
    $document = Document::where('name', $itemName)->where('level', $count)->first();
    if ($document) {
      $versions = DocumentVersion::where('document_id', $document->id)->get();
      if ($versions) {
        foreach ($versions as $version) {
          $version->delete();
        }
      }
    }
  }

  public function deleteCommentTag($document)
  {
    $itemParts = explode('/', $document);
    $count = count($itemParts);
    $itemName = $itemParts[$count - 1];
    $document = Document::where('name', $itemName)->where('level', $count)->first();
    if (!empty($document)) {
      $comments = Comment::where('doc_id', $document->id)->get();
      if (!empty($comment)) {
        foreach ($comments as $comment) {
          $comment->delete();
        }
      }
    }
  }

  public function deleteRecentDocument($document)
  {
    $itemParts = explode('/', $document);
    $count = count($itemParts);
    $itemName = $itemParts[$count - 1];
    $document = Document::where('name', $itemName)->where('level', $count)->first();
    if ($document) {
      $latestDocuments = LatestDocument::where('document_id', $document->id)->where('user_id', Auth::id())->get();
      if ($latestDocuments) {
        foreach ($latestDocuments as $document) {
          $document->delete();
        }
      }
    }
  }

  public function checkDocumentState($document)
  {
    $itemParts = explode('/', $document);
    $count = count($itemParts);
    $itemName = $itemParts[$count - 1];
    $inProcess = false;
    $document = Document::where('name', $itemName)->where('level', $count)->first();
    if ($document) {
      $doc_state = DocumentState::where('document_id', $document->id)->first();
      if ($doc_state) {
        $inProcess = true;
      }
      return $inProcess;
    }
  }

  //=========================================================================

  public function mainPath($directory)
  {
    $directories = Storage::disk('public')->directories($directory);
    $files = Storage::disk('public')->files($directory);
    $inProcess = false;
    if ($files == null && $directories == null) {
      $level = count(explode('/', $directory));
      Storage::disk('public')->deleteDirectory($directory);
      $this->logsTracker->deleteFolder($directory);
      $this->deleteDirectory($level, $directory);
    }
    if ($directories !== null) {
      foreach ($directories as $dir) {
        $this->checkChildren($dir, $directory);
      }
    }
    if ($files !== null) {
      foreach ($files as $file) {
        $doc_state = $this->checkDocumentState($file);
        if ($doc_state) {
          $inProcess = true;
          break;
        } else {
          $this->deleteDocument($file);
        }
      }
    }
    if ($inProcess) {
      return $inProcess;
    }
  }

  public function checkChildren($directory, $mainPath)
  {
    $inProcess = false;
    $directories = Storage::disk('public')->directories($directory);
    $files = Storage::disk('public')->files($directory);

    if (count($directories) > 0) {
      foreach ($directories as $directory) {
        $this->checkChildren($directory, $mainPath);
      }
    } else {
      $level = count(explode('/', $directory));
      $this->deleteDirectory($level, $directory);
      Storage::disk('public')->deleteDirectory($directory);
      $this->logsTracker->deleteFolder($directory);
      $this->mainPath($mainPath);
    }

    if ($files !== null) {
      foreach ($files as $file) {
        $doc_state = $this->checkDocumentState($file);
        if ($doc_state) {
          $inProcess = true;
          break;
        } else {
          $this->deleteDocument($file);
        }
      }
    }

    if ($inProcess) {
      return response()->json(["message" => "forbidden"], 403);
    }
  }

  public function deleteDirectory($level, $path)
  {
    $pathParts = explode('/', $path);
    $folder = Folder::where('name', $pathParts[count($pathParts) - 1])->where('level', $level)->first();

    if ($folder !== null) {
      $folderId = $folder->id;
      if ($folder != null) {
        $folder = Folder::where('id', $folderId)->first();
        $files = Document::where('folder_id', $folderId)->get();

        if ($files !== null) {
          foreach ($files as $file) {
            $file->delete();
          }
        }
        $folder->delete();
      }
    }
  }

  public function deleteDocument($path)
  {
    $this->deleteDocumentVersions($path);
    $this->deleteCommentTag($path);
    $this->deleteRecentDocument($path);
    $pathParts = explode("/", $path);
    $level = count($pathParts);
    $fileName = $pathParts[count($pathParts) - 1];
    if (count($pathParts) == 1) {
      $level = 1;
      $document = Document::where("folder_id", null)
        ->where("name", $fileName)
        ->where('level', $level)
        ->first();
    } else {
      $parentFolder = $pathParts[count($pathParts) - 2];
      $folders = Folder::where("name", $parentFolder)->where('level', $level - 1)->get();

      foreach ($folders as $folder) {
        $this->getFolderPath($folder->id);
        $tempPath = implode("/", $this->folderPath);

        if (Storage::disk('public')->exists($tempPath . "/" . $fileName)) {
          $folderId = $folder->id;
        }
        $this->folderPath = [];
      }
      $document = Document::where("folder_id", $folderId)
        ->where("name", $fileName)
        ->where('level', $level)
        ->first();
    }

    if ($document !== null) {
      $doc_metadata = DocumentMetadata::where('document_id', $document->id)->get();
      foreach ($doc_metadata as $doc_meta) {
        $doc_meta->delete();
      }
      $document->delete();
      $this->logsTracker->deleteDocument($path);
    }
    Storage::disk('public')->delete($path);
  }

  //====================================================================================

  /**
   * Copy / Cut - Files and Directories
   *
   * @param $disk
   * @param $path
   * @param $clipboard
   *
   * @return array
   */
  public function paste($disk, $path, $clipboard)
  {
    $level = count(explode("/", $path));
    $type = $clipboard['type'];

    foreach ($clipboard['directories'] as $directory) {
      $checkSubDirectories = Storage::disk($disk)->directories($directory);
      if (count($clipboard['directories']) === 1 && count($checkSubDirectories) === 0) {
        $dirParts = explode('/', $directory);
        $this->createDirectory($disk, $path, $dirParts[count($dirParts) - 1]);
      } else {
        $this->storeOnPaste($path, $type, $directory);
      }
    }

    if ($clipboard['files'] !== null) {
      foreach ($clipboard['files'] as $file) {
        $this->storeOnPaste($path, $type, $file);
      }
    }
    // compare disk names
    if ($disk !== $clipboard['disk']) {
      if (!$this->checkDisk($clipboard['disk'])) {
        return $this->notFoundMessage();
      }
    }
    $transferService = TransferFactory::build($disk, $path, $clipboard);
    return $transferService->filesTransfer();
  }

  /**
   * Store a dorectory on paste action
   *
   * @param $path
   * @param $type
   * @param $directory
   *
   * @return
   */
  private function storeOnPaste($path, $type, $item)
  {
    if ($type !== 'copy') {
      $this->updateRulesOnPaste($path, $item, $type);
    }
    $info = Storage::disk("public")->getMetadata($item);
    $itemType = $info['type'];

    if ($itemType == 'dir') {
      $this->storeDirectories($path, $type, $item);
    } else {
      $this->storeDocuments($path, $type, $item);
    }
  }

  public function updateRulesOnPaste($path, $item, $type)
  {
    if (Storage::disk("public")->exists($item)) {
      $itemParts = explode('/', $item);
      $count = count($itemParts);
      $newItem = $itemParts[$count - 1];
      if ($path !== null) {
        $newLocation = $path . '/' . $newItem;
      } else {
        $newLocation = $newItem;
      }
      $this->setAclRules($newLocation, $item, $type);
    }
  }

  public function storeDirectories($path, $type, $directory)
  {
    $clipboardParts = explode('/', $directory);
    $pathParts = explode('/', $path);
    $folderLevel = count($clipboardParts);
    $folderName = $clipboardParts[$folderLevel - 1];
    $parentLevel = count($pathParts);
    $parentFolderName = $pathParts[$parentLevel - 1];
    $targetedFolder = Folder::where('level', $folderLevel)->where('name', $folderName)->first();

    if ($path !== null) {
      if ($parentLevel <= 1) {
        if ($pathParts[0] == "") {
          $parentLevel = 1;
        }
      }
      $newLocation = Folder::where('name', $parentFolderName)->where('level', $parentLevel)->first();
      $newParentId = $newLocation->id;
    } else {
      $parentLevel = null;
      $newLocation = null;
      $newParentId = null;
    }

    if ($type === 'copy') {
      Folder::create([
        'parentId' => $newParentId,
        'name' => $folderName,
        'level' => $parentLevel + 1
      ]);
      $folder = Folder::where('level', $parentLevel + 1)->where('name', $folderName)->where('parentId', $newParentId)->first();
      $this->mainCopy($folder->id, $targetedFolder->id);
    } else {
      $targetedFolder->parentId = $newParentId;
      $targetedFolder->level = $parentLevel + 1;
      $targetedFolder->save();

      $this->mainCut($targetedFolder->id);
      $this->logsTracker->moveFolder();
    }
  }
  private $folderPath = [];
  public function getFolderPath($folderId)
  {
    $folder = Folder::find($folderId);
    array_unshift($this->folderPath, $folder->name);
    if (!is_null($folder->parentId)) {
      $this->getFolderPath($folder->parentId);
    }
  }

  public function storeDocuments($path, $type, $file)
  {
    $clipboardParts = explode('/', $file);
    $pathParts = explode('/', $path);
    $fileName = $clipboardParts[count($clipboardParts) - 1];
    if (count($clipboardParts) == 1) {
      $level = 1;
      $document = Document::where('folder_id', null)->where('level', $level)->first();
      $folderId = null;
    } else {
      $folderName = $clipboardParts[count($clipboardParts) - 2];
      $level = count($clipboardParts) - 1;
      $folders = Folder::where('name', $folderName)->where('level', $level)->get();
      $folderId = null;

      foreach ($folders as $folder) {
        $this->getFolderPath($folder->id);
        $folderPath = implode('/', $this->folderPath);
        $fileExist = Storage::disk('public')->exists($folderPath . '/' . $fileName);
        if ($fileExist) {
          echo "PAth : $folderPath <br>";
          $folderId = $folder->id;
        }
        $this->folderPath = [];
      }
      $document = Document::where('name', $fileName)->where('folder_id', $folderId)->first();
    }

    if ($pathParts[0] != null) {
      $newParentFolder = Folder::where('name', $pathParts[count($pathParts) - 1])->where('level', count($pathParts))->get();
      foreach ($newParentFolder as $parent) {
        $this->getFolderPath($parent->id);
        $tempPath = implode('/', $this->folderPath);
        if ($path === $tempPath) {
          $newParentId = $parent->id;
        }
        $this->folderPath = [];
      }
      $level = count($pathParts) + 1;
    } else {
      $newParentId = null;
      $level = count($pathParts);
    }

    if ($type === 'copy') {
      Document::create([
        'folder_id' => $newParentId,
        'user_id' => $document->user_id,
        'name' => $fileName,
        'document_type_id' => $document->document_type_id,
        'level' => $level
      ]);
      $this->logsTracker->copyDocument($path . '/' . $fileName);
    } else {
      $document->folder_id = $newParentId;
      $document->level = $level;
      $document->save();
      $this->logsTracker->moveDocument();
    }
  }

  public function mainCut($id)
  {
    $folders = Folder::where('parentId', $id)->get();
    $files = Document::where('folder_id', $id)->get();
    $parent = Folder::where('id', $id)->first();

    if ($folders !== null && $parent !== null) {
      foreach ($folders as $folder) {
        $level = $parent->level;
        $type = 'dir';
        $this->cutPaste($folder->id, $level, $type);
      }

      if ($files !== null) {
        foreach ($files as $file) {
          $level = $parent->level;
          $type = 'file';
          $this->cutPaste($file->id, $level, $type);
        }
      }

      foreach ($folders as $folder) {
        $level = $parent->level;
        $this->recursiveCut($folder, $level);
      }
    }
  }

  public function mainCopy($id, $oldId)
  {
    $newFolder = Folder::where('id', $id)->first();
    $oldFolders = Folder::where('parentId', $oldId)->get();
    $oldFiles = Document::where('folder_id', $oldId)->get();

    if ($oldFolders !== null && $oldId !== null) {
      foreach ($oldFolders as $folder) {
        $level = $newFolder->level;
        $this->copyPaste($folder, $newFolder->id, $level, 'dir');
      }

      if ($oldFiles !== null) {
        foreach ($oldFiles as $file) {
          $level = $newFolder->level;
          $this->copyPaste($file, $newFolder->id, $level, 'file');
        }
      }

      foreach ($oldFolders as $folder) {
        $level = $folder->level;
        $folderNew = Folder::where('name', $folder->name)->where('parentId', $newFolder->id)->where('level', $newFolder->level + 1)->first();
        $this->recursiveCopy($folderNew->id, $folder->id);
      }
    }
  }

  public function cutPaste($id, $level, $type)
  {
    if ($type == 'dir')
      Folder::where('id', $id)->update(['level' => $level + 1]);
    else
      Document::where('id', $id)->update(['level' => $level + 1]);
  }

  public function copyPaste($item, $parentId, $parentLevel, $type)
  {
    if ($type == 'dir') {
      Folder::create([
        'parentId' => $parentId,
        'name' => $item->name,
        'level' => $parentLevel + 1
      ]);
      $this->logsTracker->copyFolder($item->name);
    } else {
      Document::create([
        'folder_id' => $parentId,
        'user_id' => $item->user_id,
        'name' => $item->name,
        'document_type_id' => $item->document_type_id,
        'level' => $parentLevel + 1
      ]);
      $this->logsTracker->copyDocument($item->name);
    }
  }

  public function recursiveCut($folder, $level)
  {

    if ($folder !== null) {
      $this->cutPaste($folder->id, $level, 'dir');
      $this->mainCut($folder->id);
    } else {
      $folder->level = $level + 1;
      $folder->save();
    }
  }

  public function recursiveCopy($id, $oldId)
  {
    $newFolder = Folder::where('id', $id)->first();
    $folders = Folder::where('parentId', $oldId)->get();

    if ($folders !== null) {
      foreach ($folders as $folder) {
        $level = $newFolder->level;
        $this->copyPaste($folder, $newFolder->id, $level, 'dir');
        $folderNew = Folder::where('name', $folder->name)->where('parentId', $newFolder->id)->where('level', $newFolder->level + 1)->first();
        $this->mainCopy($folderNew->id, $folder->id);
      }
    }
  }

  /**
   * Rename file or folder
   *
   * @param $disk
   * @param $newName
   * @param $oldName
   *
   * @return array
   */
  public function rename($disk, $newName, $oldName)
  {

    $oldFileNameParts = explode("/", $oldName);
    $newFileNameParts = explode("/", $newName);
    $oldFileName = $oldFileNameParts[count($oldFileNameParts) - 1];
    $newFileName = $newFileNameParts[count($newFileNameParts) - 1];
    $level = count($oldFileNameParts);

    $info = Storage::disk($disk)->getMetadata($oldName);
    $type = $info['type'];

    if ($type == 'dir') {
      // check if the file was renamed on the server
      if (Storage::disk($disk)->move($oldName, $newName)) {
        $folder = Folder::where('name', $oldName)->where('level', $level)->first();
        if ($folder !== null) {
          $folder->name = $newFileName;
          $folder->save();
        }
      }
      $this->logsTracker->renameFolder($oldName, $newFileName);
    } else {
      $split_name = \explode('.', $oldFileName);
      $ext = end($split_name);
      if (Storage::disk($disk)->move($oldName, $newName . '.' . $ext)) {
        // rename the file reference in the database
        if (count($oldFileNameParts) !== 1) {
          $folderName = $oldFileNameParts[count($oldFileNameParts) - 2];
          $level = count($oldFileNameParts) - 1;
        } else {
          $folderName = null;
          $level = count($oldFileNameParts);
        }
        $folders = Folder::where('name', $folderName)->where('level', $level)->get();
        $folderId = null;
        foreach ($folders as $folder) {
          $this->getFolderPath($folder->id);
          $folderPath = implode('/', $this->folderPath);
          $fileExist = Storage::disk('public')->exists($folderPath . '/' . $newFileName . '.' . $ext);
          if ($fileExist) {
            $folderId = $folder->id;
          }
          $this->folderPath = [];
        }
        $document = Document::where('name', $oldFileName)
          ->where('folder_id', $folderId)
          ->where('level', $level + 1)->first();
        if ($document) {
          $document->name = $newFileName . '.' . $ext;
          $document->save();
        }

        // Call the function who'll update rules
        $this->setAclRules($newName . '.' . $ext, $oldName, "rename");
        $this->logsTracker->renameDocument($oldName, $newName . '.' . $ext);
      }
    }

    return [
      'result' => [
        'status'  => 'success',
        'message' => 'renamed'
      ],
    ];
  }

  /**
   * Download selected file
   *
   * @param $newPath
   * @param $oldPath
   *
   * @return
   */
  public function setAclRules($newPath, $oldPath, $type)
  {
    $rules = DB::table('acl_rules')
      ->orWhere('path', 'like', '%' . $oldPath . '%')
      ->get();
    if ($rules !== null) {
      foreach ($rules as $rule) {
        $modif = str_replace("/", "|", $oldPath);
        $pattern = '/^' . $modif . '/i';
        if (preg_match($pattern, str_replace("/", "|", $rule->path))) {
          $retreive = str_replace("|", "/", $rule->path);
          if ($type == "rename") {
            $newLocation = str_replace($oldPath, $newPath, $retreive);
          } else if ($type == "cut") {
            $newLocation = str_replace($oldPath, $newPath, $retreive);
          }
          DB::table('acl_rules')
            ->where('id', $rule->id)
            ->update(['path' => $newLocation]);
        }
      }
    }
  }

  /**
   * Download selected file
   *
   * @param $disk
   * @param $path
   *
   * @return mixed
   */
  public function download($disk, $path)
  {
    // if file name not in ASCII format
    if (!preg_match('/^[\x20-\x7e]*$/', basename($path))) {
      $filename = Str::ascii(basename($path));
    } else {
      $filename = basename($path);
    }
    // check file extension 
    $path_explode = explode('.', $path);
    $extension = array_pop($path_explode);
    $full_path = Storage::disk($disk)->path($path);
    if ($extension === "mp4") {
      // return ["file" => Storage::disk($disk)->path($path)];
      return response()->streamDownload(function () use ($disk, $path) {
        echo Storage::disk($disk)->url($path);
        // echo $disk . " - " . $path;
      }, 'ahaaaa');
    }

    return Storage::disk($disk)->response($path);
  }

  public function previewFile($user_id, $disk, $path)
  {
    // if file name not in ASCII format
    if (!preg_match('/^[\x20-\x7e]*$/', basename($path))) {
      $filename = Str::ascii(basename($path));
    } else {
      $filename = basename($path);
    }
    // check file extension 
    $path_explode = explode('.', $path);
    $extension = array_pop($path_explode);

    if (
      $extension === "docx" || $extension === "doc" ||
      $extension === "xlsx" || $extension === "xls" ||
      $extension === "pptx" || $extension === "ppt"
    ) {
      $full_path = "";

      for ($i = 0; $i <= count($path_explode) - 1; $i++) {
        $full_path = $full_path . $path_explode[$i];
      }

      $explode_full_path = \explode('/', $full_path);
      $file_name = array_pop($explode_full_path);

      $path_parts = \explode('/', $path);
      $name_parts = explode('.', $path_parts[count($path_parts) - 1]);
      $ext = array_pop($name_parts);
      $name_of_file = "";
      for ($i = 0; $i <= count($name_parts) - 1; $i++) {
        $name_of_file = $name_of_file . $path_parts[0];
      }
      $converter = new OfficeConverter(Storage::disk('public')->path($path), Storage::disk('temp')->path('.'));
      $converter->convertTo($name_of_file . '.pdf');

      return Storage::disk('temp')->response($name_of_file . '.pdf');
    }
    $this->userDocumentActivity($user_id, $path);
    return Storage::disk($disk)->response($path);
  }

  public function userDocumentActivity($user_id, $doc)
  {
    $docParts = explode('/', $doc);
    $level = count($docParts);
    $docName = $docParts[$level - 1];
    $docParent = ($level == 1) ? null : $docParts[$level - 2];
    $parent = Folder::where('name', $docParent)->where('level', $level - 1)->first();
    $document = Document::where('name', $docName)->where('level', $level)->first();
    if ($document) {
      $docActivity = LatestDocument::where('document_id', $document->id)
        ->where('user_id', $user_id)
        ->first();
      if ($docActivity) {
        $docActivity->delete();
      }

      $docs = LatestDocument::where('user_id', $user_id)->get();

      if (count($docs) >= 5) {
        $oldDocument = LatestDocument::where('user_id', $user_id)->first();
        $oldDocument->delete();
      }
      LatestDocument::create([
        'document_id' => $document->id,
        'user_id' => $user_id
      ]);
    } else {
      return response()->json(['success' => false, 'message' => 'Document doesn\'t exist'], 401);
    }
  }

  /**
   * Download selected file
   *
   * @param $disk
   * @param $path
   *
   * @return mixed
   */
  public function downloadOldVersion($disk, $path)
  {
    // if file name not in ASCII format
    if (!preg_match('/^[\x20-\x7e]*$/', basename($path))) {
      $filename = Str::ascii(basename($path));
    } else {
      $filename = basename($path);
    }

    // Returns a file response
    // $file =  Storage::disk($disk)->get($path);
    return Storage::disk($disk)->response($path);
    // return Response::make($file, 200, [
    //     'Content-Type' => $mimetype,
    //     'Content-Disposition' => 'inline; filename="'.$filename.'"'
    // ]);
  }

  public function officeEdit($user_id, $path)
  {
    // $path = $safebox.'/'.$binder.'/'.$folder.'/'.$filename;

    // Returns a file response
    // $file =  Storage::disk($disk)->get($path);
    $pathParts = explode("/", $path);
    array_shift($pathParts);
    $filePath = implode("/", $pathParts);
    $this->userDocumentActivity($user_id, $filePath);
    return Storage::disk('public')->response($filePath);
    // return Response::make($file, 200, [
    //     'Content-Type' => $mimetype,
    //     'Content-Disposition' => 'inline; filename="'.$filename.'"'
    // ]);
  }

  public function officeEditSave($file, $path, $filename, $userId, $documentId)
  {
    try {
      $this->logsTracker->officeEditSave($filename);
      return Storage::disk('public')->putFileAs(
        $path,
        $file,
        $filename
      );
    } catch (\Throwable $e) {
      return $e->getMessage();
    }
  }

  /**
   * Create thumbnails
   *
   * @param $disk
   * @param $path
   *
   * @return \Illuminate\Http\Response|mixed
   * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
   */
  public function thumbnails($disk, $path)
  {
    // create thumbnail
    if ($this->configRepository->getCache()) {
      $thumbnail = Image::cache(function ($image) use ($disk, $path) {
        $image->make(Storage::disk($disk)->get($path))->fit(80);
      }, $this->configRepository->getCache());

      // output
      return response()->make(
        $thumbnail,
        200,
        ['Content-Type' => Storage::disk($disk)->mimeType($path)]
      );
    }

    $thumbnail = Image::make(Storage::disk($disk)->get($path))->fit(80);

    return $thumbnail->response();
  }

  /**
   * Image preview
   *
   * @param $disk
   * @param $path
   *
   * @return mixed
   * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
   */
  public function preview($disk, $path)
  {
    // get image
    $preview = Image::make(Storage::disk($disk)->get($path));

    return $preview->response();
  }

  /**
   * Get file URL
   *
   * @param $disk
   * @param $path
   *
   * @return array
   */
  public function url($disk, $path)
  {
    return [
      'result' => [
        'status'  => 'success',
        'message' => null,
      ],
      'url'    => Storage::disk($disk)->url($path),
    ];
  }

  /**
   * Create new directory
   *
   * @param $disk
   * @param $path
   * @param $name
   *
   * @return array
   */
  public function createDirectory($disk, $path, $name)
  {
    // path for new directory
    $directoryName = $this->newPath($path, $name);

    // get the level of the directory
    $level = count(explode('/', $directoryName));

    // Save folder
    $this->saveFolder($path, $name, $level);

    // check - exist directory or no
    if (Storage::disk($disk)->exists($directoryName)) {
      return [
        'result' => [
          'status'  => 'warning',
          'message' => 'dirExist',
        ],
      ];
    }


    // create new directory
    Storage::disk($disk)->makeDirectory($directoryName);

    // get directory properties
    $directoryProperties = $this->directoryProperties(
      $disk,
      $directoryName
    );

    // dd($directoryProperties['uuid']);


    // add directory properties for the tree module
    $tree = $directoryProperties;
    $tree['props'] = ['hasSubdirectories' => false];


    return [
      'result'    => [
        'status'  => 'success',
        'message' => 'dirCreated',
      ],
      'directory' => $directoryProperties,
      'tree'      => [$tree],
    ];
  }

  /**
   * Store folder
   *
   * @param $path
   * @param $name
   *
   * @return
   */
  private function saveFolder($path, $name, $level)
  {

    $pathParts = explode('/', $path);

    if ($level <= 1) {
      if ($pathParts[0] == "") {
        $level = 1;
      } else {
        $level++;
      }
    }
    if ($level > 1) {
      $parentFolder = Folder::where('name', $pathParts[count($pathParts) - 1])->get();
      foreach ($parentFolder as $parent) {
        $this->getfolderPath($parent->id);
        $tempPath = implode('/', $this->folderPath);
        if ($tempPath === $path) {
          $parentFolderId = $parent->id;
        }
        $this->folderPath = [];
      }
    } else {
      if ($pathParts[0] != "") {
        $parentFolder = Folder::where('name', $pathParts[count($pathParts) - 1])->first();
        $parentFolderId = $parentFolder->id;
      } else {
        $parentFolderId = null;
      }

      $parentFolder = null;
    }

    if ($parentFolder != null) {
      Folder::create([
        'parentId' => $parentFolderId,
        'name' => $name,
        'level' => $level
      ]);
    } else {
      Folder::create([
        'parentId' => $parentFolderId,
        'name' => $name,
        'level' => $level
      ]);
    }

    $this->logsTracker->createFolder($name);
  }

  /**
   * Create new file
   *
   * @param $disk
   * @param $path
   * @param $name
   *
   * @return array
   */
  public function createFile($disk, $path, $name, $type, $officeType)
  {

    $filename = $name;
    // path for new file
    $path = $this->newPath($path, $filename);

    // check - exist file or no
    if (Storage::disk($disk)->exists($path)) {
      return [
        'result' => [
          'status'  => 'warning',
          'message' => 'fileExist',
        ],
      ];
    }

    if ($officeType === "doc") {
      $phpWord = new PhpWord();
      $wordFile = WordFactory::createWriter($phpWord, 'Word2007');
      $wordFile->save(Storage::disk($disk)->path($path));
    } else if ($officeType === "xls") {
      $spreadsheet = new Spreadsheet();
      $excelWriter = SpreadsheetFactory::createWriter($spreadsheet, 'Xlsx');
      $excelWriter->save(Storage::disk($disk)->path($path));
    } else if ($officeType === "ppt") {
      $phpPresentation = new PhpPresentation();
      $pptWriter = PresentationFactory::createWriter($phpPresentation, 'PowerPoint2007');
      $pptWriter->save(Storage::disk($disk)->path($path));
    } else {
      Storage::disk($disk)->put($path, '');
    }


    $pathParts = explode("/", $path);
    $level = count($pathParts) - 1;
    if ($level != 0) {
      $folderName = $pathParts[$level - 1];
    } else {
      $folderName = "";
    }
    $folder = Folder::where("level", $level)->where('name', $folderName)->first();

    if ($folder !== null) {
      $folderId = $folder->id;
      $level = count($pathParts);
    } else {
      $folderId = null;
      $level = 1;
    }

    Document::create([
      'name' => $filename,
      'level' => $level,
      'user_id' => \Auth::id(),
      'document_type_id' => $type,
      'folder_id' => $folderId
    ]);

    // get file properties
    $fileProperties = $this->fileProperties($disk, $path);

    $this->logsTracker->createDocument($path);

    return [
      'result' => [
        'status'  => 'success',
        'message' => 'fileCreated',
      ],
      'file'   => $fileProperties,
    ];
  }

  /**
   * Update file
   *
   * @param $disk
   * @param $path
   * @param $file
   *
   * @return array
   */
  public function updateFile($disk, $path, $file)
  {
    // update file
    Storage::disk($disk)->putFileAs(
      $path,
      $file,
      $file->getClientOriginalName()
    );

    // path for new file
    $filePath = $this->newPath($path, $file->getClientOriginalName());

    // get file properties
    $fileProperties = $this->fileProperties($disk, $filePath);

    return [
      'result' => [
        'status'  => 'success',
        'message' => 'fileUpdated',
      ],
      'file'   => $fileProperties,
    ];
  }

  /**
   * Stream file - for audio and video
   *
   * @param $disk
   * @param $path
   *
   * @return mixed
   */
  public function streamFile($disk, $path)
  {
    // if file name not in ASCII format
    if (!preg_match('/^[\x20-\x7e]*$/', basename($path))) {
      $filename = Str::ascii(basename($path));
    } else {
      $filename = basename($path);
    }

    return Storage::disk($disk)
      ->response($path, $filename, ['Accept-Ranges' => 'bytes']);
  }

  public function getDocumentPath($document_id)
  {
    $this->pathParts = [];
    $full_path = "";
    $document = Document::find($document_id);
    if (empty($document)) {
      return [
        'result' => [
          'status'  => 'error',
          'message' => 'FileNotFound'
        ]
      ];
    }
    if ($document->folder_id == null) {
      $full_path = "";
    } else {
      $full_path = $this->getParentFolder($document->folder_id);
    }
    return $full_path;
  }

  private $pathParts = [];
  public function getParentFolder($folder_id)
  {
    $folder = Folder::find($folder_id);
    $full_path = "";
    array_push($this->pathParts, $folder->name);

    if ($folder->parentId != null) {
      $this->getParentFolder($folder->parentId);
    }

    for ($i = count($this->pathParts) - 1; $i >= 0; $i--) {
      $full_path = $full_path . '/' . $this->pathParts[$i];
    }
    return $full_path;
  }
}

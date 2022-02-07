<?php

namespace App\FileManager\Traits;

use Storage;
use App\Document;
use App\DocumentMetadata;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\FileManager\Services\ACLService\ACL;

trait ContentTrait
{

    /**
     * Get content for the selected disk and path
     *
     * @param       $disk
     * @param  null $path
     *
     * @return array
     */
    public function getContent($disk, $path = null)
    {
        $content = Storage::disk($disk)->listContents($path);

        // get a list of directories
        $directories = $this->filterDir($disk, $content);

        $files = [];

        // get a list of files
        $filesData = $this->filterFile($disk, $content);        
        
        foreach ($filesData as $file) {
            array_push($files, $this->fileProperties($disk, $file['path']));
        }

        return compact('directories', 'files');
    }

    /**
     * Get directories with properties
     *
     * @param       $disk
     * @param  null $path
     *
     * @return array
     */
    public function directoriesWithProperties($disk, $path = null)
    {
        $content = Storage::disk($disk)->listContents($path);

        return $this->filterDir($disk, $content);
    }

    /**
     * Get files with properties
     *
     * @param       $disk
     * @param  null $path
     *
     * @return array
     */
    public function filesWithProperties($disk, $path = null)
    {
        $content = Storage::disk($disk)->listContents($path);

        return $this->filterFile($disk, $content);
    }

    /**
     * Get directories for tree module
     *
     * @param $disk
     * @param $path
     *
     * @return array
     */
    public function getDirectoriesTree($disk, $path = null)
    {
        $directories = $this->directoriesWithProperties($disk, $path);

        foreach ($directories as $index => $dir) {
            $directories[$index]['props'] = [
                'hasSubdirectories' => Storage::disk($disk)
                    ->directories($dir['path']) ? true : false,
            ];
        }

        return $directories;
    }

    /**
     * File properties
     *
     * @param       $disk
     * @param  null $path
     *
     * @return mixed
     */
    public function fileProperties($disk, $path = null)
    {
        $file = Storage::disk($disk)->getMetadata($path);

        $pathInfo = pathinfo($path);

        $file['basename'] = $pathInfo['basename'];
        $file['dirname'] = $pathInfo['dirname'] === '.' ? ''
            : $pathInfo['dirname'];
        $file['extension'] = isset($pathInfo['extension'])
            ? $pathInfo['extension'] : '';
        $file['filename'] = $pathInfo['filename'];
        
        // get file metadata from the database
        $document = Document::where('name', $pathInfo['basename'])->first();
        if ($document) {
            $file['doc_id'] = $document->id;
            $file['author'] = $document->user->name();
            $file['author_id'] = $document->user->id;
            $file['document_type'] = $document->type->name;
            $file['type_id'] = $document->type->id;
            $file['last_modified_by'] = $document->last_modified_by;
            $file['comment'] = $document->comment;
            
            // Get document metadata from database
            $metadatas = DB::table('document_metadata')
                            ->join('documents', 'document_metadata.document_id','documents.id')
                            ->join('metadata', 'document_metadata.metadata_id', 'metadata.id')
                            ->select(
                                "documents.id", "document_metadata.metadata_id", 
                                "metadata.label", "document_metadata.value", 
                                "metadata.type", "metadata.created_at")
                            ->where("documents.id", $document->id)
                            ->orderBy("document_metadata.created_at", "DESC")
                            ->get();
            if ($metadatas) {
                $file['metadatas'] = $metadatas;
            } else {
                $file['metadatas'] = [];
            }
        }

        // if ACL ON
        if ($this->configRepository->getAcl()) {
            return $this->aclFilter($disk, [$file])[0];
        }

        return $file;
    }

    /**
     * Get properties for the selected directory
     *
     * @param       $disk
     * @param  null $path
     *
     * @return array|false
     */
    public function directoryProperties($disk, $path = null)
    {
        $directory = Storage::disk($disk)->getMetadata($path);

        $pathInfo = pathinfo($path);

        $pathInfo['uuid'] = Str::orderedUuid();

        /**
         * S3 didn't return metadata for directories
         */
        if (!$directory) {
            $directory['path'] = $path;
            $directory['type'] = 'dir';
        }

        $directory['basename'] = $pathInfo['basename'];
        $directory['dirname'] = $pathInfo['dirname'] === '.' ? ''
            : $pathInfo['dirname'];

        // if ACL ON
        if ($this->configRepository->getAcl()) {
            return $this->aclFilter($disk, [$directory])[0];
        }

        return $directory;
    }

    /**
     * Get only directories
     *
     * @param $content
     *
     * @return array
     */
    protected function filterDir($disk, $content)
    {
        // select only dir
        $dirsList = Arr::where($content, function ($item) {
            return $item['type'] === 'dir';
        });

        // remove 'filename' param
        $dirs = array_map(function ($item) {
            return Arr::except($item, ['filename']);
        }, $dirsList);

        // if ACL ON
        if ($this->configRepository->getAcl()) {
            return array_values($this->aclFilter($disk, $dirs));
        }

        return array_values($dirs);
    }

    /**
     * Get only files
     *
     * @param $disk
     * @param $content
     *
     * @return array
     */
    protected function filterFile($disk, $content)
    {
        // select only files
        $files = Arr::where($content, function ($item) {
            return $item['type'] === 'file';
        });

        // if ACL ON
        if ($this->configRepository->getAcl()) {
            return array_values($this->aclFilter($disk, $files));
        }

        return array_values($files);
    }

    /**
     * ACL filter
     *
     * @param $disk
     * @param $content
     *
     * @return mixed
     */
    protected function aclFilter($disk, $content)
    {
        $acl = resolve(ACL::class);

        $withAccess = array_map(function ($item) use ($acl, $disk) {
            // add acl access level
            $item['acl'] = $acl->getAccessLevel($disk, $item['path']);

            return $item;
        }, $content);

        // filter files and folders
        if ($this->configRepository->getAclHideFromFM()) {
            return array_filter($withAccess, function ($item) {
                return $item['acl'] !== 0;
            });
        }

        return $withAccess;
    }
}

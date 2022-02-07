<?php

namespace App\FileManager\Events;


class DiskSelected
{
    /**
     * @var string
     */
    private $disk;

    /**
     * DiskSelected constructor.
     *
     * @param $disk
     */
    public function __construct($disk)
    {
        $this->disk = $disk;
    }

    /**
     * @return string
     */
    public function disk()
    {
        return $this->disk;
    }
}

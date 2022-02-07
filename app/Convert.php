<?php

namespace App;

use Symfony\Component\Process\Process;
use Illuminate\Database\Eloquent\Model;

class Convert extends Model
{
    private const LIBREOFFICE = "libreoffice";
    private const HEADLESS = "--headless";
    private const CONVERT_TO = "--convert-to";
    private const OUTPUT_DIR = "--outdir";

    /**
     * Connvert file to another type
     * 
     * @param $convert_to
     * @param $path_file
     */
    
    public static function convert_to($convert_to, $path_file, $output_file) {
        $cmd = new Process([
                            self::LIBREOFFICE, 
                            self::HEADLESS,
                            self::CONVERT_TO,
                            $convert_to,
                            $path_file,
                            self::OUTPUT_DIR,
                            $output_file
                        ]); 
        return $cmd->run();

    }
}

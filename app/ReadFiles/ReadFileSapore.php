<?php

namespace App\ReadFiles;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\SplFileInfo;

class ReadFileSapore
{
    public function __construct(
        protected string $path,
        protected string $path_to,
        protected string $delimiter
    ) { }

    /**
     * Execute service
     *
     * @param callable $action
     * @return void
     **/
    public function handle(callable $action): void
    {
        $files = $this->getFiles($this->path);

        foreach($files as $file) {
            $lines = $this->getContentFile($file);

            $new_path = $this->path_to . '/'. date('Ymd-His') .'-'. $file->getFilename();
        
            foreach($lines as $item) {
                $data = explode($this->delimiter, $item);

                $action($data, $new_path);
            }

            $this->moveFile($file, $new_path);
        }
    }

    /**
     * Get files from path
     *
     * @param string $path
     * @return array
     **/
    public function getFiles(string $path): array
    {
        if(!Storage::directoryExists($path)) {
            Storage::createDirectory($path);
        }

        $path_storage = Storage::path($path);

        return (new Filesystem)->files($path_storage);
    }

    /**
     * Get validated line from file
     *
     * @param \Symfony\Component\Finder\SplFileInfo $file
     * @return array
     **/
    protected function getContentFile(SplFileInfo $file): array
    {
        $lines = explode("\r\n", $file->getContents());
        if(sizeof($lines) <= 1) {
            $lines = explode("\n", $file->getContents());
        }

        return array_filter($lines);
    }
    
    /**
     * Move file to path
     *
     * @param \Symfony\Component\Finder\SplFileInfo $file
     * @return void
     **/
    protected function moveFile(SplFileInfo $file, string $new_name): void
    {
        Storage::move(
            $this->path . '/' . $file->getFilename(),
            $new_name
        );
    }
}
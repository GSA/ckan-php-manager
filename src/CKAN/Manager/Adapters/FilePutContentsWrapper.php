<?php

declare( strict_types = 1 );

namespace CKAN\Manager\Adapters;

class FilePutContentsWrapper
{
    public function filePutContents($filename, $data, $flags = 0, $context = null)
    {
        return file_put_contents($filename, $data, $flags, $context);
    }
}
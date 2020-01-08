<?php

declare( strict_types = 1 );

namespace CKAN\Manager\Adapters;

class FileGetContentsWrapper
{
    public function fileGetContents($filename, $use_include_path = false, $context = null, $offset = 0, $maxlen = null)
    {
        return file_get_contents($filename, $use_include_path, $context, $offset, $maxlen);
    }
}

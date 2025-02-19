<?php

/*
 * CKFinder
 * ========
 * https://ckeditor.com/ckfinder/
 * Copyright (c) 2007-2021, CKSource - Frederico Knabben. All rights reserved.
 *
 * The software, this file and its contents are subject to the CKFinder
 * License. Please read the license.txt file before using, installing, copying,
 * modifying or distribute this file or part of its contents. The contents of
 * this file is part of the Source Code of CKFinder.
 */

namespace CKSource\CKFinder\Backend\Adapter;

use CKSource\CKFinder\CKFinder;
use CKSource\CKFinder\ContainerAwareInterface;
use CKSource\CKFinder\Operation\OperationManager;
use TSLeague\Flysystem\AwsS3v3\AwsS3Adapter;
use TSLeague\Flysystem\Util\MimeType;

/**
 * Custom adapter for AWS-S3.
 */
class AwsS3 extends AwsS3Adapter implements ContainerAwareInterface, EmulateRenameDirectoryInterface
{
    /**
     * The CKFinder application container.
     *
     * @var CKFinder
     */
    protected $app;

    public function setContainer(CKFinder $app)
    {
        $this->app = $app;
    }

    /**
     * Emulates changing of directory name.
     *
     * @param string $path
     * @param string $newPath
     *
     * @return bool
     */
    public function renameDirectory($path, $newPath)
    {
        $sourcePath = $this->applyPathPrefix(rtrim($path, '/').'/');

        $objectsIterator = $this->s3Client->getIterator('ListObjects', [
            'Bucket' => $this->bucket,
            'Prefix' => $sourcePath,
        ]);

        $objects = array_filter(iterator_to_array($objectsIterator), function ($v) {
            return isset($v['Key']);
        });

        if (!empty($objects)) {
            /** @var OperationManager $operation */
            $operation = $this->app['operation'];

            $operation->start();

            $total = \count($objects);
            $current = 0;

            foreach ($objects as $entry) {
                $this->s3Client->copyObject([
                    'Bucket' => $this->bucket,
                    'Key' => $this->replacePath($entry['Key'], $path, $newPath),
                    'CopySource' => urlencode($this->bucket.'/'.$entry['Key']),
                ]);

                if ($operation->isAborted()) {
                    // Delete target folder in case if operation was aborted
                    $targetPath = $this->applyPathPrefix(rtrim($newPath, '/').'/');

                    $this->s3Client->deleteMatchingObjects($this->bucket, $targetPath);

                    return true;
                }

                $operation->updateStatus(['total' => $total, 'current' => ++$current]);
            }

            $this->s3Client->deleteMatchingObjects($this->bucket, $sourcePath);
        }

        return true;
    }

    /**
     * Returns a direct link to a file stored on S3.
     *
     * @param string $path
     *
     * @return string
     */
    public function getFileUrl($path)
    {
        $objectPath = $this->applyPathPrefix($path);

        return $this->s3Client->getObjectUrl($this->bucket, $objectPath);
    }

    /**
     * Returns the file MIME type.
     *
     * @param string $path
     *
     * @return null|array|false|string
     */
    public function getMimeType($path)
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        $mimeType = MimeType::detectByFileExtension(strtolower($ext));

        return $mimeType ? ['mimetype' => $mimeType] : parent::getMimetype($path);
    }

    /**
     * Helper method that replaces a part of the key (path).
     *
     * @param string $objectPath the bucket-relative object path
     * @param string $path       the old backend-relative path
     * @param string $newPath    the new backend-relative path
     *
     * @return string the new bucket-relative path
     */
    protected function replacePath($objectPath, $path, $newPath)
    {
        $objectPath = $this->removePathPrefix($objectPath);
        $newPath = trim($newPath, '/').'/';
        $path = trim($path, '/').'/';

        return $this->applyPathPrefix($newPath.substr($objectPath, \strlen($path)));
    }
}

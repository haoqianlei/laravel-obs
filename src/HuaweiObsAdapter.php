<?php

namespace Back\LaravelObs;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;
use Back\LaravelObs\Plugins\Internal\Common\Model;
use Back\LaravelObs\Plugins\ObsClient;
use Back\LaravelObs\Plugins\ObsException;
use League\Flysystem\NotSupportedException;
use SplFileInfo;

class HuaweiObsAdapter extends AbstractAdapter
{
    protected $client;

    protected $bucket;

    /**
     * @param ObsClient $client
     * @param $bucket
     * @param null $prefix
     */
    public function __construct(ObsClient $client, $bucket, $prefix = null)
    {
        $this->client = $client;
        $this->bucket = $bucket;
        $this->setPathPrefix($prefix);
        $this->init();
    }

    /**
     * Init Obs log
     */
    public function init()
    {
        $this->client->initLog([
            'FilePath' => storage_path('logs'),
            'FileName' => 'OBS-SDK.log',
            'MaxFiles' => 10,
            'Level' => INFO
        ]);
    }

    /**
     * Get the OssClient bucket.
     *
     * @return string
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * Get the OssClient instance.
     *
     * @return ObsClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config
     * @return array|false
     */
    public function write($path, $contents, Config $config)
    {
        try {
            return $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $path,
                'Body' => $contents
            ])->toArray();
        } catch (ObsException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return false;
        }
    }

    /**
     * Write a new file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config
     * @return array|false
     */
    public function writeStream($path, $resource, Config $config)
    {
        try {
            return $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $path,
                'Body' => $resource
            ])->toArray();
        } catch (ObsException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return false;
        }
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config
     * @return array|false|Model
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * Update a file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config
     * @return array|false|Model
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->writeStream($path, $resource, $config);
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     * @return bool
     */
    public function rename($path, $newpath): bool
    {
        return $this->copy($path, $newpath) && $this->delete($path);
    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     * @return bool
     */
    public function copy($path, $newpath): bool
    {
        $bucket = $this->bucket;
        try {
            $this->client->copyObject([
                'Bucket' => $bucket,
                'Key' => $newpath,
                'CopySource' => $bucket . '/' . $path,
            ]);
        } catch (ObsException $e) {
            printf(__FUNCTION__ . ": FAILED\n");
            printf($e->getMessage() . "\n");
            return false;
        }
        return true;
    }

    /**
     * Delete a file.
     *
     * @param string $path
     * @return bool
     */
    public function delete($path): bool
    {
        $bucket = $this->bucket;

        try {
            $this->client->deleteObject([
                'Bucket' => $bucket,
                'Key' => $path
            ]);
        } catch (ObsException $e) {
            return false;
        }

        return true;
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     * @return bool
     */
    public function deleteDir($dirname): bool
    {
        return $this->delete($dirname);
    }

    /**
     * Create a directory.
     *
     * @param string $dirname
     * @param Config $config
     * @return false|string
     */
    public function createDir($dirname, Config $config)
    {
        $bucket = $this->bucket;

        try {
            $this->client->putObject([
                'Bucket' => $bucket,
                'Key' => $dirname
            ]);
        } catch (ObsException $e) {

            return false;
        }

        return $dirname;
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     * @return false|string
     */
    public function setVisibility($path, $visibility)
    {
        $bucket = $this->bucket;

        try {
            $this->client->setObjectAcl([
                'Bucket' => $bucket,
                'Key' => $path,
                'ACL' => $visibility === ObsClient::AclPrivate ?: ObsClient::AclPublicRead
            ]);
        } catch (ObsException $e) {
            return false;
        }
        return $path;
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     * @return bool
     */
    public function has($path): bool
    {
        return (bool)$this->getMetadata($path);
    }

    /**
     * Read a file.
     *
     * @param string $path
     * @return array|false
     */
    public function read($path)
    {
        $bucket = $this->bucket;
        try {
            return $this->client->getObject([
                'Bucket' => $bucket,
                'Key' => $path
            ])->toArray();
        } catch (ObsException $e) {
            return false;
        }
    }

    /**
     * Retrieves a read-stream for a path.
     *
     * @param string $path
     * @return array|false
     */
    public function readStream($path)
    {
        $bucket = $this->bucket;

        try {
            return $this->client->getObject([
                'Bucket' => $bucket,
                'Key' => $path,
                'SaveAsStream ' => true
            ])->toArray();
        } catch (ObsException $e) {
            return false;
        }
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param false $recursive
     * @return array|false
     */
    public function listContents($directory = '', $recursive = false)
    {
        $bucket = $this->bucket;

        try {

            $list = [];
            $directory = '/' === substr($directory, -1) ? $directory : $directory.'/';

            $result = $this->client->listObjects([
                'Bucket' => $bucket,
                'Prefix' => $directory,
                'Delimiter' => $directory
            ])->toArray();

            if (!empty($result['Contents'])) {
                foreach ($result['Contents'] as $files) {
                    if (!$fileInfo = $this->normalizeFileInfo($files)) {
                        continue;
                    }
                    $list[] = $fileInfo;
                }
            }

            // prefix
            if (!empty($result['CommonPrefixes'])) {
                foreach ($result['CommonPrefixes'] as $dir) {
                    $list[] = [
                        'type' => 'dir',
                        'path' => $dir['Prefix'],
                    ];
                }
            }
            return $list;
        } catch (ObsException $e) {
            return false;
        }
    }

    /**
     * format
     *
     * @param array $stats
     * @return array
     */
    protected function normalizeFileInfo(array $stats): array
    {
        $filePath = ltrim($stats['Key'], '/');

        $meta = $this->getMetadata($filePath) ?? [];

        if (empty($meta)) {

            return [];
        }

        return [
            'type' => 'file',
            'mimetype' => $meta['ContentType'],
            'path' => $filePath,
            'timestamp' => $meta['LastModified'],
            'size' => $meta['ContentLength'],
        ];
    }

    /**
     * Get a file's metadata.
     *
     * @param string $path
     * @return array|false
     */
    public function getMetadata($path)
    {
        $bucket = $this->bucket;
        try {
            return $this->client->getObjectMetadata([
                'Bucket' => $bucket,
                'Key' => $path
            ])->toArray();
        } catch (ObsException $e) {
            return false;
        }
    }

    /**
     * Get the size of a file.
     *
     * @param string $path
     * @return array|false|int|mixed|null
     */
    public function getSize($path)
    {
        $objectMetaData = $this->getMetadata($path);
        if ($objectMetaData) {
            return $objectMetaData['ContentLength'];
        }
        return 0;
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     * @return array|false|mixed|null
     */
    public function getMimetype($path)
    {
        $object = $this->getMetadata($path);
        return $object['ContentType'];
    }

    /**
     * Get the last modified time of a file as a timestamp.
     *
     * @param string $path
     * @return array|false|mixed|null
     */
    public function getTimestamp($path)
    {
        $object = $this->getMetadata($path);
        return $object['LastModified'];
    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     * @return array|false
     */
    public function getVisibility($path)
    {
        try {
            return $this->client->getObjectAcl([
                'Bucket' => $this->getBucket(),
                'Key' => $path
            ])->toArray();
        } catch (ObsException $e) {
            return false;
        }
    }
}

<?php

namespace Back\Obs;


use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;

class ObsAdapter extends AbstractAdapter
{
    /**
     * @var
     */
    protected  $endpoint;
    /**
     * @var
     */
    protected  $cdnDomain;
    /**
     * @var
     */
    protected  $ssl;
    /**
     * @var string
     */
    private string $bucket;
    /**
     * @var ObsClient
     */
    private ObsClient $client;

    /**
     * ObsAdapter constructor.
     *
     * @param  string  $bucket
     */
    public function __construct(ObsClient $client, string $bucket,  $endpoint,  $cdnDomain,  $ssl)
    {
        $this->client = $client;
        $this->bucket = $bucket;
        $this->endpoint = $endpoint;
        $this->cdnDomain = $cdnDomain;
        $this->ssl = $ssl;
    }

    /**
     * @param $path
     * @return string
     */
    public function getUrl($path)
    {
        return ($this->ssl ? 'https://' : 'http://')
            .($this->cdnDomain == '' ? $this->getBucket().'.'.$this->endpoint : $this->cdnDomain)
            .'/'.ltrim($path, '/');
    }

    /**
     * get bucket
     * @return string
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * Update a file.
     *
     * @param  string  $path
     * @param  string  $contents
     * @param  Config  $config
     * @return array|Internal\Common\Model|false
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * write a file.
     *
     * @param  string  $path
     * @param  string  $contents
     * @param  Config  $config
     * @return Internal\Common\Model|false
     */
    public function write($path, $contents, Config $config)
    {
        $path = $this->applyPathPrefix($path);

        try {
            return $this->client->putObject([
                'Bucket' => $this->getBucket(),
                'Key' => $path,
                'Body' => $contents
            ]);
        } catch (ObsException $e) {
            return false;
        }

    }

    /**
     * Update a file using a stream.
     *
     * @param  string  $path
     * @param  resource  $resource
     * @param  Config  $config  Config object
     *
     * @return false|Internal\Common\Model false on failure file meta data on success
     */

    public function updateStream($path, $resource, Config $config)
    {
        return $this->writeStream($path, $resource, $config);
    }

    /**
     * Write a new file using a stream.
     *
     * @param  string  $path
     * @param  resource  $resource
     * @param  Config  $config  Config object
     *
     * @return Internal\Common\Model|false
     */
    public function writeStream($path, $resource, Config $config)
    {
        $path = $this->applyPathPrefix($path);

        try {
            return $this->client->putObject([
                'Bucket' => $this->getBucket(),
                'Key' => $path,
                'Body' => $resource
            ]);
        } catch (ObsException $e) {
            return false;
        }
    }

    /**
     * Rename a file
     *
     * @param  string  $path
     * @param  string  $newpath
     * @return bool
     */
    public function rename($path, $newpath)
    {
        if ($this->copy($path, $newpath) && $this->delete($path)) {
            return true;
        }
        return false;
    }

    /**
     * Rename a file.
     *
     * @param  string  $path
     * @param  string  $newpath
     * @return Internal\Common\Model|bool
     */
    public function copy($path, $newpath)
    {
        $path = $this->applyPathPrefix($path);
        $newpath = $this->applyPathPrefix($newpath);

        try {
            return $this->client->deleteObject([
                'Bucket' => $this->getBucket(),
                'Key' => $newpath,
                'CopySource' => $this->getBucket().'/'.$path
            ]);
        } catch (ObsException $e) {
            return false;
        }
    }

    /**
     * Delete a file
     *
     * @param  string  $path
     * @return Internal\Common\Model|bool
     */
    public function delete($path)
    {
        $path = $this->applyPathPrefix($path);

        try {
            return $this->client->deleteObject([
                'Bucket' => $this->getBucket(),
                'Key' => $path
            ]);
        } catch (ObsException $e) {
            return false;
        }
    }

    /**
     * Delete a directory.
     *
     * @param  string  $dirname
     * @return Internal\Common\Model|bool
     */
    public function deleteDir($dirname)
    {
        return $this->delete($dirname);
    }

    /**
     * Create a directory
     *
     * @param  string  $dirname
     * @param  Config  $config
     * @return Internal\Common\Model|false
     */
    public function createDir($dirname, Config $config)
    {
        $path = $this->applyPathPrefix($dirname);

        try {
            return $this->client->putObject([
                'Bucket' => $this->getBucket(),
                'Key' => $path
            ]);
        } catch (ObsException $e) {
            return false;
        }
    }

    /**
     * Check whether a file exists.
     *
     * @param  string  $path
     * @return array|Internal\Common\Model|bool|null
     */
    public function has($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get all the meta data of a file or directory.
     * @param  string  $path
     * @return array|Internal\Common\Model|false
     */

    public function getMetadata($path)
    {
        $path = $this->applyPathPrefix($path);

        try {
            $object = $this->client->getObjectMetadata([
                'Bucket' => $this->getBucket(),
                'Key' => $path
            ]);
        } catch (ObsException $e) {
            return false;
        }

        return $object;
    }

    /**
     * Read a file.
     *
     * @param  string  $path
     * @return array|Internal\Common\Model|false
     */
    public function read($path)
    {
        $path = $this->applyPathPrefix($path);

        try {
            $object = $this->client->getObject([
                'Bucket' => $this->getBucket(),
                'Key' => $path
            ]);
        } catch (ObsException $e) {
            return false;
        }

        return $object;
    }

    /**
     * Read a file as a stream.
     *
     * @param  string  $path
     * @return Internal\Common\Model|false
     */
    public function readStream($path)
    {
        $path = $this->applyPathPrefix($path);

        try {
            $object = $this->client->getObject([
                'Bucket' => $this->getBucket(),
                'Key' => $path,
                'SaveAsStream' => true
            ]);
        } catch (ObsException $e) {
            return false;
        }

        return $object;
    }

    /**
     * List contents of a directory.
     *
     * @param  string  $directory
     * @param  false  $recursive
     * @return array|false
     */
    public function listContents($directory = '', $recursive = false)
    {
        $directory = $this->applyPathPrefix($directory);

        try {
            $object = $this->client->listObjects([
                'Bucket' => $this->getBucket(),
                'MaxKeys' => 1000,
                'Prefix' => $directory,
                'Marker' => null
            ]);
        } catch (ObsException $e) {
            return false;
        }

        if (!empty($object['Contents'])) {
            foreach ($object['Contents'] as $files) {
                if (!$fileInfo = $this->normalizeFileInfo($files)) {
                    continue;
                }
                $list[] = $fileInfo;
            }
        }

        return $list;
    }

    /**
     * fileinfo
     * @param  array  $stats
     * @return array
     */
    protected function normalizeFileInfo(array $stats)
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
     * Get the size of a file.
     * @param  string  $path
     * @return array|false|mixed|null
     */
    public function getSize($path)
    {
        $object = $this->getMetadata($path);
        return $object['ContentLength'];
    }

    /**
     * Get the mimetype of a file.
     * @param  string  $path
     * @return array|false|mixed|null
     */
    public function getMimetype($path)
    {
        $object = $this->getMetadata($path);
        return $object['ContentType'];
    }

    /**
     * Get the last modified time of a file as a timestamp.
     * @param  string  $path
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
     * @param  string  $path
     * @return array|Internal\Common\Model|false
     */
    public function getVisibility($path)
    {
        $path = $this->applyPathPrefix($path);

        try {
            return $this->client->getObjectAcl([
                'Bucket' => $this->getBucket(),
                'Key' => $path
            ]);
        } catch (ObsException $e) {
            return false;
        }
    }

    public function __destruct()
    {
        $this->client->close();
    }

    /**
     * Set the visibility for a file.
     *
     * @param  string  $path
     * @param  string  $visibility
     * @return Internal\Common\Model|false
     */
    public function setVisibility($path, $visibility)
    {
        $path = $this->applyPathPrefix($path);
        $acl = (AdapterInterface::VISIBILITY_PUBLIC === $visibility) ? ObsClient::AclPublicRead : ObsClient::AclPrivate;

        try {
            return $this->client->setObjectAcl([
                'Bucket' => $this->getBucket(),
                'Key' => $path,
                // 设置对象访问权限为私有读写
                'ACL' => $acl
            ]);
        } catch (ObsException $exception) {
            return false;
        }
    }
}

<?php

namespace Back\LaravelObs;

use Illuminate\Support\Carbon;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;
use Back\LaravelObs\Plugins\Internal\Common\Model;
use Back\LaravelObs\Plugins\ObsClient;
use Back\LaravelObs\Plugins\ObsException;

class HuaweiObsAdapter extends AbstractAdapter
{
    protected ObsClient $client;

    protected string $bucket;

    protected bool $useSSL;

    protected string $key;

    protected string $secret;

    protected string $endpoint;

    protected bool $ssl_verify;

    protected array $options;

    protected string $cdn_domain;

    /**
     * @param $key
     * @param $secret
     * @param $endpoint
     * @param $bucket
     * @param $sslVerify
     * @param $cdnDomain
     * @param array $options
     * @param null $prefix
     */
    public function __construct($key, $secret, $endpoint, $bucket, $sslVerify, $cdnDomain, array $options = [], $prefix = null)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->endpoint = $endpoint;
        $this->ssl_verify = $sslVerify;
        $this->bucket = $bucket;
        $this->cdn_domain = $cdnDomain;
        $this->options = $options;

        $this->setPathPrefix($prefix);
        $this->initClient();
        $this->checkEndpoint();
    }

    /**
     * Init Obs log
     */
    public function initClient(): void
    {
        $config = [
            'key' => $this->key,
            'secret' => $this->secret,
            'endpoint' => $this->endpoint,
            'ssl_verify' => $this->ssl_verify,
            'max_retry_count' => $this->max_retry_count ?? 3,
            'socket_timeout' => $this->socket_timeout ?? 60,
            'connect_timeout' => $this->connect_timeout ?? 60,
            'chunk_size' => $this->chunk_size ?? 65536,
        ];

        $this->client = new ObsClient(array_merge($config, $this->options));

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
                'Key' => $path,
                'VersionId' => ''
            ]);
        } catch (ObsException $e) {
            return false;
        }

        return !$this->has($path);
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     * @return bool
     */
    public function deleteDir($dirname): bool
    {
        $fileList = $this->listContents($dirname, true);
        foreach ($fileList as $file) {
            $this->delete($file['path']);
        }

        return !$this->has($dirname);
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
        $defaultFile = trim($dirname, '/') . '/obs.txt';

        try {
            $this->write($defaultFile, '当虚拟目录下有其他文件时，可删除此文件~', $config);
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
     * @return false
     */
    public function setVisibility($path, $visibility)
    {
        $bucket = $this->bucket;

        try {
            $this->client->setObjectAcl([
                'Bucket' => $bucket,
                'Key' => $path,
                'ACL' => $visibility === ObsClient::AclPrivate ? ObsClient::AclPrivate : ObsClient::AclPublicRead,
                'Delivered' => true,
            ])->toArray();
        } catch (ObsException $e) {
            return false;
        }
        return $visibility;
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
     * sign url.
     *
     * @param $path
     * @param $timeout
     *
     * @return array|bool
     */
    public function signUrl($path, $timeout, array $options = [])
    {
        $path = $this->applyPathPrefix($path);
        $bucket = $this->bucket;

        try {
            $options = array_merge([
                'Method' => 'GET',
                'Bucket' => $bucket,
                'Key' => $path,
                'Expires' => $timeout,
            ], $options);

            $signUrl = $this->client->createSignedUrl($options)->get('SignedUrl');
        } catch (OBSException $exception) {
            return false;
        }

        return $signUrl;
    }

    /**
     * temporary file url.
     *
     * @param $path
     * @param $expiration
     * @param array $options
     */
    public function getTemporaryUrl($path, $expiration, array $options = [])
    {
        return $this->signUrl($path, Carbon::now()->diffInSeconds($expiration), $options);
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
            $object = $this->client->getObject([
                'Bucket' => $bucket,
                'Key' => $path,
            ])->toArray();

            $object['Key'] = $path;
            $object['contents'] = (string)$object['Body'];
            unset($object['Body']);

            return array_merge($this->normalizeResponse($object), $object);
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
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, $this->client->getObject([
                'Bucket' => $bucket,
                'Key' => $path,
                'SaveAsStream ' => true
            ])['Body']);
            rewind($stream);
        } catch (ObsException $e) {
            return false;
        }

        return compact('stream', 'path');
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
        try {

            $list = [];
            $directory = '/' === substr($directory, -1) ? $directory : $directory . '/';
            $result = $this->listDirObjects($directory, $recursive);

            if (!empty($result['objects'])) {
                foreach ($result['objects'] as $files) {
                    if (!$fileInfo = $this->normalizeFileInfo($files)) {
                        continue;
                    }
                    $list[] = $fileInfo;
                }
            }

            // prefix
            if (!empty($result['prefix'])) {
                foreach ($result['prefix'] as $dir) {
                    $list[] = [
                        'type' => 'dir',
                        'path' => $dir,
                    ];
                }
            }
        } catch (ObsException $e) {
            return false;
        }

        return $list;
    }

    /**
     * File list core method.
     *
     * @param string $dirname
     * @param false $recursive
     * @return array
     */
    protected function listDirObjects(string $dirname = '', bool $recursive = false): array
    {
        $delimiter = '/';
        $nextMarker = '';
        $maxkeys = 1000;
        $bucket = $this->bucket;
        $result = [];

        while (true) {
            $options = [
                'MaxKeys' => $maxkeys,
                'Marker' => $nextMarker,
                'Bucket' => $bucket,
                'Delimiter' => $delimiter
            ];

            if (!empty($dirname)) {
                $options['Prefix'] = $dirname;
            }

            $listObjectInfo = $this->client->listObjects($options)->toArray();
            $nextMarker = $listObjectInfo['NextMarker'];
            $objectList = $listObjectInfo['Contents'];
            $prefixList = $listObjectInfo['CommonPrefixes'];
            if (!empty($objectList)) {
                foreach ($objectList as $objectInfo) {
                    $object['Prefix'] = $dirname;
                    $object['Key'] = $objectInfo['Key'] ?? "";
                    $object['LastModified'] = $objectInfo['LastModified'] ?? "";
                    $object['ETag'] = $objectInfo['ETag'] ?? "";
                    $object['Type'] = $objectInfo['Type'] ?? "";
                    $object['Size'] = $objectInfo['Size'] ?? "";
                    $object['StorageClass'] = $objectInfo['StorageClass'] ?? "";
                    $result['objects'][] = $object;
                }
            } else {
                $result['objects'] = [];
            }

            if (!empty($prefixList)) {
                foreach ($prefixList as $prefixInfo) {
                    $result['prefix'][] = $prefixInfo['Prefix'];
                }
            } else {
                $result['prefix'] = [];
            }

            // Recursive directory
            if ($recursive) {
                foreach ($result['prefix'] as $prefix) {
                    $next = $this->listDirObjects($prefix, $recursive);
                    $result['objects'] = array_merge($result['objects'], $next['objects']);
                }
            }

            if ('' === $nextMarker) {
                break;
            }
        }

        return $result;
    }

    /**
     * Check the endpoint to see if SSL can be used.
     */
    protected function checkEndpoint()
    {
        if (0 === strpos($this->endpoint, 'http://')) {
            $this->endpoint = substr($this->endpoint, strlen('http://'));
            $this->ssl_verify = false;
        } elseif (0 === strpos($this->endpoint, 'https://')) {
            $this->endpoint = substr($this->endpoint, strlen('https://'));
            $this->ssl_verify = true;
        }
    }

    /**
     * Get resource url.
     *
     * @param string $path
     *
     * @return string
     */
    public function getUrl($path): string
    {

        $path = $this->applyPathPrefix($path);

        if ($this->cdn_domain) {
            return rtrim($this->cdn_domain, '/') . '/' . ltrim($path, '/');
        }

        return $this->normalizeHost() . ltrim($path, '/');
    }

    /**
     * normalize Host.
     *
     * @return string
     */
    protected function normalizeHost()
    {
        if ($this->cdn_domain) {
            $domain = $this->cdn_domain;
        } else {
            $domain = $this->bucket . '.' . $this->endpoint;
        }

        if ($this->ssl_verify) {
            $domain = "https://{$domain}";
        } else {
            $domain = "http://{$domain}";
        }

        return rtrim($domain, '/') . '/';
    }

    /**
     * @param $object
     * @return array
     */
    public function normalizeResponse($object): array
    {
        $path = ltrim($this->removePathPrefix($object['Key']), '/');

        $result = ['path' => $path];

        if (isset($object['LastModified'])) {
            $result['timestamp'] = strtotime($object['LastModified']);
        }

        if (isset($object['ContentLength'])) {
            $result['size'] = $object['ContentLength'];
            $result['bytes'] = $object['ContentLength'];
        }

        $type = (substr($result['path'], -1) === '/' ? 'dir' : 'file');

        $result['type'] = $type;


        return $result;
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
            'timestamp' => strtotime($meta['LastModified']),
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
            $objectMetaData['Key'] = $path;
            return array_merge($this->normalizeResponse($objectMetaData), $objectMetaData);
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
        $object['Key'] = $path;
        return array_merge($this->normalizeResponse($object), $object);
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
        return strtotime($object['LastModified']);
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

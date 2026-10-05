<?php

namespace App\Support\Filesystem;

use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;

/**
 * Presigned URLs embed the host in their signature. When PHP reaches S3 through an internal
 * hostname (e.g. `s3:9000` inside Docker), sign them against the browser-facing endpoint instead.
 */
class PublicPresigningS3Adapter extends AwsS3V3Adapter
{
    private ?S3Client $publicClient = null;

    /**
     * @param  string  $path
     * @param  \DateTimeInterface  $expiration
     * @param  array<string, mixed>  $options
     * @return string
     */
    public function temporaryUrl($path, $expiration, array $options = [])
    {
        $client = $this->publicClient();

        $command = $client->getCommand('GetObject', array_merge([
            'Bucket' => $this->config['bucket'],
            'Key' => $this->prefixer->prefixPath($path),
        ], $options));

        return (string) $client->createPresignedRequest($command, $expiration, $options)->getUri();
    }

    private function publicClient(): S3Client
    {
        return $this->publicClient ??= new S3Client([
            'version' => 'latest',
            'region' => $this->config['region'],
            'endpoint' => $this->config['public_endpoint'],
            'use_path_style_endpoint' => (bool) ($this->config['use_path_style_endpoint'] ?? false),
            'credentials' => $this->client->getCredentials()->wait(),
        ]);
    }
}

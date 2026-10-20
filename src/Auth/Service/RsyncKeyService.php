<?php

namespace App\Auth\Service;

class RsyncKeyService
{
    private string $keyBasePath;

    public function __construct(string $projectDir)
    {
        $this->keyBasePath = $projectDir . '/var/orgs';
    }

    public function generateKeyPair(string $orgId): array
    {
        $orgPath = "{$this->keyBasePath}/{$orgId}/private-keys";
        if (!is_dir($orgPath)) {
            mkdir($orgPath, 0770, true);
        }

        $keyId = uniqid("key_", true);
        $keyBasePath = "$orgPath/{$keyId}";

        $cmd = sprintf(
            'ssh-keygen -t rsa -b 4096 -N "" -f %s -q',
            escapeshellarg($keyBasePath)
        );
        exec($cmd, $output, $returnCode);

        $privateKeyPath = $keyBasePath;
        $publicKeyPath  = $keyBasePath . '.pub';

        if ($returnCode !== 0 || !file_exists($privateKeyPath) || !file_exists($publicKeyPath)) {
            throw new \RuntimeException("SSH key generation failed for the organization: $orgId.");
        }

        return [
            'private' => file_get_contents($privateKeyPath),
            'public'  => file_get_contents($publicKeyPath),
            'id'      => $keyId,
        ];
    }
}
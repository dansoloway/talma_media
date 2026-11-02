<?php

declare(strict_types=1);

namespace App\Services\GoogleDrive;

use Google\Client as GoogleClient;
use Google\Service\Drive;
use InvalidArgumentException;
use RuntimeException;

final class GoogleDriveClientFactory
{
    public function make(): Drive
    {
        $raw = (string) config('services.google.service_account_json');

        if ($raw === '') {
            throw new InvalidArgumentException(
                'GOOGLE_SERVICE_ACCOUNT_JSON is not set. Add the base64-encoded service account key to .env.'
            );
        }

        $json = $this->decodeServiceAccountJson($raw);

        $client = new GoogleClient;
        $client->setAuthConfig($json);
        $client->setApplicationName((string) config('app.name'));
        $client->setScopes([Drive::DRIVE_READONLY]);

        return new Drive($client);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeServiceAccountJson(string $raw): array
    {
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            $fromBase64 = base64_decode($raw, true);

            if ($fromBase64 !== false) {
                $decoded = json_decode($fromBase64, true);
            }
        }

        if (! is_array($decoded) || ! isset($decoded['client_email'], $decoded['private_key'])) {
            throw new RuntimeException('GOOGLE_SERVICE_ACCOUNT_JSON is not valid service-account JSON or base64 JSON.');
        }

        return $decoded;
    }
}

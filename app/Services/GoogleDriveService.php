<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;

class GoogleDriveService
{
    protected Drive $drive;

    public function __construct()
    {
        $client = new Client();

        $client->setAuthConfig(
            storage_path('app/google/drive-key.json')
        );

        $client->addScope(Drive::DRIVE_READONLY);

        $this->drive = new Drive($client);
    }

    public function getDrive(): Drive
    {
        return $this->drive;
    }

    public function getAbout()
    {
        return $this->drive->about->get([
            'fields' => 'user'
        ]);
    }

    public function getFolder(string $folderId)
    {
        return $this->drive->files->get(
            $folderId,
            [
                'fields' => 'id,name,mimeType,webViewLink,parents',
                'supportsAllDrives' => true,
            ]
        );
    }
}

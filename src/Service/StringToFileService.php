<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class StringToFileService
{
    /**
     * Creates a temporary UploadedFile object from a string content.  The file object is then
     * used by Doctrine and Vich to save the file to proper location and save entity properties.
     */
    public function createUploadedFileFromString(string $content, string $fileExtension): UploadedFile
    {
        $tempPath = sys_get_temp_dir() . '/' . uniqid('form_text_', true) . '.' . $fileExtension;
        file_put_contents($tempPath, $content);

        $originalFilename = 'pasted_text.' . $fileExtension;

        // Create a "mock" UploadedFile by using the test mode ($test = true).
        // This makes it behave like a real uploaded file and work with VichUploaderBundle.
        return new UploadedFile(
            path: $tempPath,
            originalName: $originalFilename,
            mimeType: 'text/plain',
            error: null,
            test: true // Important for VichUploaderBundle
        );
    }
}

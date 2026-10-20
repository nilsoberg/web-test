<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

trait FilenameTrait
{

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $uploadedFilename = null;

    /**
     * This is not a persisted field. It's used to temporary store the uploaded file.
     * The `mapping` value refers to the key in the vich_uploader.yaml config.
     * The `fileNameProperty` points to the field that will store the generated filename.
     **/
    #[Vich\UploadableField(mapping: "job_files", fileNameProperty: "jobFilename")]
    private ?File $jobFile = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $jobFilename = null;

    /**
     * This is a virtual field used to update other fields when the file is set.
     * It's important to set this so that Doctrine knows the entity has changed
     * and needs to be persisted.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getJobFile(): ?File
    {
        return $this->jobFile;
    }

    /**
     * If manually uploading a file (i.e. not using a form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the  update. This
     * happens when a user uploads text instead of a file and the factory creates
     * a file object to store the text.
     *
     * If this bundle's configuration parameter 'inject_on_load' is set to 'true' this
     * setter must be able to accept an instance of 'File' as the bundle will inject
     * one here during Doctrine hydration.
     *
     * @param File|\Symfony\Component\HttpFoundation\File\UploadedFile|null $jobFile
     */
    public function setJobFile(?File $jobFile): static
    {
        $this->jobFile = $jobFile;

        if ($jobFile !== null) {
            // It is required that at least one field changes if you are using Doctrine,
            // otherwise the event listeners won't be called and the file is lost.
            $this->updatedAt = new \DateTimeImmutable();

            // Store the original file name
            if ($jobFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $this->setUploadedFilename($jobFile->getClientOriginalName());
            }
        }

        return $this;
    }

    public function getJobFilename(): ?string
    {
        return $this->jobFilename;
    }

    public function setJobFilename(?string $jobFilename): static
    {
        $this->jobFilename = $jobFilename;

		return $this;
    }

    public function getUploadedFilename(): ?string
    {
        return $this->uploadedFilename;
    }

    public function setUploadedFilename(?string $uploadedFilename): static
    {
        $this->uploadedFilename = $uploadedFilename;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Virtual property used by the JobSummaryService.
     */
    public function getFormattedUploadedFilename(): string
    {
        if ($this->uploadedFilename !== null && str_starts_with($this->uploadedFilename, 'pasted_text.')) {
            return 'Uploaded text';
        } else {
            return $this->uploadedFilename ?? '';
        }
    }
}

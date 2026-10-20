<?php

namespace App\Vich;

use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\NamerInterface;

/**
 * Class JobIdNamer
 *
 * Names a file using the associated Job entity's ID.
 * Example: if the Job ID is 1234 and the file is a .txt, the name will be "1234.txt".
 */
class JobIdNamer implements NamerInterface
{
    /**
     * @param object          $object  The object the upload is attached to (an entity with the FilenameTrait trait)
     * @param PropertyMapping $mapping The mapping is an Uploader-managed property
     *
     * @return string The file name
     */
    public function name($object, PropertyMapping $mapping): string
    {
        // Ensure we are working with an entity that supports file uploading, using duck typing
        if (!method_exists($object, 'getId')) {
            throw new \InvalidArgumentException(sprintf('The object passed to JobIdNamer must have a public "getId()" method. The class "%s" does not.', get_class($object)));
        }

        $file = $mapping->getFile($object);

		$extension = "txt";
		if ($file && method_exists($file, 'getClientOriginalExtension')) {
            $extension = $file->getClientOriginalExtension();
        }

        // Use a fallback if the ID is not yet available (on initial upload)
        $id = $object->getId() ?? uniqid();

        $suffix = '';
        if (strtolower($extension) === 'zip') {
            $suffix = '_ssn';
        }

        // The bundle will rename the file with the correct ID after persistence.
        return sprintf('%s%s.%s', $id, $suffix, $extension);
    }
}


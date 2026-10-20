<?php

namespace App\Service;

use App\Auth\Repository\OrganizationRepository;

/**
 * A service to generate a unique, "stock ticker" style ID for an Organization.
 *
 * This service creates a short, memorable ID based on the words in the
 * organization's name. It then queries the database to ensure the generated ID
 * is unique, appending a numerical suffix if a collision is found.
 */
class OrganizationIdGenerator
{
    /**
     * A list of common words to ignore when generating the ID.
     */
    private const STOP_WORDS = ['THE', 'A', 'AN', 'OF', 'AND', 'FOR', 'IN', 'ON', 'CO', 'LLC', 'INC', 'LTD', 'COMPANY', 'CORPORATION', 'GROUP'];

    /**
     * The constructor injects the necessary dependencies.
     *
     * @param OrganizationRepository $organizationRepository The repository to check for existing IDs.
     */
    public function __construct(
        private readonly OrganizationRepository $organizationRepository
    ) {
    }

    /**
     * Generates a unique, short ID for an organization in a "stock ticker" style.
     *
     * @param string $organizationName The name of the organization.
     * @return string The unique, short organization ID.
     */
    public function generate(string $organizationName): string
    {
        // 1. Pre-process the name: uppercase, extract digits, and clean.
        $nameUpper = strtoupper(trim($organizationName));
        
        preg_match_all('/\d+/', $nameUpper, $matches);
        $digitsPart = implode('', $matches[0]);
        
        // Remove punctuation, digits, and extra spaces
        $cleanName = preg_replace('/[^\w\s]/', '', $nameUpper);
        $cleanName = preg_replace('/\d+/', '', $cleanName);
        $cleanName = preg_replace('/\s+/', ' ', $cleanName);

        // 2. Tokenize the name into words and filter out stop words.
        $allWords = explode(' ', $cleanName);
        $significantWords = array_diff($allWords, self::STOP_WORDS);
        $significantWords = array_values(array_filter($significantWords)); // Re-index array

        if (empty($significantWords)) {
            $significantWords = $allWords; // Fallback to all words if filtering left nothing
        }
        
        // 3. Build the ticker based on the number of significant words.
        $ticker = '';
        $wordCount = count($significantWords);
        
        if ($wordCount === 1) {
            // "Avengers" -> "AVE"
            $ticker = substr($significantWords[0], 0, 3);
        } elseif ($wordCount === 2) {
            // "Helix Biosciences" -> "HBI"
            $ticker = substr($significantWords[0], 0, 1) . substr($significantWords[1], 0, 2);
        } elseif ($wordCount === 3) {
            // "Kryptonian Fluid Dynamics" -> "KFD"
            $ticker = substr($significantWords[0], 0, 1) . substr($significantWords[1], 0, 1) . substr($significantWords[2], 0, 1);
        } elseif ($wordCount >= 4) {
            // "North Atlantic Treaty Organization" -> "NATO"
            $ticker = substr($significantWords[0], 0, 1) . substr($significantWords[1], 0, 1) . substr($significantWords[2], 0, 1) . substr($significantWords[3], 0, 1);
        }

        // Handle cases where the name is too short or empty
        if (empty($ticker)) {
             $ticker = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 3);
        }

        // 4. Combine the ticker and digits to form the base ID.
        $baseId = $ticker . $digitsPart;
        
        // 5. Check for uniqueness and append a suffix if necessary.
        $id = $baseId;
        $counter = 2;
        while ($this->organizationRepository->findOneBy(['id' => $id])) {
            $id = $baseId . '-' . $counter;
            $counter++;
        }

        return $id;
    }
}

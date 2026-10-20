<?php

namespace App\Auth\Service;

class TemporaryPasswordGenerator
{
    private const LOWERCASE = 'abcdefghjkmnpqrstuvwxyz';
    private const UPPERCASE = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const DIGITS = '23456789';
    private const SYMBOLS = '!@#$%^&*()-_=+';

    public function __construct(
        private readonly int $length = 16,
    ) {
    }

    public function generate(): string
    {
        $requiredSets = [
            self::LOWERCASE,
            self::UPPERCASE,
            self::DIGITS,
            self::SYMBOLS,
        ];

        $passwordChars = [];
        foreach ($requiredSets as $characterSet) {
            $passwordChars[] = $this->randomCharacter($characterSet);
        }

        $allCharacters = implode('', $requiredSets);
        $remainingLength = max($this->length - count($passwordChars), 0);
        for ($i = 0; $i < $remainingLength; $i++) {
            $passwordChars[] = $this->randomCharacter($allCharacters);
        }

        $this->secureShuffle($passwordChars);
        $this->ensureLeadingCharacterIsNotSymbol($passwordChars);

        return implode('', $passwordChars);
    }

    private function randomCharacter(string $characterSet): string
    {
        $index = random_int(0, strlen($characterSet) - 1);

        return $characterSet[$index];
    }

    /**
     * Fisher-Yates shuffle using cryptographically secure randomness.
     *
     * @param string[] $items
     */
    private function secureShuffle(array &$items): void
    {
        $count = count($items);
        for ($i = $count - 1; $i > 0; $i--) {
            $swapIndex = random_int(0, $i);
            [$items[$i], $items[$swapIndex]] = [$items[$swapIndex], $items[$i]];
        }
    }

    /**
     * Keep the first character alphanumeric to reduce user confusion while preserving
     * symbol requirements elsewhere in the generated password.
     *
     * @param string[] $passwordChars
     */
    private function ensureLeadingCharacterIsNotSymbol(array &$passwordChars): void
    {
        if ($passwordChars === [] || !$this->isSymbol($passwordChars[0])) {
            return;
        }

        $totalChars = count($passwordChars);
        for ($i = 1; $i < $totalChars; $i++) {
            if (!$this->isSymbol($passwordChars[$i])) {
                [$passwordChars[0], $passwordChars[$i]] = [$passwordChars[$i], $passwordChars[0]];

                return;
            }
        }
    }

    private function isSymbol(string $character): bool
    {
        return str_contains(self::SYMBOLS, $character);
    }
}

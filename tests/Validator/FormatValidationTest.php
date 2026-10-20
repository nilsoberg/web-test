<?php

namespace App\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Regex;

class FormatValidationTest extends TestCase
{
    /**
     * @dataProvider inputProvider
     */
    public function testConsensusResidueValidation(string|null $input, bool $expectedValid)
    {
        $validator = Validation::createValidator();

        $constraint = new Regex(pattern: '/^([A-Z](,[A-Z])*|)$/i');

        $violations = $validator->validate($input, $constraint);

        if ($expectedValid) {
            $this->assertEmpty($violations, "Input '$input' should be valid.");
        } else {
            $this->assertNotEmpty($violations, "Input '$input' should be invalid.");
        }
    }

    public function inputProvider(): array
    {
        return [
            // Success Cases
            'Null value'           => [null, true],
            'Empty string'         => ['', true],
            'Single letter'        => ['A', true],
            'Lowercase letter'     => ['a', true],
            'Multiple letters'     => ['A,B,C,Z', true],
            'Mixed case'           => ['a,B,c', true],

            // Failure Cases
            'Trailing comma'       => ['A,', false],
            'Leading comma'        => [',A', false],
            'Missing commas'       => ['AB', false],
            'Double comma'         => ['A,,B', false],
            'Special characters'   => ['A,B,!', false],
            'Numbers'              => ['A,1,B', false],
            'Spaces included'      => ['A, B', false],
        ];
    }
}

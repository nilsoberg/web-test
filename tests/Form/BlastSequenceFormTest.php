<?php

namespace App\Tests\Form;

use App\Form\Partial\BlastSequenceTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class BlastSequenceFormTest extends TypeTestCase
{
    // We need to register the Validator extension to test constraints
    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();
        return [
            new ValidatorExtension($validator),
        ];
    }

    // Valid FASTA format
    public function testValidBlastSequenceHeader(): void
    {
        $validData = ">Header loki@42_.\nMKAILV";

        $form = $this->factory->create(TestBlastForm::class);
        $form->submit(['blastSequence' => $validData]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), 'Form should be valid for correct FASTA input');
    }

    // Invalid FASTA format
    public function testInvalidBlastSequenceBodyWithHeader(): void
    {
        $invalidData = ">Header loki@42_.\nMKAILV42";

        $form = $this->factory->create(TestBlastForm::class);
        $form->submit(['blastSequence' => $invalidData]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid(), 'Form should be valid for correct FASTA input');

        // Verify the specific error message matches your Trait
        $errors = $form->get('blastSequence')->getErrors();
        $this->assertGreaterThan(0, count($errors));
        $this->assertEquals(
            "The sequence can only contain amino acid codes (letters, gaps). Numbers and symbols are only allowed in the header line (starting with '>').",
            $errors[0]->getMessage()
        );
    }

    // Invalid data containing numbers
    public function testInvalidBlastSequenceBodyNumbers(): void
    {
        $invalidData = "MKAILV123"; 

        $form = $this->factory->create(TestBlastForm::class);
        $form->submit(['blastSequence' => $invalidData]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid(), 'Form should be invalid when input contains numbers');

        // Verify the specific error message matches your Trait
        $errors = $form->get('blastSequence')->getErrors();
        $this->assertGreaterThan(0, count($errors));
        $this->assertEquals(
            "The sequence can only contain amino acid codes (letters, gaps). Numbers and symbols are only allowed in the header line (starting with '>').",
            $errors[0]->getMessage()
        );
    }

    // Invalid data containing numbers
    public function testInvalidBlastSequenceBodySymbol(): void
    {
        $invalidData = "MKAILV*A-.D"; 

        $form = $this->factory->create(TestBlastForm::class);
        $form->submit(['blastSequence' => $invalidData]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid(), 'Form should be invalid when input contains numbers');

        // Verify the specific error message matches your Trait
        $errors = $form->get('blastSequence')->getErrors();
        $this->assertGreaterThan(0, count($errors));
        $this->assertEquals(
            "The sequence can only contain amino acid codes (letters, gaps). Numbers and symbols are only allowed in the header line (starting with '>').",
            $errors[0]->getMessage()
        );
    }

    public function testBlankIsInvalid(): void
    {
        $form = $this->factory->create(TestBlastForm::class);
        $form->submit(['blastSequence' => '']);

        $this->assertFalse($form->isValid());
        $this->assertEquals('Please enter a BLAST sequence.', $form->get('blastSequence')->getErrors()[0]->getMessage());
    }
}

// Helper class for testing trait
class TestBlastForm extends AbstractType
{
    use BlastSequenceTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addBlastSequenceField($builder, $options);
    }
}

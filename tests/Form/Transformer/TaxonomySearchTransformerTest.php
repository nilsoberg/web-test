<?php

namespace App\Tests\Form\Transformer;

use App\Form\Transformer\TaxonomySearchTransformer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;

class TaxonomySearchTransformerTest extends TestCase
{
    public function testTransformDecodesJson(): void
    {
        $transformer = new TaxonomySearchTransformer();
        $json = '{"bacteria": "123", "fungi": "456"}';
        
        $result = $transformer->transform($json);
        
        $this->assertEquals(['bacteria' => '123', 'fungi' => '456'], $result);
    }

    public function testTransformThrowsExceptionOnBadJson(): void
    {
        $this->expectException(TransformationFailedException::class);
        
        $transformer = new TaxonomySearchTransformer();
        $transformer->transform('{bad_json: ...');
    }

    public function testReverseTransformEncodesArray(): void
    {
        $transformer = new TaxonomySearchTransformer();
        $data = ['bacteria' => '123'];
        
        $this->assertEquals('{"bacteria":"123"}', $transformer->reverseTransform($data));
    }
}

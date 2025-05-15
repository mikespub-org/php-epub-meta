<?php

use PHPUnit\Framework\TestCase;
use SebLucas\EPubMeta\Metadata;

/**
 * Test for Metadata class
 */
class MetadataTest extends TestCase
{
    public const TEST_FILE = __DIR__ . '/data/metadata.opf';

    public function testFromFile(): void
    {
        $metadata = Metadata::fromFile(self::TEST_FILE);
        $expected = Metadata::class;
        $this->assertInstanceOf($expected, $metadata);
        $expected = "2.0";
        $this->assertEquals($expected, $metadata->version);
        $expected = 24;
        $this->assertCount($expected, $metadata->metadata);
        $identifiers = $metadata->getIdentifiers();
        $expected = 2;
        $this->assertCount($expected, $identifiers);
        $expected = [
            'scheme' => 'calibre',
            'id' => 'calibre_id',
            'value' => '17',
        ];
        $this->assertEquals($expected, $identifiers[0]);
        $annotations = $metadata->getAnnotations();
        $expected = 5;
        $this->assertCount($expected, $annotations);
        $expected = "bookmark";
        $this->assertEquals($expected, $annotations[0]["annotation"]["type"]);
        $expected = "About #1";
        $this->assertEquals($expected, $annotations[0]["annotation"]["title"]);
    }

    public function testGetElement(): void
    {
        $element = "dc:title";
        $metadata = Metadata::fromFile(self::TEST_FILE);

        $result = $metadata->getElement($element);
        $expected = 1;
        $this->assertCount($expected, $result);
        $expected = "Alice's Adventures in Wonderland";
        $this->assertEquals($expected, $result[0]);
    }

    public function testGetElementName(): void
    {
        $element = "meta";
        $name = "calibre:annotation";
        $metadata = Metadata::fromFile(self::TEST_FILE);

        $result = $metadata->getElementName($element, $name);
        $expected = 5;
        $this->assertCount($expected, $result);
        $expected = [
            'format' => 'EPUB',
            'user_type' => 'local',
            'user' => 'viewer',
            'annotation' => [
                'title' => 'About #1',
                'pos_type' => 'epubcfi',
                'pos' => 'epubcfi(/6/2/4/2/6/2:38)',
                'timestamp' => '2024-03-11T11:54:35.128396+00:00',
                'type' => 'bookmark',
            ],
        ];
        $this->assertEquals($expected, $result[0]);
    }

    public function testGetIdentifiers(): void
    {
        $metadata = Metadata::fromFile(self::TEST_FILE);

        $result = $metadata->getIdentifiers();
        $expected = 2;
        $this->assertCount($expected, $result);
        $expected = [
            'scheme' => 'calibre',
            'id' => 'calibre_id',
            'value' => '17',
        ];
        $this->assertEquals($expected, $result[0]);
    }

    public function testGetAnnotations(): void
    {
        $metadata = Metadata::fromFile(self::TEST_FILE);

        $result = $metadata->getAnnotations();
        $expected = 5;
        $this->assertCount($expected, $result);
        $expected = [
            'format' => 'EPUB',
            'user_type' => 'local',
            'user' => 'viewer',
            'annotation' => [
                'title' => 'About #1',
                'pos_type' => 'epubcfi',
                'pos' => 'epubcfi(/6/2/4/2/6/2:38)',
                'timestamp' => '2024-03-11T11:54:35.128396+00:00',
                'type' => 'bookmark',
            ],
        ];
        $this->assertEquals($expected, $result[0]);
    }
}

<?php

use PHPUnit\Framework\TestCase;
use SebLucas\EPubMeta\ComicInfo;

/**
 * Test for ComicInfo class
 */
class ComicInfoTest extends TestCase
{
    public const COMIC_INFO_XML = __DIR__ . '/data/ComicInfo.xml';

    public function testParseData(): void
    {
        $xml = file_get_contents(self::COMIC_INFO_XML);
        $comicInfo = ComicInfo::parseData($xml);

        $this->assertEquals('You Had One Job', $comicInfo->getTitle());
        $this->assertEquals('Fantastic Four', $comicInfo->getSeries());
        $this->assertEquals('22', $comicInfo->getNumber());
        $this->assertEquals('Dan Slott', $comicInfo->getWriter());
        $this->assertEquals('Marvel', $comicInfo->getPublisher());
        $this->assertEquals('en', $comicInfo->getLanguageISO());
        //$this->assertEquals('Tragedy, Action, Sci-Fi', $comicInfo->getTags());
        //$this->assertEquals('978-1302913537', $comicInfo->getGTIN());

        $pages = $comicInfo->getPages();
        $this->assertCount(24, $pages);
        $this->assertEquals('0', $pages[0]['Image']);
        $this->assertEquals('FrontCover', $pages[0]['Type']);
    }

    public function testToXML(): void
    {
        $comicInfo = new ComicInfo();
        $comicInfo->setTitle('Test Title');
        $comicInfo->setSeries('Test Series');
        $comicInfo->setWriter('Test Writer');
        $comicInfo->setPages([
            ['Image' => '0', 'Type' => 'FrontCover'],
            ['Image' => '1'],
        ]);

        $xmlString = $comicInfo->toXML();

        $this->assertStringContainsString('<Title>Test Title</Title>', $xmlString);
        $this->assertStringContainsString('<Series>Test Series</Series>', $xmlString);
        $this->assertStringContainsString('<Writer>Test Writer</Writer>', $xmlString);
        $this->assertStringContainsString('<Page Image="0" Type="FrontCover"/>', $xmlString);
        $this->assertStringContainsString('<Page Image="1"/>', $xmlString);

        // Test that empty fields are not included
        $this->assertStringNotContainsString('<Number>', $xmlString);
    }

    public function testSettersAndGetters(): void
    {
        $comicInfo = new ComicInfo();

        $comicInfo->setTitle('My Comic');
        $this->assertEquals('My Comic', $comicInfo->getTitle());

        $comicInfo->setSeries('My Series');
        $this->assertEquals('My Series', $comicInfo->getSeries());

        $comicInfo->setNumber('1');
        $this->assertEquals('1', $comicInfo->getNumber());
    }

    public function testCallMagicMethod(): void
    {
        $comicInfo = new ComicInfo();
        $comicInfo->__call('setCharacters', ['Character A, Character B']);
        $this->assertEquals('Character A, Character B', $comicInfo->__call('getCharacters', []));
    }
}

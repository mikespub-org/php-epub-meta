<?php
/**
 * @todo These are the methods that haven't been integrated with EPub here...
 */

use PHPUnit\Framework\TestCase;
use SebLucas\EPubMeta\Other;

class OtherTest extends TestCase
{
    const TEST_IMAGE = __DIR__ . '/data/test.jpg';
    const MARKUP_XML_1 = __DIR__ . '/data/markup.1.xml';
    const MARKUP_XML_2 = __DIR__ . '/data/markup.2.xml';
    const MARKUP_XML_3 = __DIR__ . '/data/markup.3.xml';
    const MARKUP_XML_4 = __DIR__ . '/data/markup.4.xml';
    const MARKUP_XML_5 = __DIR__ . '/data/markup.5.xml';

    protected Other $epub;

    public function testCover(): void
    {
        // read current cover
        $cover = $this->epub->getCover();
        $this->assertEquals(657911, strlen($cover));

        // change cover
        $this->epub->setCover(self::TEST_IMAGE, 'image/jpeg');
        $this->epub->save();

        // read recently changed cover
        $cover = $this->epub->getCover();
        $this->assertEquals(filesize(self::TEST_IMAGE), strlen($cover));

        // delete cover
        $this->epub->clearCover();
        $cover = $this->epub->getCover();
        $this->assertNull($cover);
    }

    /**
     * @throws Exception
     * @return void
     */
    public function testTitlePage()
    {
        // read current cover
        $this->epub->addCoverImageTitlePage();
        $this->epub->save();
        $spine = $this->epub->getSpine();
        $titlePage = $spine->first();

        $this->assertEquals('epubli-epub-titlepage.xhtml', $titlePage->getHref());
        $this->assertEquals('epubli-epub-titlepage', $titlePage->getId());
        $this->assertEquals('application/xhtml+xml', (string)$titlePage->getMediaType());

        // We expect an empty string since there is only an image but no text on that page.
        $this->assertEmpty(trim($titlePage->getContents()));
    }

    /**
     * @throws Exception
     * @return void
     */
    public function testToc()
    {
        $toc = $this->epub->getToc();
        $this->assertEquals('Romeo and Juliet', $toc->getDocTitle());
        $this->assertEquals('Shakespeare, William', $toc->getDocAuthor());
        $navMap = $toc->getNavMap();
        $this->assertEquals(8, $navMap->count());

        $navPoint = $navMap->first();
        /** @var NavPoint $navPoint */
        $this->assertEquals('level1-titlepage', $navPoint->getId());
        $this->assertEquals('titlepage', $navPoint->getClass());
        $this->assertEquals('1', $navPoint->getPlayOrder());
        $this->assertEquals('Title', $navPoint->getNavLabel());
        $this->assertEquals('title.xml', $navPoint->getContentSource());
        $this->assertCount(0, $navPoint->getChildren());

        $navMap->next();
        $navMap->next();
        $navPoint = $navMap->current();
        /** @var NavPoint $navPoint */
        $this->assertEquals('sec77303', $navPoint->getId());
        $this->assertEquals('section', $navPoint->getClass());
        $this->assertEquals('3', $navPoint->getPlayOrder());
        $this->assertEquals('Act I', $navPoint->getNavLabel());
        $this->assertEquals('main0.xml', $navPoint->getContentSource());
        $this->assertCount(6, $navPoint->getChildren());
        $this->assertEquals('Prologue', $navPoint->getChildren()->first()->getNavLabel());
        $this->assertEquals('SCENE V. A hall in Capulet\'s house.', $navPoint->getChildren()->last()->getNavLabel());
    }

    /**
     * @throws Exception
     * @return void
     */
    public function testSpine()
    {
        $spine = $this->epub->getSpine();
        $this->assertCount(31, $spine);

        $this->assertEquals('cover', $spine->first()->getId());
        $this->assertEquals('InternetMediaType::XHTML', $spine->current()->getMediaType());
        $spine->next();
        $this->assertEquals('title.xml', $spine->current()->getHref());
        $this->assertEquals('feedbooks', $spine->last()->getId());

        $this->assertEquals('fb.ncx', $spine->getTocItem()->getHref());

        $this->assertSame($spine[0], $spine->first());
        $this->assertSame($spine[30], $spine->last());
    }

    /**
     * @dataProvider provideContentsTestParameters
     * @param string $referenceStart The expected start of the extracted contents.
     * @param string $referenceEnd The expected end of the extracted contents.
     * @param int $referenceSize The expected size of the extracted contents.
     * @param bool $keepMarkup Whether to extract contents with or without HTML markup.
     * @param float $fraction
     * @throws Exception
     * @return void
     */
    public function testContents(
        $referenceStart,
        $referenceEnd,
        $referenceSize,
        $keepMarkup,
        $fraction
    ) {
        $contents = trim($this->epub->getContents($keepMarkup, $fraction));
        $this->assertStringStartsWith($referenceStart, substr($contents, 0, 100));
        $this->assertStringEndsWith($referenceEnd, substr($contents, -100));
        $this->assertEquals($referenceSize, strlen($contents));
    }

    /**
     * Summary of provideContentsTestParameters
     * @return array<mixed>
     */
    public function provideContentsTestParameters()
    {
        return [
            ["Romeo and Juliet\n\nWilliam Shakespeare", "www.feedbooks.com\n\n    Food for the mind", 152879, false, 1],
            ["Romeo and Juliet\n\nWilliam Shakespeare", "seek happy nights to happy days.\n\nExeunt", 24936, false, .2],
            ["Romeo and Juliet\n\nWilliam Shakespeare", "miss, our toil shall strive to mend.", 3810, false, .1],
        ];
    }

    /**
     * @dataProvider provideItemContentsTestParameters
     * @param string $referenceStart The expected start of the extracted contents.
     * @param string $referenceEnd The expected end of the extracted contents.
     * @param string $spineIndex The spine index of the item to extract contents from.
     * @param string $fragmentBegin The anchor name (ID) where to start extraction.
     * @param string $fragmentEnd The anchor name (ID) where to end extraction.
     * @throws Exception
     * @return void
     */
    public function testItemContents(
        $referenceStart,
        $referenceEnd,
        $spineIndex,
        $fragmentBegin = null,
        $fragmentEnd = null
    ) {
        $spine = $this->epub->getSpine();
        $contents = trim($spine[$spineIndex]->getContents($fragmentBegin, $fragmentEnd));
        $this->assertStringStartsWith($referenceStart, $contents);
        $this->assertStringEndsWith($referenceEnd, $contents);
    }

    /**
     * Summary of provideItemContentsTestParameters
     * @return array<mixed>
     */
    public function provideItemContentsTestParameters()
    {
        return [
            ['Act I', 'our toil shall strive to mend.', 3],
            ['SCENE I. Verona. A public place.', "I'll pay that doctrine, or else die in debt.\n\nExeunt", 4],
            ['Act III', 'Act III', 16, 'section_77331', 'section_77332'],
            ['Act III', 'Act III', 16, null, 'section_77332'],
            ['SCENE I. A public place.', "pardoning those that kill.\n\nExeunt", 16, 'section_77332'],
        ];
    }

    public function testItemContentsStartFragmentException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Begin of fragment not found:');
        $spine = $this->epub->getSpine();
        $spine[3]->getContents('NonExistingElement');
    }

    public function testItemContentsEndFragmentException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('End of fragment not found:');
        $spine = $this->epub->getSpine();
        $spine[3]->getContents(null, 'NonExistingElement');
    }

    /**
     * @dataProvider provideItemContentsMarkupTestParameters
     * @param string $referenceFile
     * @param string $spineIndex
     * @param string $fragmentBegin
     * @param string $fragmentEnd
     * @throws Exception
     * @return void
     */
    public function testItemContentsMarkup($referenceFile, $spineIndex, $fragmentBegin = null, $fragmentEnd = null)
    {
        $spine = $this->epub->getSpine();
        $contents = $spine[$spineIndex]->getContents($fragmentBegin, $fragmentEnd, true);
        $extracted = new DOMDocument();
        $extracted->loadXML($contents);
        $reference = new DOMDocument();
        $reference->load($referenceFile);
        $this->assertEqualXMLStructure($reference->documentElement, $extracted->documentElement);
    }

    /**
     * Summary of provideItemContentsMarkupTestParameters
     * @return array<mixed>
     */
    public function provideItemContentsMarkupTestParameters()
    {
        return [
            [self::MARKUP_XML_1, 3],
            [self::MARKUP_XML_2, 4],
            [self::MARKUP_XML_3, 16, 'section_77331', 'section_77332'],
            [self::MARKUP_XML_4, 16, null, 'section_77332'],
            [self::MARKUP_XML_5, 16, 'section_77332'],
        ];
    }

    /**
     * @throws Exception
     * @return void
     */
    public function testItemDataSize()
    {
        $item = $this->epub->getSpine()[0];
        $size = $item->getSize();
        $data = $item->getData();

        $this->assertEquals(strlen($data), $size);
    }
}

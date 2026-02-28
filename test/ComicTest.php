<?php

use PHPUnit\Framework\TestCase;
use SebLucas\EPubMeta\Comic;
use SebLucas\EPubMeta\ComicInfo;
use SebLucas\EPubMeta\Tools\ZipEdit;

/**
 * Test for Comic class
 */
class ComicTest extends TestCase
{
    public const TEST_CBZ = __DIR__ . '/data/test.cbz';
    public const COMIC_INFO = __DIR__ . '/data/ComicInfo.xml';
    public const TEST_IMAGE = __DIR__ . '/data/test.jpg';

    protected function setUp(): void
    {
        if (file_exists(self::TEST_CBZ)) {
            return;
            //unlink(self::TEST_CBZ);
        }

        $zip = new ZipArchive();
        if ($zip->open(self::TEST_CBZ, ZipArchive::CREATE) !== true) {
            $this->fail('Could not create test.cbz');
        }

        // Add ComicInfo.xml
        if (file_exists(self::COMIC_INFO)) {
            $zip->addFile(self::COMIC_INFO, 'ComicInfo.xml');
        } else {
            $this->fail('ComicInfo.xml not found at ' . self::COMIC_INFO);
        }

        // Add some images
        if (file_exists(self::TEST_IMAGE)) {
            $zip->addFile(self::TEST_IMAGE, '0.jpg');
            $zip->addFile(self::TEST_IMAGE, '1.jpg');
        } else {
            $this->fail('test.jpg not found at ' . self::TEST_IMAGE);
        }

        $zip->close();
    }

    protected function tearDown(): void
    {
        if (file_exists(self::TEST_CBZ)) {
            unlink(self::TEST_CBZ);
        }
    }

    public function testMetadata(): void
    {
        $comic = new Comic(self::TEST_CBZ);
        $this->assertInstanceOf(ComicInfo::class, $comic->getMetadata());
        $this->assertEquals('You Had One Job', $comic->getTitle());
    }

    public function testExplicitGetters(): void
    {
        $comic = new Comic(self::TEST_CBZ);
        $this->assertEquals('You Had One Job', $comic->getTitle());
        $this->assertEquals('Fantastic Four', $comic->getSeries());
        $this->assertEquals('22', $comic->getNumber());
        $this->assertEquals('Dan Slott', $comic->getWriter());
        $this->assertEquals('Marvel', $comic->getPublisher());
    }

    public function testInterfaceMethods(): void
    {
        $comic = new Comic(self::TEST_CBZ);

        // getDescription() -> getSummary()
        //$this->assertStringStartsWith('After a space-time anomaly', $comic->getDescription());

        // getSubjects() -> getTags()
        //$this->assertEquals(['Tragedy', 'Action', 'Sci-Fi'], $comic->getSubjects());

        // getLanguage() -> getLanguageISO()
        $this->assertEquals('en', $comic->getLanguage());

        // getIsbn() -> getGTIN()
        //$this->assertEquals('978-1302913537', $comic->getIsbn());

        // getAuthors()
        $authors = $comic->getAuthors();
        $this->assertArrayHasKey('Writer', $authors);
        $this->assertEquals('Dan Slott', $authors['Writer']);
        $this->assertArrayHasKey('Penciller', $authors);
        $this->assertEquals('Paco Medina, Sean Izaakse', $authors['Penciller']);
    }

    public function testCover(): void
    {
        $comic = new Comic(self::TEST_CBZ);

        // Based on ComicInfo.xml, Image="0" is FrontCover.
        // 0.jpg should be the first image in natural sort of [0.jpg, 1.jpg].
        $this->assertEquals('0.jpg', $comic->getCoverPath());

        $coverData = $comic->getCover();
        $this->assertNotEmpty($coverData);
        $this->assertEquals(filesize(self::TEST_IMAGE), strlen($coverData));
    }

    public function testSave(): void
    {
        // Use ZipEdit to allow saving
        $comic = new Comic(self::TEST_CBZ, ZipEdit::class);
        $comic->setTitle('New Title');
        $comic->setSeries('New Series');
        $comic->setNumber('100');
        $comic->setWriter('New Writer');
        $comic->save();

        // Re-open to verify
        $comic2 = new Comic(self::TEST_CBZ);
        $this->assertEquals('New Title', $comic2->getTitle());
        $this->assertEquals('New Series', $comic2->getSeries());
        $this->assertEquals('100', $comic2->getNumber());
        $this->assertEquals('New Writer', $comic2->getWriter());
    }

    public function testSetAuthors(): void
    {
        $comic = new Comic(self::TEST_CBZ, ZipEdit::class);

        $newAuthors = [
            'Writer' => 'New Writer',
            'Penciller' => 'New Penciller',
        ];
        $comic->setAuthors($newAuthors);
        $comic->save();

        $comic2 = new Comic(self::TEST_CBZ);
        $authors = $comic2->getAuthors();
        $this->assertEquals('New Writer', $authors['Writer']);
        $this->assertEquals('New Penciller', $authors['Penciller']);
        // Original Inker should be gone
        $this->assertArrayNotHasKey('Inker', $authors);
    }
}

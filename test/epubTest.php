<?php

use PHPUnit\Framework\TestCase;
use SebLucas\EPubMeta\EPub;

// remove seblucas/tbszip from composer.json
include_once(dirname(dirname(__DIR__)) . '/tbszip/tbszip.php');
// remove marsender/epub-loader from composer.json
include_once(dirname(dirname(__DIR__)) . '/epub-loader/ZipFile.class.php');

/**
 * Test for EPUB library
 *
 * Source: https://github.com/splitbrain/php-epub-meta
 * @author Andreas Gohr <andi@splitbrain.org> © 2012
 * @author Simon Schrape <simon@epubli.com> © 2015
 */
class EPubTest extends TestCase
{
    public const TEST_EPUB = __DIR__ . '/data/test.epub';
    public const TEST_EPUB_COPY = __DIR__ . '/data/test.copy.epub';
    public const TEST_IMAGE = __DIR__ . '/data/test.jpg';
    public const EMPTY_ZIP = __DIR__ . '/data/empty.zip';
    public const BROKEN_ZIP = __DIR__ . '/data/broken.zip';

    protected EPub $epub;

    protected function setUp(): void
    {
        // sometime I might have accidentally broken the test file
        if (filesize(self::TEST_EPUB) != 768780) {
            die('test.epub has wrong size, make sure it\'s unmodified');
        }

        // we work on a copy to test saving
        if (!copy(self::TEST_EPUB, self::TEST_EPUB_COPY)) {
            die('failed to create copy of the test book');
        }

        // @see https://github.com/sebastianbergmann/phpunit/issues/5062#issuecomment-1416362657
        set_error_handler(
            static function (int $errno, string $errstr) {
                throw new \Exception($errstr, $errno);
            },
            E_ALL
        );

        $this->epub = new Epub(self::TEST_EPUB_COPY);
    }

    protected function tearDown(): void
    {
        restore_error_handler();

        unlink(self::TEST_EPUB_COPY);
    }

    public function testOldAuthors(): void
    {
        // read curent value
        $this->assertEquals(
            $this->epub->Authors(),
            ['Shakespeare, William' => 'William Shakespeare']
        );

        // remove value with string
        $this->assertEquals(
            $this->epub->Authors(''),
            []
        );

        // set single value by String

        $this->assertEquals(
            $this->epub->Authors('John Doe'),
            ['John Doe' => 'John Doe']
        );

        // set single value by indexed array
        $this->assertEquals(
            $this->epub->Authors(['John Doe']),
            ['John Doe' => 'John Doe']
        );

        // remove value with array
        $this->assertEquals(
            $this->epub->Authors([]),
            []
        );

        // set single value by associative array
        $this->assertEquals(
            $this->epub->Authors(['Doe, John' => 'John Doe']),
            ['Doe, John' => 'John Doe']
        );

        // set multi value by string
        $this->assertEquals(
            $this->epub->Authors('John Doe, Jane Smith'),
            ['John Doe' => 'John Doe', 'Jane Smith' => 'Jane Smith']
        );

        // set multi value by indexed array
        $this->assertEquals(
            $this->epub->Authors(['John Doe', 'Jane Smith']),
            ['John Doe' => 'John Doe', 'Jane Smith' => 'Jane Smith']
        );

        // set multi value by associative  array
        $this->assertEquals(
            $this->epub->Authors(['Doe, John' => 'John Doe', 'Smith, Jane' => 'Jane Smith']),
            ['Doe, John' => 'John Doe', 'Smith, Jane' => 'Jane Smith']
        );

        // check escaping
        $this->assertEquals(
            $this->epub->Authors(['Doe, John&nbsp;' => 'John Doe&nbsp;']),
            ['Doe, John&nbsp;' => 'John Doe&nbsp;']
        );
    }

    public function testOldTitle(): void
    {
        // get current value
        $this->assertEquals(
            $this->epub->Title(),
            'Romeo and Juliet'
        );

        // delete current value
        $this->assertEquals(
            $this->epub->Title(''),
            ''
        );

        // get current value
        $this->assertEquals(
            $this->epub->Title(),
            ''
        );

        // set new value
        $this->assertEquals(
            $this->epub->Title('Foo Bar'),
            'Foo Bar'
        );

        // check escaping
        $this->assertEquals(
            $this->epub->Title('Foo&nbsp;Bar'),
            'Foo&nbsp;Bar'
        );
    }

    public function testOldSubject(): void
    {
        // get current values
        $this->assertEquals(
            $this->epub->Subjects(),
            ['Fiction','Drama','Romance']
        );

        // delete current values with String
        $this->assertEquals(
            $this->epub->Subjects(''),
            []
        );

        // set new values with String
        $this->assertEquals(
            $this->epub->Subjects('Fiction, Drama, Romance'),
            ['Fiction','Drama','Romance']
        );

        // delete current values with Array
        $this->assertEquals(
            $this->epub->Subjects([]),
            []
        );

        // set new values with array
        $this->assertEquals(
            $this->epub->Subjects(['Fiction','Drama','Romance']),
            ['Fiction','Drama','Romance']
        );

        // check escaping
        $this->assertEquals(
            $this->epub->Subjects(['Fiction','Drama&nbsp;','Romance']),
            ['Fiction','Drama&nbsp;','Romance']
        );
    }


    public function testOldCover(): void
    {
        // read current cover
        $cover = $this->epub->Cover();
        $this->assertEquals($cover['mime'], 'image/png');
        $this->assertEquals($cover['found'], 'OPS/images/cover.png');
        $this->assertEquals(strlen($cover['data']), 657911);

        /**
        $cover = $this->epub->Cover2();

        // // delete cover // Don't work anymore
        // $cover = $this->epub->Cover('');
        // $this->assertEquals($cover['mime'],'image/gif');
        // $this->assertEquals($cover['found'],false);
        // $this->assertEquals(strlen($cover['data']), 42);

        // // set new cover (will return a not-found as it's not yet saved)
        $cover = $this->epub->Cover2(self::TEST_IMAGE,'image/jpeg');
        // $this->assertEquals($cover['mime'],'image/jpeg');
        // $this->assertEquals($cover['found'],'OPS/php-epub-meta-cover.img');
        // $this->assertEquals(strlen($cover['data']), 0);

        // save
        $this->epub->save();
        //$this->epub = new EPub(self::TEST_EPUB_COPY);

        // read now changed cover
        $cover = $this->epub->Cover2();
        $this->assertEquals($cover['mime'],'image/jpeg');
        $this->assertEquals($cover['found'],'OPS/images/cover.png');
        $this->assertEquals(strlen($cover['data']), filesize(self::TEST_IMAGE));
         */
    }

    public function testGetZipEntries(): void
    {
        $entries = $this->epub->getZipEntries();
        $this->assertCount(49, $entries);
        $this->assertArrayHasKey(EPub::METADATA_FILE, $entries);
        $this->assertEquals(250, $entries[EPub::METADATA_FILE]['size']);

        $count = $this->epub->getImageCount();
        $this->assertEquals(3, $count);

        $coverpath = $this->epub->getCoverPath();
        $this->assertEquals('images/cover.png', $coverpath);

        $size = $this->epub->getComponentSize($coverpath);
        $this->assertEquals(657911, $size);
    }

    public function testLoadNonZip(): void
    {
        //$this->expectException(Exception::class);
        //$this->expectExceptionMessage('Failed to read EPUB file. Not a zip archive.');
        //$this->expectExceptionMessage('Failed to read epub file');
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Invalid or uninitialized Zip object');
        new Epub(self::TEST_IMAGE);
    }

    public function testLoadBrokenZip(): void
    {
        //$this->expectException(Exception::class);
        //$this->expectExceptionMessage('Failed to read EPUB file. Zip archive inconsistent.');
        //$this->expectExceptionMessage('Unable to find metadata.xml');
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Invalid or uninitialized Zip object');
        new Epub(self::BROKEN_ZIP);
    }

    public function testLoadMissingFile(): void
    {
        $this->expectException(Exception::class);
        //$this->expectExceptionMessage('Failed to read EPUB file. No such file.');
        $this->expectExceptionMessage('Epub file does not exist!');
        new Epub('/a/file/that/is/not_there.epub');
    }

    /**
     * We cannot expect a more specific exception message. ZipArchive::open returns 28
     * which is not known as an error code.
     */
    public function testLoadDirectory(): void
    {
        $this->expectException(Exception::class);
        //$this->expectExceptionMessage('Failed to read EPUB file.');
        $this->expectExceptionMessage('Epub file does not exist!');
        new Epub(__DIR__);
    }

    public function testLoadEmptyZip(): void
    {
        $this->expectException(Exception::class);
        //$this->expectExceptionMessage('Failed to read from EPUB container: META-INF/container.xml');
        //$this->expectExceptionMessage('Failed to read epub file');
        $this->expectExceptionMessage('Unable to find ' . EPub::METADATA_FILE);
        new Epub(self::EMPTY_ZIP);
    }

    public function testFilename(): void
    {
        $this->assertEquals(self::TEST_EPUB_COPY, $this->epub->file());
    }

    public function testAuthors(): void
    {
        // read curent value
        $this->assertEquals(['Shakespeare, William' => 'William Shakespeare'], $this->epub->getAuthors());

        // remove value with string
        $this->epub->setAuthors('');
        $this->assertEquals([], $this->epub->getAuthors());

        // set single value by String
        $this->epub->setAuthors('John Doe');
        $this->assertEquals(['John Doe' => 'John Doe'], $this->epub->getAuthors());

        // set single value by indexed array
        $this->epub->setAuthors(['John Doe']);
        $this->assertEquals(['John Doe' => 'John Doe'], $this->epub->getAuthors());

        // remove value with array
        $this->epub->setAuthors([]);
        $this->assertEquals([], $this->epub->getAuthors());

        // set single value by associative array
        $this->epub->setAuthors(['Doe, John' => 'John Doe']);
        $this->assertEquals(['Doe, John' => 'John Doe'], $this->epub->getAuthors());

        // set multi value by string
        $this->epub->setAuthors('John Doe, Jane Smith');
        $this->assertEquals(['John Doe' => 'John Doe', 'Jane Smith' => 'Jane Smith'], $this->epub->getAuthors());

        // set multi value by indexed array
        $this->epub->setAuthors(['John Doe', 'Jane Smith']);
        $this->assertEquals(['John Doe' => 'John Doe', 'Jane Smith' => 'Jane Smith'], $this->epub->getAuthors());

        // set multi value by associative  array
        $this->epub->setAuthors(['Doe, John' => 'John Doe', 'Smith, Jane' => 'Jane Smith']);
        $this->assertEquals(['Doe, John' => 'John Doe', 'Smith, Jane' => 'Jane Smith'], $this->epub->getAuthors());

        // check escaping
        $this->epub->setAuthors(['Doe, John&nbsp;' => 'John Doe&nbsp;']);
        $this->assertEquals(['Doe, John&nbsp;' => 'John Doe&nbsp;'], $this->epub->getAuthors());
    }

    public function testTitle(): void
    {
        // get current value
        $this->assertEquals('Romeo and Juliet', $this->epub->getTitle());

        // set new value
        $this->epub->setTitle('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getTitle());

        // delete current value
        $this->epub->setTitle('');
        $this->assertEquals('', $this->epub->getTitle());

        // check escaping
        $this->epub->setTitle('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getTitle());
    }

    public function testLanguage(): void
    {
        // get current value
        $this->assertEquals('en', $this->epub->getLanguage());

        // set new value
        $this->epub->setLanguage('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getLanguage());

        // delete current value
        $this->epub->setLanguage('');
        $this->assertEquals('', $this->epub->getLanguage());

        // check escaping
        $this->epub->setLanguage('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getLanguage());
    }

    public function testPublisher(): void
    {
        // get current value
        $this->assertEquals('Feedbooks', $this->epub->getPublisher());

        // set new value
        $this->epub->setPublisher('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getPublisher());

        // delete current value
        $this->epub->setPublisher('');
        $this->assertEquals('', $this->epub->getPublisher());

        // check escaping
        $this->epub->setPublisher('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getPublisher());
    }

    public function testCopyright(): void
    {
        // get current value
        $this->assertEquals('', $this->epub->getCopyright());

        // set new value
        $this->epub->setCopyright('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getCopyright());

        // delete current value
        $this->epub->setCopyright('');
        $this->assertEquals('', $this->epub->getCopyright());

        // check escaping
        $this->epub->setCopyright('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getCopyright());
    }

    public function testDescription(): void
    {
        // get current value
        $this->assertStringStartsWith('Romeo and Juliet is a tragic play written', $this->epub->getDescription());

        // set new value
        $this->epub->setDescription('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getDescription());

        // delete current value
        $this->epub->setDescription('');
        $this->assertEquals('', $this->epub->getDescription());

        // check escaping
        $this->epub->setDescription('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getDescription());
    }

    public function testUniqueIdentifier(): void
    {
        // get current value
        $this->assertEquals('urn:uuid:7d38d098-4234-11e1-97b6-001cc0a62c0b', $this->epub->getUniqueIdentifier());

        // set new value
        $this->epub->setUniqueIdentifier('134htb34tp089h1b');
        $this->assertEquals('134htb34tp089h1b', $this->epub->getUniqueIdentifier());
        // this should have affected the same node that is found when looking for UUID/URN scheme
        $this->assertEquals('134htb34tp089h1b', $this->epub->getUuid());
    }

    public function testUuid(): void
    {
        // get current value
        $this->assertEquals('urn:uuid:7d38d098-4234-11e1-97b6-001cc0a62c0b', $this->epub->getUuid());

        // set new value
        $this->epub->setUuid('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getUuid());

        // delete current value
        $this->epub->setUuid('');
        $this->assertEquals('', $this->epub->getUuid());

        // check escaping
        $this->epub->setUuid('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getUuid());
    }

    public function testUri(): void
    {
        // get current value
        $this->assertEquals('http://www.feedbooks.com/book/2936', $this->epub->getUri());

        // set new value
        $this->epub->setUri('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getUri());

        // delete current value
        $this->epub->setUri('');
        $this->assertEquals('', $this->epub->getUri());

        // check escaping
        $this->epub->setUri('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getUri());
    }

    public function testIsbn(): void
    {
        // get current value
        $this->assertEquals('', $this->epub->getIsbn());

        // set new value
        $this->epub->setIsbn('Foo Bar');
        $this->assertEquals('Foo Bar', $this->epub->getIsbn());

        // delete current value
        $this->epub->setIsbn('');
        $this->assertEquals('', $this->epub->getIsbn());

        // check escaping
        $this->epub->setIsbn('Foo&nbsp;Bar');
        $this->assertEquals('Foo&nbsp;Bar', $this->epub->getIsbn());
    }

    public function testSubject(): void
    {
        // get current values
        $this->assertEquals(['Fiction', 'Drama', 'Romance'], $this->epub->getSubjects());

        // delete current values with String
        $this->epub->setSubjects('');
        $this->assertEquals([], $this->epub->getSubjects());

        // set new values with String
        $this->epub->setSubjects('Fiction, Drama, Romance');
        $this->assertEquals(['Fiction', 'Drama', 'Romance'], $this->epub->getSubjects());

        // delete current values with Array
        $this->epub->setSubjects([]);
        $this->assertEquals([], $this->epub->getSubjects());

        // set new values with array
        $this->epub->setSubjects(['Fiction', 'Drama', 'Romance']);
        $this->assertEquals(['Fiction', 'Drama', 'Romance'], $this->epub->getSubjects());

        // check escaping
        $this->epub->setSubjects(['Fiction', 'Drama&nbsp;', 'Romance']);
        $this->assertEquals(['Fiction', 'Drama&nbsp;', 'Romance'], $this->epub->getSubjects());
    }

    public function testCover(): void
    {
        // read current cover
        $cover = $this->epub->getCover();
        $this->assertEquals(657911, strlen($cover));

        /**
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
         */
    }
}

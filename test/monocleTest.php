<?php

use PHPUnit\Framework\TestCase;
use SebLucas\EPubMeta\EPub;

/**
 * Test for EPUB methods used by Monocle in COPS
 *
 * Source: https://github.com/mikespub-org/seblucas
 */
class MonocleTest extends TestCase
{
    public const TEST_EPUB = __DIR__ . '/data/eng.epub';
    public const TEST_EPUB_COPY = __DIR__ . '/data/eng.copy.epub';
    public const TEST_CONTENTS = __DIR__ . '/data/eng.contents.json';
    public const TEST_COMPONENTS = __DIR__ . '/data/eng.components.json';
    public const TEST_EPUB3 = __DIR__ . '/data/eng3.epub';

    private static EPub $book;

    public static function setUpBeforeClass(): void
    {
        // sometime I might have accidentally broken the test file
        if (filesize(static::TEST_EPUB) != 22664) {
            die('test.epub has wrong size, make sure it\'s unmodified');
        }

        // we work on a copy to test saving
        if (!copy(static::TEST_EPUB, static::TEST_EPUB_COPY)) {
            die('failed to create copy of the test book');
        }

        // @see https://github.com/sebastianbergmann/phpunit/issues/5062#issuecomment-1416362657
        set_error_handler(
            static function (int $errno, string $errstr) {
                throw new \Exception($errstr, $errno);
            },
            E_ALL
        );

        self::$book = new Epub(static::TEST_EPUB_COPY);
        self::$book->initSpineComponent();
    }

    public static function tearDownAfterClass(): void
    {
        restore_error_handler();

        unlink(static::TEST_EPUB_COPY);
    }

    public function testComponents(): void
    {
        $data = self::$book->components();
        $contents = file_get_contents(static::TEST_COMPONENTS);
        $check = json_decode($contents, true);
        $encoder = $this->provideEncodeReplace();
        $check = str_replace($encoder[0], $encoder[1], $check);
        $this->assertEquals($check, $data);
    }

    public function testContents(): void
    {
        $data = self::$book->contents();
        $contents = file_get_contents(static::TEST_CONTENTS);
        $check = json_decode($contents, true);
        $encoder = $this->provideEncodeReplace();
        foreach (array_keys($check) as $idx) {
            $check[$idx] = $this->encodeItem($check[$idx], $encoder);
        }
        $this->assertEquals($check, $data);
    }

    /**
     * Summary of testComponent
     * @param string $component
     * @return void
     */
    public function testComponent($component = 'text/titlepage.xhtml')
    {
        $data = self::$book->component($component);
        $check = 641;
        $this->assertEquals($check, strlen($data));
    }

    /**
     * Summary of testGetComponentName
     * @param string $component
     * @param string $element
     * @return void
     */
    public function testGetComponentName($component = 'text/titlepage.xhtml', $element = '../images/cover.jpg')
    {
        $data = self::$book->getComponentName($component, $element);
        $check = 'images~SLASH~cover.jpg';
        $this->assertEquals($check, $data);
    }

    /**
     * Summary of testComponentContentType
     * @param string $component
     * @return void
     */
    public function testComponentContentType($component = 'text/titlepage.xhtml')
    {
        $data = self::$book->componentContentType($component);
        $check = 'application/xhtml+xml';
        $this->assertEquals($check, $data);
    }

    public function testContentsEpub3(): void
    {
        $epub = new EPub(__DIR__ . '/data/eng3.epub');
        $epub->initSpineComponent();
        $data = $epub->contents();
        $contents = file_get_contents(static::TEST_CONTENTS);
        $check = json_decode($contents, true);
        $encoder = $this->provideEncodeReplace();
        foreach (array_keys($check) as $idx) {
            $check[$idx] = $this->encodeItem($check[$idx], $encoder);
        }
        $this->assertEquals($check, $data);
    }

    /**
     * Summary of encodeItem
     * @param array<mixed> $item
     * @param array<mixed> $encoder
     * @return array<mixed>
     */
    protected function encodeItem($item, $encoder)
    {
        $item['src'] = str_replace($encoder[0], $encoder[1], $item['src']);
        if (!empty($item['children'])) {
            foreach (array_keys($item['children']) as $idx) {
                $item['children'][$idx] = $this->encodeItem($item['children'][$idx], $encoder);
            }
        }
        return $item;
    }

    /**
     * Summary of provideEncodeReplace
     * @return array<mixed>
     */
    public function provideEncodeReplace()
    {
        return EPub::$encodeNameReplace;
    }

    /**
     * Summary of provideDecodeReplace
     * @return array<mixed>
     */
    public function provideDecodeReplace()
    {
        return EPub::$decodeNameReplace;
    }
}

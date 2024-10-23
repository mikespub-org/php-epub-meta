<?php

use SebLucas\EPubMeta\App\Handler;
use PHPUnit\Framework\TestCase;

/**
 * Test for EPub Meta App Handler
 */
class HandlerTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function getConfig(bool $recursive = false): array
    {
        if ($recursive) {
            $bookdir = dirname(__DIR__) . '/test/';
        } else {
            $bookdir = dirname(__DIR__) . '/test/data/';
        }
        return [
            'bookdir' => $bookdir,
            'recursive' => $recursive,
            'baseurl' => '..',
            'rename' => true,
            'templatedir' => dirname(__DIR__) . '/templates/',
            'cachedir' => dirname(__DIR__) . '/cache/',
        ];
    }

    public function testGetHome(): void
    {
        $config = $this->getConfig();
        $handler = new Handler($config);
        $result = $handler->handle();

        $expected = '<title>EPub Metadata</title>';
        $this->assertStringContainsString($expected, $result);
        $expected = '<a href="?book=test"><span class="title">test</span>';
        $this->assertStringContainsString($expected, $result);
        $expected = 'MIT License';
        $this->assertStringContainsString($expected, $result);
    }

    public function testGetBookFromGlobals(): void
    {
        $_REQUEST['book'] = 'test';

        $config = $this->getConfig();
        $handler = new Handler($config);
        $params = $handler->getRequestFromGlobals();

        $expected = [
            'api' => null,
            'lang' => null,
            'book' => 'test',
            'img' => null,
            'save' => null,
        ];
        $this->assertEquals($expected, $params);

        $result = $handler->handle($params);

        $expected = '<title>EPub Metadata</title>';
        $this->assertStringContainsString($expected, $result);
        $expected = 'Romeo and Juliet';
        $this->assertStringContainsString($expected, $result);

        unset($_REQUEST['book']);
    }

    public function testGetBookFromParams(): void
    {
        $config = $this->getConfig();
        $handler = new Handler($config);
        $params = [
            'book' => 'test',
        ];
        $result = $handler->handle($params);

        $expected = '<title>EPub Metadata</title>';
        $this->assertStringContainsString($expected, $result);
        $expected = 'Romeo and Juliet';
        $this->assertStringContainsString($expected, $result);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testGetCoverImage(): void
    {
        $config = $this->getConfig();
        $handler = new Handler($config);
        $params = [
            'book' => 'test',
            'img' => '1',
        ];
        $result = $handler->handle($params);
        //$headers = headers_list();

        $expected = 657911;
        $this->assertEquals($expected, strlen($result));
    }

    public function testSearchBookApi(): void
    {
        $config = $this->getConfig();
        $handler = new Handler($config);
        $params = [
            'book' => 'test',
            'api' => 'Romeo and Juliet',
            'lang' => 'en',
        ];
        $output = $handler->handle($params);
        $result = json_decode($output, true);

        $expected = 355;
        $this->assertEquals($expected, $result['totalItems']);
        $expected = 'Romeo and Juliet';
        $this->assertStringContainsString($expected, $result['items'][0]['volumeInfo']['title']);
    }

    public function testGetHomeRecursive(): void
    {
        $config = $this->getConfig(true);
        $handler = new Handler($config);
        $result = $handler->handle();

        $expected = '<title>EPub Metadata</title>';
        $this->assertStringContainsString($expected, $result);
        $expected = '<a href="?book=data/test"><span class="title">test</span>';
        $this->assertStringContainsString($expected, $result);
        $expected = 'MIT License';
        $this->assertStringContainsString($expected, $result);
    }

    public function testGetBookRecursive(): void
    {
        $config = $this->getConfig(true);
        $handler = new Handler($config);
        $params = ['book' => 'data/test'];
        $result = $handler->handle($params);

        $expected = '<title>EPub Metadata</title>';
        $this->assertStringContainsString($expected, $result);
        $expected = 'Romeo and Juliet';
        $this->assertStringContainsString($expected, $result);
    }

    public function testGetBookInvalid(): void
    {
        $config = $this->getConfig(true);
        $handler = new Handler($config);
        $params = ['book' => '../../etc/passwd'];
        $result = $handler->handle($params);

        $expected = '<title>EPub Metadata</title>';
        $this->assertStringContainsString($expected, $result);
        $expected = "alert('Invalid ebook file passwd.epub');";
        $this->assertStringContainsString($expected, $result);
    }

    public function testGetBookOutsideBookdir(): void
    {
        $config = $this->getConfig(true);
        $handler = new Handler($config);
        $params = ['book' => '../../epub-tests/tests/nav-access'];
        $result = $handler->handle($params);

        $expected = '<title>EPub Metadata</title>';
        $this->assertStringContainsString($expected, $result);
        $expected = "alert('No ebooks allowed outside bookdir. Are you using symlinks inside bookdir?');";
        $this->assertStringContainsString($expected, $result);
    }
}

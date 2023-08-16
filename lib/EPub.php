<?php
/**
 * PHP EPub Meta library
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Sébastien Lucas <sebastien@slucas.fr>
 * @author Simon Schrape <simon@epubli.com> © 2015
 */

namespace SebLucas\EPubMeta;

use SebLucas\EPubMeta\Dom\Element as EpubDomElement;
use SebLucas\EPubMeta\Dom\XPath as EpubDomXPath;
use SebLucas\EPubMeta\Data\Manifest;
use SebLucas\EPubMeta\Contents\Nav;
use SebLucas\EPubMeta\Contents\Spine;
use SebLucas\EPubMeta\Contents\NavPoint as TocNavPoint;
use SebLucas\EPubMeta\Contents\NavPointList as TocNavPointList;
use SebLucas\EPubMeta\Contents\Toc;
use SebLucas\TbsZip\clsTbsZip;
use Marsender\EPubLoader\ZipFile;
use DOMDocument;
use DOMNodeList;
use Exception;
use InvalidArgumentException;
use ZipArchive;

use const SebLucas\TbsZip\TBSZIP_DOWNLOAD;

class EPub
{
    /** Identifier for cover image inserted by this lib. */
    public const COVER_ID = 'php-epub-meta-cover';
    /** Identifier for title page inserted by this lib. */
    public const TITLE_PAGE_ID = 'php-epub-meta-titlepage';
    public const METADATA_FILE = 'META-INF/container.xml';
    /** @var DOMDocument */
    public $xml; //FIXME: change to protected, later
    /** @var DOMDocument */
    public $toc;
    /** @var DOMDocument */
    public $nav;
    /** @var EpubDomXPath */
    protected $xpath;
    /** @var EpubDomXPath */
    protected $toc_xpath;
    /** @var EpubDomXPath */
    protected $nav_xpath;
    protected string $file;
    protected string $meta;
    /** @var clsTbsZip|ZipFile */
    protected $zip;
    protected string $coverpath='';
    /** @var mixed */
    protected $namespaces;
    protected string $imagetoadd='';
    /** @var array<mixed> A map of ZIP items mapping filenames to file sizes */
    private $zipSizeMap;
    /** @var Manifest|null The manifest (catalog of files) of this EPUB */
    private $manifest;
    /** @var Spine|null The spine structure of this EPUB */
    private $spine;
    /** @var Toc|Nav|null The TOC structure of this EPUB */
    private $tocnav;

    /**
     * Constructor
     *
     * @param string $file path to epub file to work on
     * @param string $zipClass class to handle zip - ZipFile is read-only
     * @throws Exception if metadata could not be loaded
     */
    public function __construct($file, $zipClass = ZipFile::class)
    {
        if (!is_file($file)) {
            throw new Exception("Epub file does not exist!");
        }
        if (filesize($file) <= 0) {
            throw new Exception("Epub file is empty!");
        }
        // open file
        $this->file = $file;
        $this->zip = new $zipClass();
        if (!$this->zip->Open($this->file)) {
            throw new Exception('Failed to read epub file');
        }

        // read container data
        $this->loadMetadata();
    }

    /**
     * Summary of loadMetadata
     * @throws \Exception
     * @return void
     */
    public function loadMetadata()
    {
        if (!$this->zip->FileExists(static::METADATA_FILE)) {
            throw new Exception('Unable to find ' . static::METADATA_FILE);
        }

        $data = $this->zip->FileRead(static::METADATA_FILE);
        if ($data == false) {
            throw new Exception('Failed to access epub container data');
        }
        $xml = new DOMDocument();
        $xml->registerNodeClass('DOMElement', EpubDomElement::class);
        $xml->loadXML($data);
        $xpath = new EpubDomXPath($xml);
        $nodes = $xpath->query('//n:rootfiles/n:rootfile[@media-type="application/oebps-package+xml"]');
        $this->meta = static::getAttr($nodes, 'full-path');

        // load metadata
        if (!$this->zip->FileExists($this->meta)) {
            throw new Exception('Unable to find ' . $this->meta);
        }

        $data = $this->zip->FileRead($this->meta);
        if (!$data) {
            throw new Exception('Failed to access epub metadata');
        }
        $this->xml =  new DOMDocument();
        $this->xml->registerNodeClass('DOMElement', EpubDomElement::class);
        $this->xml->loadXML($data);
        $this->xml->formatOutput = true;
        $this->xpath = new EpubDomXPath($this->xml);

        $this->zipSizeMap = $this->loadSizeMap($this->file);
    }

    /**
     * Summary of initSpineComponent
     * @throws \Exception
     * @return void
     */
    public function initSpineComponent()
    {
        $nodes = $this->xpath->query('//opf:spine');
        $tocid = static::getAttr($nodes, 'toc');
        if (empty($tocid)) {
            $nodes = $this->xpath->query('//opf:manifest/opf:item[@properties="nav"]');
            $navhref = static::getAttr($nodes, 'href');
            $navpath = $this->getFullPath($navhref);
            // read epub nav doc
            if (!$this->zip->FileExists($navpath)) {
                throw new Exception('Unable to find ' . $navpath);
            }
            $data = $this->zip->FileRead($navpath);
            $this->nav = new DOMDocument();
            $this->nav->registerNodeClass('DOMElement', EpubDomElement::class);
            $this->nav->loadXML($data);
            $this->nav_xpath = new EpubDomXPath($this->nav);
            $rootNamespace = $this->nav->lookupNamespaceUri($this->nav->namespaceURI);
            $this->nav_xpath->registerNamespace('x', $rootNamespace);
            return;
        }
        $nodes = $this->xpath->query('//opf:manifest/opf:item[@id="' . $tocid . '"]');
        $tochref = static::getAttr($nodes, 'href');
        $tocpath = $this->getFullPath($tochref);
        // read epub toc
        if (!$this->zip->FileExists($tocpath)) {
            throw new Exception('Unable to find ' . $tocpath);
        }

        $data = $this->zip->FileRead($tocpath);
        $this->toc = new DOMDocument();
        $this->toc->registerNodeClass('DOMElement', EpubDomElement::class);
        $this->toc->loadXML($data);
        $this->toc_xpath = new EpubDomXPath($this->toc);
        $rootNamespace = $this->toc->lookupNamespaceUri($this->toc->namespaceURI);
        $this->toc_xpath->registerNamespace('x', $rootNamespace);
    }

    /**
     * file name getter
     * @return string
     */
    public function file()
    {
        return $this->file;
    }

    /**
     * Close the epub file
     * @return void
     */
    public function close()
    {
        $this->zip->FileCancelModif($this->meta);
        // TODO: Add cancelation of cover image
        $this->zip->Close();
    }

    /**
     * Remove iTunes files
     * @return void
     */
    public function cleanITunesCrap()
    {
        if ($this->zip->FileExists('iTunesMetadata.plist')) {
            $this->zip->FileReplace('iTunesMetadata.plist', false);
        }
        if ($this->zip->FileExists('iTunesArtwork')) {
            $this->zip->FileReplace('iTunesArtwork', false);
        }
    }

    /**
     * Writes back all meta data changes
     * @return void
     */
    public function save()
    {
        $this->download();
        $this->zip->close();
    }

    /**
     * Get the updated epub
     * @param mixed $file
     * @return void
     */
    public function download($file=false)
    {
        $this->zip->FileReplace($this->meta, $this->xml->saveXML());
        // add the cover image
        if ($this->imagetoadd) {
            $this->zip->FileReplace($this->coverpath, file_get_contents($this->imagetoadd));
            $this->imagetoadd='';
        }
        if ($file) {
            $this->zip->Flush(TBSZIP_DOWNLOAD, $file);
        }
    }

    /**
     * Get the components list as an array
     * @return array<mixed>
     */
    public function components()
    {
        $spine = [];
        $nodes = $this->xpath->query('//opf:spine/opf:itemref');
        foreach ($nodes as $node) {
            /** @var EpubDomElement $node */
            $idref =  $node->getAttribute('idref');
            /** @var EpubDomElement $item */
            $item = $this->xpath->query('//opf:manifest/opf:item[@id="' . $idref . '"]')->item(0);
            $spine[] = $this->encodeComponentName($item->getAttribute('href'));
        }
        return $spine;
    }

    /**
     * Get the component content
     * @param mixed $comp
     * @return mixed
     */
    public function component($comp)
    {
        $path = $this->decodeComponentName($comp);
        $path = $this->getFullPath($path);
        if (!$this->zip->FileExists($path)) {
            throw new Exception('Unable to find ' . $path . ' <' . $comp . '>');
        }

        $data = $this->zip->FileRead($path);
        return $data;
    }

    /**
     * Summary of getComponentName
     * @param mixed $comp
     * @param mixed $elementPath
     * @return bool|string
     */
    public function getComponentName($comp, $elementPath)
    {
        $path = $this->decodeComponentName($comp);
        $path = $this->getFullPath($path, $elementPath);
        if (!$this->zip->FileExists($path)) {
            error_log('Unable to find ' . $path);
            return false;
        }
        $ref = dirname('/' . $this->meta);
        $ref = ltrim($ref, '\\');
        $ref = ltrim($ref, '/');
        if (strlen($ref) > 0) {
            $path = str_replace($ref . '/', '', $path);
        }
        return $this->encodeComponentName($path);
    }

    /**
     * Encode the component name (to replace / and -)
     * @param mixed $src
     * @return string
     */
    private function encodeComponentName($src)
    {
        return str_replace(
            ['/', '-'],
            ['~SLASH~', '~DASH~'],
            $src
        );
    }

    /**
     * Decode the component name (to replace / and -)
     * @param mixed $src
     * @return string
     */
    private function decodeComponentName($src)
    {
        return str_replace(
            ['~SLASH~', '~DASH~'],
            ['/', '-'],
            $src
        );
    }


    /**
     * Get the component content type
     * @param mixed $comp
     * @return string
     */
    public function componentContentType($comp)
    {
        $comp = $this->decodeComponentName($comp);
        $nodes = $this->xpath->query('//opf:manifest/opf:item[@href="' . $comp . '"]');
        if ($nodes->length) {
            return static::getAttr($nodes, 'media-type');
        }

        // I had at least one book containing %20 instead of spaces in the opf file
        $comp = str_replace(' ', '%20', $comp);
        $nodes = $this->xpath->query('//opf:manifest/opf:item[@href="' . $comp . '"]');
        if ($nodes->length) {
            return static::getAttr($nodes, 'media-type');
        }
        return 'application/octet-stream';
    }

    /**
     * Summary of getComponentSize
     * @param mixed $comp
     * @return bool|int
     */
    public function getComponentSize($comp)
    {
        $path = $this->decodeComponentName($comp);
        $path = $this->getFullPath($path);
        if (!$this->zip->FileExists($path)) {
            error_log('Unable to find ' . $path);
            return false;
        }

        $sizeMap = $this->loadSizeMap();
        return $sizeMap[$path];
    }

    /**
     * EPUB 2 navigation control file (NCX format)
     * See https://idpf.org/epub/20/spec/OPF_2.0_latest.htm#Section2.4.1
     * @param mixed $node
     * @return array<string, string>
     */
    private function getNavPointDetail($node)
    {
        $title = $this->toc_xpath->query('x:navLabel/x:text', $node)->item(0)->nodeValue;
        $nodes = $this->toc_xpath->query('x:content', $node);
        $src = static::getAttr($nodes, 'src');
        $src = $this->encodeComponentName($src);
        return ['title' => preg_replace('~[\r\n]+~', '', $title), 'src' => $src];
    }

    /**
     * EPUB 3 navigation document (toc nav element)
     * See https://www.w3.org/TR/epub-33/#sec-nav-toc
     * @param mixed $node
     * @return array<string, string>
     */
    private function getNavTocListItem($node)
    {
        $nodes = $this->nav_xpath->query('x:a', $node);
        $title = $nodes->item(0)->nodeValue;
        $src = static::getAttr($nodes, 'href');
        $src = $this->encodeComponentName($src);
        return ['title' => preg_replace('~[\r\n]+~', '', $title), 'src' => $src];
    }

    /**
     * Get the Epub content (TOC) as an array
     *
     * For each chapter there is a "title" and a "src"
     * @return mixed
     */
    public function contents()
    {
        $contents = [];
        if (!empty($this->nav)) {
            $toc = $this->nav_xpath->query('//x:nav[@epub:type="toc"]')->item(0);
            $nodes = $this->nav_xpath->query('//x:ol/x:li', $toc);
            foreach ($nodes as $node) {
                $contents[] = $this->getNavTocListItem($node);
            }
            return $contents;
        }
        $nodes = $this->toc_xpath->query('//x:ncx/x:navMap/x:navPoint');
        foreach ($nodes as $node) {
            $contents[] = $this->getNavPointDetail($node);

            $insidenodes = $this->toc_xpath->query('x:navPoint', $node);
            foreach ($insidenodes as $insidenode) {
                $contents[] = $this->getNavPointDetail($insidenode);
            }
        }
        return $contents;
    }

    /**
     * Get or set the book author(s)
     * @param mixed $authors
     * @return mixed
     */
    public function Authors($authors=false)
    {
        // set new data
        if ($authors !== false) {
            $this->setAuthors($authors);
        }

        // read current data
        return $this->getAuthors();
    }

    /**
     * Set the book author(s)
     *
     * Authors should be given with a "file-as" and a real name. The file as
     * is used for sorting in e-readers.
     *
     * Example:
     *
     * array(
     *      'Pratchett, Terry'   => 'Terry Pratchett',
     *      'Simpson, Jacqueline' => 'Jacqueline Simpson',
     * )
     *
     * @param mixed $authors
     * @return void
     */
    public function setAuthors($authors)
    {
        // Author where given as a comma separated list
        if (is_string($authors)) {
            if ($authors == '') {
                $authors = [];
            } else {
                $authors = explode(',', $authors);
                $authors = array_map('trim', $authors);
            }
        }

        // delete existing nodes
        $nodes = $this->xpath->query('//opf:metadata/dc:creator[@opf:role="aut"]');
        static::deleteNodes($nodes);

        // add new nodes
        /** @var EpubDomElement $parent */
        $parent = $this->xpath->query('//opf:metadata')->item(0);
        foreach ($authors as $as => $name) {
            if (is_int($as)) {
                $as = $name; //numeric array given
            }
            $node = $parent->newChild('dc:creator', $name);
            $node->setAttrib('opf:role', 'aut');
            $node->setAttrib('opf:file-as', $as);
        }

        $this->reparse();
    }

    /**
     * Get the book author(s)
     * @return array<string>
     */
    public function getAuthors()
    {
        $rolefix = false;
        $authors = [];
        $nodes = $this->xpath->query('//opf:metadata/dc:creator[@opf:role="aut"]');
        if ($nodes->length == 0) {
            // no nodes where found, let's try again without role
            $nodes = $this->xpath->query('//opf:metadata/dc:creator');
            $rolefix = true;
        }
        foreach ($nodes as $node) {
            /** @var EpubDomElement $node */
            $name = $node->nodeValue;
            $as   = $node->getAttrib('opf:file-as');
            if (!$as) {
                $as = $name;
                $node->setAttrib('opf:file-as', $as);
            }
            if ($rolefix) {
                $node->setAttrib('opf:role', 'aut');
            }
            $authors[$as] = $name;
        }
        return $authors;
    }

    /**
     * Set or get the book title
     *
     * @param string|bool $title
     * @return mixed
     */
    public function Title($title=false)
    {
        return $this->getset('dc:title', $title);
    }

    /**
     * Set or get the book's language
     *
     * @param string|bool $lang
     * @return mixed
     */
    public function Language($lang=false)
    {
        return $this->getset('dc:language', $lang);
    }

    /**
     * Set or get the book' publisher info
     *
     * @param string|bool $publisher
     * @return mixed
     */
    public function Publisher($publisher=false)
    {
        return $this->getset('dc:publisher', $publisher);
    }

    /**
     * Set or get the book's copyright info
     *
     * @param string|bool $rights
     * @return mixed
     */
    public function Copyright($rights=false)
    {
        return $this->getset('dc:rights', $rights);
    }

    /**
     * Set or get the book's description
     *
     * @param string|bool $description
     * @return mixed
     */
    public function Description($description=false)
    {
        return $this->getset('dc:description', $description);
    }

    /**
     * Set or get the book's Unique Identifier
     *
     * @param string|bool $uuid Unique identifier
     * @return mixed
     */
    public function Uuid($uuid = false)
    {
        $nodes = $this->xpath->query('/opf:package');
        if ($nodes->length !== 1) {
            $error = sprintf('Cannot find ebook identifier');
            throw new Exception($error);
        }
        $identifier = static::getAttr($nodes, 'unique-identifier');

        $res = $this->getset('dc:identifier', $uuid, 'id', $identifier);

        return $res;
    }

    /**
     * Set or get the book's creation date
     *
     * @param string|bool $date Date eg: 2012-05-19T12:54:25Z
     * @return mixed
     */
    public function CreationDate($date = false)
    {
        $res = $this->getset('dc:date', $date, 'opf:event', 'creation');

        return $res;
    }

    /**
     * Set or get the book's modification date
     *
     * @param string|bool $date Date eg: 2012-05-19T12:54:25Z
     * @return mixed
     */
    public function ModificationDate($date = false)
    {
        $res = $this->getset('dc:date', $date, 'opf:event', 'modification');

        return $res;
    }

    /**
     * Set or get the book's URI
     *
     * @param string|bool $uri URI
     * @return mixed
     */
    public function Uri($uri = false)
    {
        $res = $this->getset('dc:identifier', $uri, 'opf:scheme', 'URI');

        return $res;
    }

    /**
     * Set or get the book's ISBN number
     *
     * @param string|bool $isbn
     * @return mixed
     */
    public function ISBN($isbn=false)
    {
        return $this->getset('dc:identifier', $isbn, 'opf:scheme', 'ISBN');
    }

    /**
     * Set or get the Google Books ID
     *
     * @param string|bool $google
     * @return mixed
     */
    public function Google($google=false)
    {
        return $this->getset('dc:identifier', $google, 'opf:scheme', 'GOOGLE');
    }

    /**
     * Set or get the Amazon ID of the book
     *
     * @param string|bool $amazon
     * @return mixed
     */
    public function Amazon($amazon=false)
    {
        return $this->getset('dc:identifier', $amazon, 'opf:scheme', 'AMAZON');
    }

    /**
     * Set or get the Calibre UUID of the book
     *
     * @param string|bool $uuid
     * @return mixed
     */
    public function Calibre($uuid=false)
    {
        return $this->getset('dc:identifier', $uuid, 'opf:scheme', 'calibre');
    }

    /**
     * Set or get the Serie of the book
     *
     * @param string|bool $serie
     * @return mixed
     */
    public function Serie($serie=false)
    {
        return $this->getset('opf:meta', $serie, 'name', 'calibre:series', 'content');
    }

    /**
     * Set or get the Serie Index of the book
     *
     * @param string|bool $serieIndex
     * @return mixed
     */
    public function SerieIndex($serieIndex=false)
    {
        return $this->getset('opf:meta', $serieIndex, 'name', 'calibre:series_index', 'content');
    }

    /**
     * Set or get the book's subjects (aka. tags)
     *
     * Subject should be given as array, but a comma separated string will also
     * be accepted.
     *
     * @param array<string>|string|bool $subjects
     * @return array<mixed>
     */
    public function Subjects($subjects=false)
    {
        // setter
        if ($subjects !== false) {
            $this->setSubjects($subjects);
        }

        //getter
        return $this->getSubjects();
    }

    /**
     * Set the book's subjects (aka. tags)
     *
     * Subject should be given as array, but a comma separated string will also
     * be accepted.
     *
     * @param array<string>|string $subjects
     * @return void
     */
    public function setSubjects($subjects)
    {
        if (is_string($subjects)) {
            if ($subjects === '') {
                $subjects = [];
            } else {
                $subjects = explode(',', $subjects);
                $subjects = array_map('trim', $subjects);
            }
        }

        // delete previous
        $nodes = $this->xpath->query('//opf:metadata/dc:subject');
        static::deleteNodes($nodes);
        // add new ones
        $parent = $this->xpath->query('//opf:metadata')->item(0);
        foreach ($subjects as $subj) {
            $node = $this->xml->createElement('dc:subject', htmlspecialchars($subj));
            $node = $parent->appendChild($node);
        }

        $this->reparse();
    }

    /**
     * Get the book's subjects (aka. tags)
     * @return array<mixed>
     */
    public function getSubjects()
    {
        $subjects = [];
        $nodes = $this->xpath->query('//opf:metadata/dc:subject');
        foreach ($nodes as $node) {
            $subjects[] =  $node->nodeValue;
        }
        return $subjects;
    }

    /**
     * Read the cover data
     *
     * Returns an associative array with the following keys:
     *
     *   mime  - filetype (usually image/jpeg)
     *   data  - the binary image data
     *   found - the internal path, or false if no image is set in epub
     *
     * When no image is set in the epub file, the binary data for a transparent
     * GIF pixel is returned.
     *
     * When adding a new image this function return no or old data because the
     * image contents are not in the epub file, yet. The image will be added when
     * the save() method is called.
     *
     * @param  string|bool $path local filesystem path to a new cover image
     * @param  string|bool $mime mime type of the given file
     * @return array<mixed>
     */
    public function Cover($path=false, $mime=false)
    {
        // set cover
        if ($path !== false) {
            // remove current pointer
            $nodes = $this->xpath->query('//opf:metadata/opf:meta[@name="cover"]');
            static::deleteNodes($nodes);
            // remove previous manifest entries if they where made by us
            $nodes = $this->xpath->query('//opf:manifest/opf:item[@id="'. static::COVER_ID . '"]');
            static::deleteNodes($nodes);

            if ($path) {
                // add pointer
                /** @var EpubDomElement $parent */
                $parent = $this->xpath->query('//opf:metadata')->item(0);
                $node = $parent->newChild('opf:meta');
                $node->setAttrib('opf:name', 'cover');
                $node->setAttrib('opf:content', static::COVER_ID);

                // add manifest
                /** @var EpubDomElement $parent */
                $parent = $this->xpath->query('//opf:manifest')->item(0);
                $node = $parent->newChild('opf:item');
                $node->setAttrib('id', static::COVER_ID);
                $node->setAttrib('opf:href', static::COVER_ID . '.img');
                $node->setAttrib('opf:media-type', $mime);

                // remember path for save action
                $this->imagetoadd = $path;
            }

            $this->reparse();
        }

        // load cover
        $nodes = $this->xpath->query('//opf:metadata/opf:meta[@name="cover"]');
        if (!$nodes->length) {
            return $this->no_cover();
        }
        $coverid = (string) static::getAttr($nodes, 'opf:content');
        if (!$coverid) {
            return $this->no_cover();
        }

        $nodes = $this->xpath->query('//opf:manifest/opf:item[@id="' . $coverid . '"]');
        if (!$nodes->length) {
            return $this->no_cover();
        }
        $mime = static::getAttr($nodes, 'opf:media-type');
        $path = static::getAttr($nodes, 'opf:href');
        $path = dirname('/' . $this->meta) . '/' . $path; // image path is relative to meta file
        $path = ltrim($path, '/');

        $zip = new ZipArchive();
        if (!@$zip->open($this->file)) {
            throw new Exception('Failed to read epub file');
        }
        $data = $zip->getFromName($path);

        return [
            'mime'  => $mime,
            'data'  => $data,
            'found' => $path,
        ];
    }

    /**
     * Summary of getCoverId
     * @return string|null
     */
    public function getCoverId()
    {
        $nodes = $this->xpath->query('//opf:metadata/opf:meta[@name="cover"]');
        if (!$nodes->length) {
            return null;
        }

        $coverid = (string) static::getAttr($nodes, 'opf:content');
        if (!$coverid) {
            return null;
        }

        return $coverid;
    }

    /**
     * Summary of getCoverItem
     * @return EpubDomElement|null
     */
    public function getCoverItem()
    {
        $coverid = $this->getCoverId();
        if (!$coverid) {
            return null;
        }

        $nodes = $this->xpath->query('//opf:manifest/opf:item[@id="' . $coverid . '"]');
        if (!$nodes->length) {
            return null;
        }

        /** @var EpubDomElement $node */
        $node = $nodes->item(0);
        return $node;
    }

    /**
     * Get the internal path of the cover image file.
     *
     * @return string|null
     */
    public function getCoverPath()
    {
        $item = $this->getCoverItem();
        if (!$item) {
            return null;
        }

        return $item->getAttrib('opf:href');
    }

    /**
     * Summary of Combine
     * @param mixed $a
     * @param mixed $b
     * @throws \InvalidArgumentException
     * @return string
     */
    public function Combine($a, $b)
    {
        $isAbsolute = false;
        if ($a[0] == '/') {
            $isAbsolute = true;
        }

        if ($b[0] == '/') {
            throw new InvalidArgumentException('Second path part must not start with /');
        }

        $splittedA = preg_split('#/#', $a);
        $splittedB = preg_split('#/#', $b);

        $pathParts = [];
        $mergedPath = array_merge($splittedA, $splittedB);

        foreach ($mergedPath as $item) {
            if ($item == null || $item == '' || $item == '.') {
                continue;
            }

            if ($item == '..') {
                array_pop($pathParts);
                continue;
            }

            array_push($pathParts, $item);
        }

        $path = implode('/', $pathParts);
        if ($isAbsolute) {
            return('/' . $path);
        } else {
            return($path);
        }
    }

    /**
     * Summary of getFullPath
     * @param mixed $file
     * @param mixed $context
     * @return string
     */
    private function getFullPath($file, $context = null)
    {
        $path = dirname('/' . $this->meta) . '/' . $file;
        $path = ltrim($path, '\\');
        $path = ltrim($path, '/');
        if (!empty($context)) {
            $path = $this->combine(dirname($path), $context);
        }
        //error_log ("FullPath : $path ($file / $context)");
        return $path;
    }

    /**
     * Summary of updateForKepub
     * @return void
     */
    public function updateForKepub()
    {
        $item = $this->getCoverItem();
        if (!is_null($item)) {
            $item->setAttrib('opf:properties', 'cover-image');
        }
    }

    /**
     * Summary of Cover2
     * @param mixed $path
     * @param mixed $mime
     * @return array<mixed>|void
     */
    public function Cover2($path=false, $mime=false)
    {
        $hascover = true;
        $item = $this->getCoverItem();
        if (is_null($item)) {
            $hascover = false;
        } else {
            $mime = $item->getAttrib('opf:media-type');
            $this->coverpath = $item->getAttrib('opf:href');
            $this->coverpath = dirname('/' . $this->meta) . '/' . $this->coverpath; // image path is relative to meta file
            $this->coverpath = ltrim($this->coverpath, '\\');
            $this->coverpath = ltrim($this->coverpath, '/');
        }

        // set cover
        if ($path !== false) {
            if (!$hascover) {
                return; // TODO For now only update
            }

            if ($path) {
                $item->setAttrib('opf:media-type', $mime);

                // remember path for save action
                $this->imagetoadd = $path;
            }

            $this->reparse();
        }

        if (!$hascover) {
            return $this->no_cover();
        }

        // not very useful here, but data gets added in download() if needed
        return [
            'data'  => null,
            'mime'  => $mime,
            'found' => $this->coverpath,
        ];
    }

    /**
     * Summary of getAttr
     * @param DOMNodeList<EpubDomElement> $nodes list of Element items
     * @param string $att Attribute name
     * @return string
     */
    protected static function getAttr($nodes, $att)
    {
        $node = $nodes->item(0);
        return $node->getAttrib($att);
    }

    /**
     * Summary of deleteNodes
     * @param DOMNodeList<EpubDomElement> $nodes list of Element items
     * @return void
     */
    protected static function deleteNodes($nodes)
    {
        foreach ($nodes as $node) {
            $node->delete();
        }
    }

    /**
     * A simple getter/setter for simple meta attributes
     *
     * It should only be used for attributes that are expected to be unique
     *
     * @param string $item   XML node to set/get
     * @param string|bool $value  New node value
     * @param string|bool $att    Attribute name
     * @param string|bool|array<mixed> $aval   Attribute value
     * @param string|bool $datt   Destination attribute
     * @return string|void
     */
    protected function getset($item, $value=false, $att=false, $aval=false, $datt=false)
    {
        // construct xpath
        $xpath = '//opf:metadata/' . $item;
        if ($att) {
            if (is_array($aval)) {
                $xpath .= '[@' . $att . '="';
                $xpath .= implode("\" or @$att=\"", $aval);
                $xpath .= '"]';
            } else {
                $xpath .= '[@' . $att . '="' . $aval . '"]';
            }
        }

        // set value
        if ($value !== false) {
            $value = htmlspecialchars($value);
            $nodes = $this->xpath->query($xpath);
            if ($nodes->length == 1) {
                /** @var EpubDomElement $node */
                $node = $nodes->item(0);
                if ($value === '') {
                    // the user want's to empty this value -> delete the node
                    $node->delete();
                } else {
                    // replace value
                    if ($datt) {
                        $node->setAttrib($datt, $value);
                    } else {
                        $node->nodeValue = $value;
                    }
                }
            } else {
                // if there are multiple matching nodes for some reason delete
                // them. we'll replace them all with our own single one
                static::deleteNodes($nodes);
                // readd them
                if ($value) {
                    /** @var EpubDomElement $parent */
                    $parent = $this->xpath->query('//opf:metadata')->item(0);

                    $node = $parent->newChild($item);
                    if ($att) {
                        $node->setAttrib($att, $aval);
                    }
                    if ($datt) {
                        $node->setAttrib($datt, $value);
                    } else {
                        $node->nodeValue = $value;
                    }
                }
            }

            $this->reparse();
        }

        // get value
        $nodes = $this->xpath->query($xpath);
        if ($nodes->length) {
            /** @var EpubDomElement $node */
            $node = $nodes->item(0);
            if ($datt) {
                return $node->getAttrib($datt);
            } else {
                return $node->nodeValue;
            }
        } else {
            return '';
        }
    }

    /**
     * Return a not found response for Cover()
     * @return array<string, mixed>
     */
    protected function no_cover()
    {
        return [
            'data'  => base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAEALAAAAAABAAEAAAIBTAA7'),
            'mime'  => 'image/gif',
            'found' => false,
        ];
    }

    /**
     * Reparse the DOM tree
     *
     * I had to rely on this because otherwise xpath failed to find the newly
     * added nodes
     * @return void
     */
    protected function reparse()
    {
        $this->xml->loadXML($this->xml->saveXML());
        $this->xpath = new EpubDomXPath($this->xml);
        // reset structural members
        $this->manifest = null;
        $this->spine = null;
        $this->tocnav = null;
    }

    /** based on slightly more updated version at https://github.com/epubli/epub */

    /**
     * A simple setter for simple meta attributes
     *
     * It should only be used for attributes that are expected to be unique
     *
     * @param string $item XML node to set
     * @param string $value New node value
     * @param bool|string $attribute Attribute name
     * @param bool|string $attributeValue Attribute value
     * @param bool $caseSensitive
     * @return mixed
     */
    private function setMeta($item, $value, $attribute = false, $attributeValue = false, $caseSensitive = true)
    {
        /**
        if ($attributeValue !== false && !$caseSensitive) {
            $attval = is_array($attributeValue) ? $attributeValue : [ $attributeValue ];
            $vallist = [];
            foreach ($attval as $val) {
                $vallist[] = strtoupper($val);
                $vallist[] = strtolower($val);
            }
            $attributeValue = $vallist;
        }
         */
        return $this->getset($item, $value, $attribute, $attributeValue);
    }

    /**
     * A simple getter for simple meta attributes
     *
     * It should only be used for attributes that are expected to be unique
     *
     * @param string $item XML node to get
     * @param bool|string $att Attribute name
     * @param bool|string $aval Attribute value
     * @param bool $caseSensitive
     * @return string
     */
    private function getMeta($item, $att = false, $aval = false, $caseSensitive = true)
    {
        /**
        if ($aval !== false && !$caseSensitive) {
            $attval = is_array($aval) ? $aval : [ $aval ];
            $vallist = [];
            foreach ($attval as $val) {
                $vallist[] = strtoupper($val);
                $vallist[] = strtolower($val);
            }
            $aval = $vallist;
        }
         */
        return $this->getset($item, false, $att, $aval);
    }

    /**
     * Set the book title
     *
     * @param string $title
     * @return mixed
     */
    public function setTitle($title)
    {
        return $this->getset('dc:title', $title);
    }

    /**
     * Get the book title
     *
     * @return mixed
     */
    public function getTitle()
    {
        return $this->getset('dc:title');
    }

    /**
     * Set the book's language
     *
     * @param string $lang
     * @return mixed
     */
    public function setLanguage($lang)
    {
        return $this->getset('dc:language', $lang);
    }

    /**
     * Get the book's language
     *
     * @return mixed
     */
    public function getLanguage()
    {
        return $this->getset('dc:language');
    }

    /**
     * Set the book's publisher info
     *
     * @param string $publisher
     * @return void
     */
    public function setPublisher($publisher)
    {
        $this->setMeta('dc:publisher', $publisher);
    }

    /**
     * Get the book's publisher info
     *
     * @return string
     */
    public function getPublisher()
    {
        return $this->getMeta('dc:publisher');
    }

    /**
     * Set the book's copyright info
     *
     * @param string $rights
     * @return void
     */
    public function setCopyright($rights)
    {
        $this->setMeta('dc:rights', $rights);
    }

    /**
     * Get the book's copyright info
     *
     * @return string
     */
    public function getCopyright()
    {
        return $this->getMeta('dc:rights');
    }

    /**
     * Set the book's description
     *
     * @param string $description
     * @return void
     */
    public function setDescription($description)
    {
        $this->setMeta('dc:description', $description);
    }

    /**
     * Get the book's description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->getMeta('dc:description');
    }

    /**
     * Set an identifier in the package file’s meta section.
     *
     * @param string|array<string> $idScheme The identifier’s scheme. If an array is given
     * all matching identifiers are replaced by one with the first value as scheme.
     * @param string $value
     * @param bool $caseSensitive
     * @return void
     */
    public function setIdentifier($idScheme, $value, $caseSensitive = false)
    {
        $this->setMeta('dc:identifier', $value, 'opf:scheme', $idScheme, $caseSensitive);
    }

    /**
     * Set an identifier from the package file’s meta section.
     *
     * @param string|array<string> $idScheme The identifier’s scheme. If an array is given
     * the scheme can be any of its values.
     * @param bool $caseSensitive - @todo changed to true here
     * @return string The value of the first matching element.
     */
    public function getIdentifier($idScheme, $caseSensitive = true)
    {
        return $this->getMeta('dc:identifier', 'opf:scheme', $idScheme, $caseSensitive);
    }

    /**
     * Set the book's unique identifier
     *
     * @param string $value
     * @return void
     */
    public function setUniqueIdentifier($value)
    {
        $idRef = $this->xpath->document->documentElement->getAttribute('unique-identifier');
        $this->setMeta('dc:identifier', $value, 'id', $idRef);
    }

    /**
     * Get the book's unique identifier
     *
     * @param bool $normalize
     * @return string
     */
    public function getUniqueIdentifier($normalize = false)
    {
        $idRef = $this->xpath->document->documentElement->getAttribute('unique-identifier');
        $idVal = $this->getMeta('dc:identifier', 'id', $idRef);
        if ($normalize) {
            $idVal = strtolower($idVal);
            $idVal = str_replace('urn:uuid:', '', $idVal);
        }

        return $idVal;
    }

    /**
     * Set the book's UUID - @todo pick one + case sensitive
     *
     * @param string $uuid
     * @return void
     */
    public function setUuid($uuid)
    {
        //$this->setIdentifier(['UUID', 'uuid', 'URN', 'urn'], $uuid);
        $this->setIdentifier('URN', $uuid);
    }

    /**
     * Get the book's UUID - @todo pick one + case sensitive
     *
     * @return string
     */
    public function getUuid()
    {
        //return $this->getIdentifier(['uuid', 'urn']);
        return $this->getIdentifier(['UUID', 'URN']);
    }

    /**
     * Set the book's URI
     *
     * @param string $uri
     * @return void
     */
    public function setUri($uri)
    {
        $this->setIdentifier('URI', $uri);
    }

    /**
     * Get the book's URI
     *
     * @return string
     */
    public function getUri()
    {
        return $this->getIdentifier('URI');
    }

    /**
     * Set the book's ISBN
     *
     * @param string $isbn
     * @return void
     */
    public function setIsbn($isbn)
    {
        $this->setIdentifier('ISBN', $isbn);
    }

    /**
     * Get the book's ISBN
     *
     * @return string
     */
    public function getIsbn()
    {
        return $this->getIdentifier('ISBN');
    }

    /**
     * Remove the cover image
     *
     * If the actual image file was added by this library it will be removed. Otherwise only the
     * reference to it is removed from the metadata, since the same image might be referenced
     * by other parts of the EPUB file.
     * @return void
     */
    public function clearCover()
    {
        if (!$this->hasCover()) {
            return;
        }

        $manifest = $this->getManifest();

        // remove any cover image file added by us
        if (isset($manifest[static::COVER_ID])) {
            $fullPath = $this->getFullPath(static::COVER_ID . '.img');
            if (!$this->zip->FileReplace($fullPath, false)) {
                throw new Exception('Unable to remove ' . $fullPath);
            }
        }

        // remove metadata cover pointer
        $nodes = $this->xpath->query('//opf:metadata/opf:meta[@name="cover"]');
        static::deleteNodes($nodes);

        // remove previous manifest entries if they where made by us
        $nodes = $this->xpath->query('//opf:manifest/opf:item[@id="' . static::COVER_ID . '"]');
        static::deleteNodes($nodes);

        $this->reparse();
    }

    /**
     * Set the cover image
     *
     * @param string $path local filesystem path to a new cover image
     * @param string $mime mime type of the given file
     * @return void
     */
    public function setCover($path, $mime)
    {
        if (!$path) {
            throw new InvalidArgumentException('Parameter $path must not be empty!');
        }

        if (!is_readable($path)) {
            throw new InvalidArgumentException("Cannot add $path as new cover image since that file is not readable!");
        }

        $this->clearCover();

        // add metadata cover pointer
        /** @var EpubDomElement $parent */
        $parent = $this->xpath->query('//opf:metadata')->item(0);
        $node = $parent->newChild('opf:meta');
        $node->setAttrib('opf:name', 'cover');
        $node->setAttrib('opf:content', static::COVER_ID);

        // add manifest item
        /** @var EpubDomElement $parent */
        $parent = $this->xpath->query('//opf:manifest')->item(0);
        $node = $parent->newChild('opf:item');
        $node->setAttrib('id', static::COVER_ID);
        $node->setAttrib('opf:href', static::COVER_ID . '.img');
        $node->setAttrib('opf:media-type', $mime);

        // add the cover image
        $fullPath = $this->getFullPath(static::COVER_ID . '.img');
        if (!$this->zip->FileAdd($fullPath, file_get_contents($path))) {
            throw new Exception('Unable to add ' . $fullPath);
        }

        $this->reparse();
    }

    /**
     * Get the cover image
     *
     * @return string|null The binary image data or null if no image exists.
     */
    public function getCover()
    {
        $comp = $this->getCoverPath();

        return $comp ? $this->component($comp) : null;
    }

    /**
     * Whether a cover image meta entry does exist.
     *
     * @return bool
     */
    public function hasCover()
    {
        return !empty($this->getCoverId());
    }

    /**
     * Get the manifest of this EPUB.
     *
     * @return Manifest
     * @throws Exception
     */
    public function getManifest()
    {
        if ($this->manifest) {
            return $this->manifest;
        }

        /** @var EpubDomElement|null $manifestNode */
        $manifestNode = $this->xpath->query('//opf:manifest')->item(0);
        if (is_null($manifestNode)) {
            throw new Exception('No manifest element found in EPUB!');
        }

        $this->manifest = new Manifest();
        /** @var EpubDomElement $item */
        foreach ($manifestNode->getElementsByTagName('item') as $item) {
            $id = $item->getAttribute('id');
            $href = urldecode($item->getAttribute('href'));
            $fullPath = $this->getFullPath($href);
            // this won't work with clsTbsZip - $this->zip->getStream($fullPath);
            //$handle = $this->zip->FileStream($fullPath);
            $callable = function () use ($fullPath): string|bool {
                // Automatic binding of $this
                return $this->zip->FileRead($fullPath);
            };
            $size = $this->zipSizeMap[$fullPath] ?? 0;
            $mediaType = $item->getAttribute('media-type');
            $this->manifest->createItem($id, $href, $callable, $size, $mediaType);
        }

        return $this->manifest;
    }

    /**
     * Get the spine structure of this EPUB.
     *
     * @return Spine
     * @throws Exception
     */
    public function getSpine()
    {
        if ($this->spine) {
            return $this->spine;
        }

        $nodes = $this->xpath->query('//opf:spine');
        if (!$nodes->length) {
            throw new Exception('No spine element found in EPUB!');
        }
        $tocId = static::getAttr($nodes, 'toc');
        $tocFormat = Toc::class;
        if (empty($tocId)) {
            $nodes = $this->xpath->query('//opf:manifest/opf:item[@properties="nav"]');
            $tocId = static::getAttr($nodes, 'id');
            $tocFormat = Nav::class;
        }

        $manifest = $this->getManifest();

        if (!isset($manifest[$tocId])) {
            throw new Exception('TOC or NAV item referenced in spine missing in manifest!');
        }

        $this->spine = new Spine($manifest[$tocId], $tocFormat);

        /** @var EpubDomElement $spineNode */
        $spineNode = $this->xpath->query('//opf:spine')->item(0);

        $itemRefNodes = $spineNode->getElementsByTagName('itemref');
        foreach ($itemRefNodes as $itemRef) {
            /** @var EpubDomElement $itemRef */
            $id = $itemRef->getAttribute('idref');
            if (!isset($manifest[$id])) {
                throw new Exception("Item $id referenced in spine missing in manifest!");
            }
            // Link the item from the manifest to the spine.
            $this->spine->appendItem($manifest[$id]);
        }

        return $this->spine;
    }

    /**
     * Get the table of contents structure of this EPUB.
     *
     * @return Toc|Nav
     * @throws Exception
     */
    public function getToc()
    {
        if ($this->tocnav) {
            return $this->tocnav;
        }

        // @todo support Nav structure as well, see initSpineComponent
        if ($this->getSpine()->getTocFormat() === Nav::class) {
            throw new Exception('TODO: support NAV structure as well');
        }

        $tocpath = $this->getFullPath($this->getSpine()->getTocItem()->getHref());
        $data = $this->zip->FileRead($tocpath);
        $toc = new DOMDocument();
        $toc->registerNodeClass('DOMElement', EpubDomElement::class);
        $toc->loadXML($data);
        $xpath = new EpubDomXPath($toc);
        //$rootNamespace = $toc->lookupNamespaceUri($toc->namespaceURI);
        //$xpath->registerNamespace('x', $rootNamespace);

        $titleNode = $xpath->query('//ncx:docTitle/ncx:text')->item(0);
        $title = $titleNode ? $titleNode->nodeValue : '';
        $authorNode = $xpath->query('//ncx:docAuthor/ncx:text')->item(0);
        $author = $authorNode ? $authorNode->nodeValue : '';
        $this->tocnav = new Toc($title, $author);

        $navPointNodes = $xpath->query('//ncx:navMap/ncx:navPoint');

        $this->loadNavPoints($navPointNodes, $this->tocnav->getNavMap(), $xpath);

        return $this->tocnav;
    }

    /**
     * Load navigation points from TOC XML DOM into TOC object structure.
     *
     * @param DOMNodeList<EPubDomElement> $navPointNodes List of nodes to load from.
     * @param TocNavPointList $navPointList List structure to load into.
     * @param EpubDomXPath $xp The XPath of the TOC document.
     * @return void
     */
    protected static function loadNavPoints(DOMNodeList $navPointNodes, TocNavPointList $navPointList, EpubDomXPath $xp)
    {
        foreach ($navPointNodes as $navPointNode) {
            /** @var EpubDomElement $navPointNode */
            $id = $navPointNode->getAttribute('id');
            $class = $navPointNode->getAttribute('class');
            $playOrder = (int) $navPointNode->getAttribute('playOrder');
            $labelTextNode = $xp->query('ncx:navLabel/ncx:text', $navPointNode)->item(0);
            $label = $labelTextNode ? $labelTextNode->nodeValue : '';
            /** @var EpubDomElement|null $contentNode */
            $contentNode = $xp->query('ncx:content', $navPointNode)->item(0);
            $contentSource = $contentNode ? $contentNode->getAttribute('src') : '';
            $navPoint = new TocNavPoint($id, $class, $playOrder, $label, $contentSource);
            $navPointList->addNavPoint($navPoint);
            $childNavPointNodes = $xp->query('ncx:navPoint', $navPointNode);
            $childNavPoints = $navPoint->getChildren();

            static::loadNavPoints($childNavPointNodes, $childNavPoints, $xp);
        }
    }

    /**
     * Summary of getNav
     * @return Toc|Nav
     */
    public function getNav()
    {
        if ($this->tocnav) {
            return $this->tocnav;
        }

        $navpath = $this->getFullPath($this->getSpine()->getTocItem()->getHref());
        $data = $this->zip->FileRead($navpath);
        $nav = new DOMDocument();
        $nav->registerNodeClass('DOMElement', EpubDomElement::class);
        $nav->loadXML($data);
        $xpath = new EpubDomXPath($nav);
        $rootNamespace = $nav->lookupNamespaceUri($nav->namespaceURI);
        $xpath->registerNamespace('x', $rootNamespace);

        // nav documents don't contain mandatory title or author - look in main doc
        $title = $this->getTitle();
        $author = implode(', ', $this->getAuthors());
        $this->tocnav = new Nav($title, $author);

        $toc = $xpath->query('//x:nav[@epub:type="toc"]')->item(0);
        $navListNodes = $xpath->query('//x:ol/x:li', $toc);
        if ($navListNodes->length > 0) {
            $this->loadNavList($navListNodes, $this->tocnav->getNavMap(), $xpath);
        }

        return $this->tocnav;
    }

    /**
     * Load navigation points from NAV XML DOM into NAV object structure.
     *
     * @param DOMNodeList<EPubDomElement> $navListNodes List of nodes to load from.
     * @param TocNavPointList $navPointList List structure to load into.
     * @param EpubDomXPath $xp The XPath of the NAV document.
     * @param int $depth Current depth of this list (recursive)
     * @param int $order Current start order for this list
     * @return void
     */
    protected static function loadNavList(DOMNodeList $navListNodes, TocNavPointList $navPointList, EpubDomXPath $xp, int $depth = 0, int $order = 0)
    {
        // h1 - h6 are supported as title for the list
        $className = 'h' . strval($depth + 1);
        if ($depth > 5) {
            throw new Exception("We're at maximum depth for NAV DOC here!?");
        }
        foreach ($navListNodes as $navPointNode) {
            $order += 1;
            $nodes = $xp->query('x:a', $navPointNode);
            $label = trim($nodes->item(0)->nodeValue);
            if (empty($label)) {
                // do we have an image with title or alt available?
                $images = $xp->query('x:a/x:img', $navPointNode);
                if ($images->length) {
                    $label = static::getAttr($images, 'alt');
                    if (empty($label)) {
                        $label = static::getAttr($images, 'title');
                    }
                }
            }
            $contentSource = static::getAttr($nodes, 'href');
            /** @var EpubDomElement $navPointNode */
            $id = $navPointNode->getAttribute('id');
            $class = $className;
            $playOrder = $order;
            $navPoint = new TocNavPoint($id, $class, $playOrder, $label, $contentSource);
            $navPointList->addNavPoint($navPoint);
            $childNavPointNodes = $xp->query('x:ol/x:li', $navPointNode);
            $childNavPoints = $navPoint->getChildren();

            static::loadNavList($childNavPointNodes, $childNavPoints, $xp, $depth + 1, $order);
        }
    }

    /**
     * Extract the contents of this EPUB.
     *
     * This concatenates contents of items according to their order in the spine.
     *
     * @param bool $keepMarkup Whether to keep the XHTML markup rather than extracted plain text.
     * @param float $fraction If less than 1, only the respective part from the beginning of the book is extracted.
     * @return string The contents of this EPUB.
     * @throws Exception
     */
    public function getContents($keepMarkup = false, $fraction = 1.0)
    {
        $contents = '';
        if ($fraction < 1) {
            $totalSize = 0;
            foreach ($this->getSpine() as $item) {
                $totalSize += $item->getSize();
            }
            $fractionSize = $totalSize * $fraction;
            $contentsSize = 0;
            foreach ($this->spine as $item) {
                $itemSize = $item->getSize();
                if ($contentsSize + $itemSize > $fractionSize) {
                    break;
                }
                $contentsSize += $itemSize;
                $contents .= $item->getContents(null, null, $keepMarkup);
            }
        } else {
            foreach ($this->getSpine() as $item) {
                $contents .= $item->getContents(null, null, $keepMarkup);
            }
        }

        return $contents;
    }

    /**
     * Get the stat entries for all files in a ZIP file
     *
     * @param string $file|null Path to a ZIP file or null for current file
     * @return array<mixed> (filename => details of the entry)
     */
    public function getZipEntries($file=null)
    {
        $file ??= $this->file;
        $entries = [];

        $zip = new ZipArchive();
        $result = $zip->open($file, ZipArchive::RDONLY);
        if ($result !== true) {
            throw new Exception("Unable to open file", $result);
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $entries[$stat['name']] = $stat;
        }
        $zip->close();

        return $entries;
    }

    /**
     * Map the items of a ZIP file to their respective file sizes.
     *
     * @param string $file|null Path to a ZIP file or null for current ZIP file
     * @return array<mixed> (filename => file size)
     */
    protected function loadSizeMap($file=null)
    {
        $entries = $this->getZipEntries($file);

        $sizeMap = [];
        foreach ($entries as $filename => $entry) {
            $sizeMap[$filename] = $entry['size'];
        }

        return $sizeMap;
    }

    /**
     * @return int
     */
    public function getImageCount()
    {
        $entries = $this->getZipEntries();
        $images = array_filter($entries, static function ($k) {
            return preg_match('/(.jpeg|.jpg|.png|.gif)/', $k);
        }, ARRAY_FILTER_USE_KEY);

        return count($images);
    }
}

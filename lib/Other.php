<?php
/**
 * Representation of an EPUB document.
 *
 * @author Andreas Gohr <andi@splitbrain.org> © 2012
 * @author Simon Schrape <simon@epubli.com> © 2015
 */

//namespace Epubli\Epub;
namespace SebLucas\EPubMeta;

use DOMDocument;
use DOMNodeList;
use Exception;
use InvalidArgumentException;
use ZipArchive;

/**
 * @todo These are the methods that haven't been integrated with EPub here...
 */
class Other extends EPub
{
    /**
     * Remove the cover image
     *
     * If the actual image file was added by this library it will be removed. Otherwise only the
     * reference to it is removed from the metadata, since the same image might be referenced
     * by other parts of the EPUB file.
     */
    public function clearCover()
    {
        if (!$this->hasCover()) {
            return;
        }

        // remove any cover image file added by us
        $this->zip->deleteName($this->packageDir . self::COVER_ID . '.img');

        // remove metadata cover pointer
        $nodes = $this->xpath->query('//opf:metadata/opf:meta[@name="cover"]');
        foreach ($nodes as $node) {
            /** @var EpubDomElement $node */
            $node->delete();
        }

        // remove previous manifest entries if they where made by us
        $nodes = $this->xpath->query('//opf:manifest/opf:item[@id="' . self::COVER_ID . '"]');
        foreach ($nodes as $node) {
            /** @var EpubDomElement $node */
            $node->delete();
        }

        $this->sync();
    }

    /**
     * Set the cover image
     *
     * @param string $path local filesystem path to a new cover image
     * @param string $mime mime type of the given file
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
        $node->setAttrib('opf:content', self::COVER_ID);

        // add manifest item
        $parent = $this->xpath->query('//opf:manifest')->item(0);
        $node = $parent->newChild('opf:item');
        $node->setAttrib('id', self::COVER_ID);
        $node->setAttrib('opf:href', self::COVER_ID . '.img');
        $node->setAttrib('opf:media-type', $mime);

        // add the cover image
        $this->zip->addFile($path, $this->packageDir . self::COVER_ID . '.img');

        $this->sync();
    }

    /**
     * Get the cover image
     *
     * @return string|null The binary image data or null if no image exists.
     */
    public function getCover()
    {
        $item = $this->getCoverItem();

        return $item ? $item->getData() : null;
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
     * Add a title page with the cover image to the EPUB.
     *
     * @param string $templatePath The path to the template file. Defaults to an XHTML file contained in this library.
     */
    public function addCoverImageTitlePage($templatePath = __DIR__ . '/../templates/titlepage.xhtml')
    {
        $xhtmlFilename = self::TITLE_PAGE_ID . '.xhtml';

        // add title page file to zip
        $template = file_get_contents($templatePath);
        $xhtml = strtr($template, ['{{ title }}' => $this->getTitle(), '{{ coverPath }}' => $this->getCoverPath()]);
        $this->zip->addFromString($this->packageDir . $xhtmlFilename, $xhtml);

        // prepend title page file to manifest
        $parent = $this->packageXPath->query('//opf:manifest')->item(0);
        $node = new EpubDomElement('opf:item');
        $parent->insertBefore($node, $parent->firstChild);
        $node->setAttrib('id', self::TITLE_PAGE_ID);
        $node->setAttrib('opf:href', $xhtmlFilename);
        $node->setAttrib('opf:media-type', 'application/xhtml+xml');

        // prepend title page spine item
        $parent = $this->packageXPath->query('//opf:spine')->item(0);
        $node = new EpubDomElement('opf:itemref');
        $parent->insertBefore($node, $parent->firstChild);
        $node->setAttrib('idref', self::TITLE_PAGE_ID);

        // prepend title page guide reference
        $parent = $this->packageXPath->query('//opf:guide')->item(0);
        $node = new EpubDomElement('opf:reference');
        $parent->insertBefore($node, $parent->firstChild);
        $node->setAttrib('opf:href', $xhtmlFilename);
        $node->setAttrib('opf:type', 'cover');
        $node->setAttrib('opf:title', 'Title Page');
    }

    /**
     * Remove the title page added by this library (determined by a certain manifest item ID).
     */
    public function removeTitlePage()
    {
        $xhtmlFilename = self::TITLE_PAGE_ID . '.xhtml';

        // remove title page file from zip
        $this->zip->deleteName($this->packageDir . $xhtmlFilename);

        // remove title page file from manifest
        $nodes = $this->packageXPath->query('//opf:manifest/opf:item[@id="' . self::TITLE_PAGE_ID . '"]');
        foreach ($nodes as $node) {
            /** @var EpubDomElement $node */
            $node->delete();
        }

        // remove title page spine item
        $nodes = $this->packageXPath->query('//opf:spine/opf:itemref[@idref="' . self::TITLE_PAGE_ID . '"]');
        foreach ($nodes as $node) {
            /** @var EpubDomElement $node */
            $node->delete();
        }

        // remove title page guide reference
        $nodes = $this->packageXPath->query('//opf:guide/opf:reference[@href="' . $xhtmlFilename . '"]');
        foreach ($nodes as $node) {
            /** @var EpubDomElement $node */
            $node->delete();
        }
    }

    /**
     * Get the manifest of this EPUB.
     *
     * @return Manifest
     * @throws Exception
     */
    public function getManifest()
    {
        if (!$this->manifest) {
            /** @var DOMElement $manifestNode */
            $manifestNode = $this->packageXPath->query('//opf:manifest')->item(0);
            if (is_null($manifestNode)) {
                throw new Exception('No manifest element found in EPUB!');
            }

            $this->manifest = new Manifest();
            /** @var DOMElement $item */
            foreach ($manifestNode->getElementsByTagName('item') as $item) {
                $id = $item->getAttribute('id');
                $href = urldecode($item->getAttribute('href'));
                $fullPath = $this->packageDir . $href;
                $handle = $this->zip->getStream($fullPath);
                $size = isset($this->zipSizeMap[$fullPath]) ? $this->zipSizeMap[$fullPath] : 0;
                $mediaType = $item->getAttribute('media-type');
                $this->manifest->createItem($id, $href, $handle, $size, $mediaType);
            }
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
        if (!$this->spine) {
            /** @var DOMElement $spineNode */
            $spineNode = $this->packageXPath->query('//opf:spine')->item(0);
            if (is_null($spineNode)) {
                throw new Exception('No spine element found in EPUB!');
            }

            $manifest = $this->getManifest();

            // Get the TOC item.
            $tocId = $spineNode->getAttribute('toc');
            if (empty($tocId)) {
                throw new Exception('No TOC ID given in spine!');
            }
            if (!isset($manifest[$tocId])) {
                throw new Exception('TOC item referenced in spine missing in manifest!');
            }

            $this->spine = new Spine($manifest[$tocId]);

            $itemRefNodes = $spineNode->getElementsByTagName('itemref');
            foreach ($itemRefNodes as $itemRef) {
                /** @var DOMElement $itemRef */
                $id = $itemRef->getAttribute('idref');
                if (!isset($manifest[$id])) {
                    throw new Exception("Item $id referenced in spine missing in manifest!");
                }
                // Link the item from the manifest to the spine.
                $this->spine->appendItem($manifest[$id]);
            }
        }

        return $this->spine;
    }

    /**
     * Get the table of contents structure of this EPUB.
     *
     * @return Toc
     * @throws Exception
     */
    public function getToc()
    {
        if (!$this->toc) {
            $xp = $this->loadXPathFromItem($this->packageDir . $this->getSpine()->getTocItem()->getHref());
            $titleNode = $xp->query('//ncx:docTitle/ncx:text')->item(0);
            $title = $titleNode ? $titleNode->nodeValue : '';
            $authorNode = $xp->query('//ncx:docAuthor/ncx:text')->item(0);
            $author = $authorNode ? $authorNode->nodeValue : '';
            $this->toc = new Toc($title, $author);

            $navPointNodes = $xp->query('//ncx:navMap/ncx:navPoint');

            $this->loadNavPoints($navPointNodes, $this->toc->getNavMap(), $xp);
        }

        return $this->toc;
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
     * A simple setter for simple meta attributes
     *
     * It should only be used for attributes that are expected to be unique
     *
     * @param string $item XML node to set
     * @param string $value New node value
     * @param bool|string $attribute Attribute name
     * @param bool|string $attributeValue Attribute value
     * @param bool $caseSensitive
     */
    private function setMeta($item, $value, $attribute = false, $attributeValue = false, $caseSensitive = true)
    {
        $xpath = $this->buildMetaXPath($item, $attribute, $attributeValue, $caseSensitive);

        // set value
        $nodes = $this->packageXPath->query($xpath);
        if ($nodes->length == 1) {
            /** @var EpubDomElement $node */
            $node = $nodes->item(0);
            if ($value === '') {
                // the user wants to empty this value -> delete the node
                $node->delete();
            } else {
                // replace value
                $node->nodeValueUnescaped = $value;
            }
        } else {
            // if there are multiple matching nodes for some reason delete
            // them. we'll replace them all with our own single one
            foreach ($nodes as $node) {
                /** @var EpubDomElement $node */
                $node->delete();
            }
            // re-add them
            if ($value) {
                $parent = $this->packageXPath->query('//opf:metadata')->item(0);
                $node = new EpubDomElement($item, $value);
                $node = $parent->appendChild($node);
                if ($attribute) {
                    if (is_array($attributeValue)) {
                        // use first given value for new attribute
                        $attributeValue = reset($attributeValue);
                    }
                    $node->setAttrib($attribute, $attributeValue);
                }
            }
        }

        $this->sync();
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
        $xpath = $this->buildMetaXPath($item, $att, $aval, $caseSensitive);

        // get value
        $nodes = $this->packageXPath->query($xpath);
        if ($nodes->length) {
            /** @var EpubDomElement $node */
            $node = $nodes->item(0);

            return $node->nodeValueUnescaped;
        } else {
            return '';
        }
    }

    /**
     * Build an XPath expression to select certain nodes in the metadata section.
     *
     * @param string $element The node name of the elements to select.
     * @param string $attribute If set, the attribute required in the element.
     * @param string|array $value If set, the value of the above named attribute. If an array is given
     * all of its values will be allowed in the selector.
     * @param bool $caseSensitive If false, attribute values are matched case insensitively.
     * (This is not completely true, as only full upper or lower case strings are matched, not mixed case.
     * A lower-case function is missing in XPath 1.0.)
     * @return string
     */
    private function buildMetaXPath($element, $attribute, $value, $caseSensitive = true)
    {
        $xpath = '//opf:metadata/'.$element;
        if ($attribute) {
            $xpath .= "[@$attribute";
            if ($value) {
                $values = is_array($value) ? $value : [$value];
                if (!$caseSensitive) {
                    $temp = [];
                    foreach ($values as $item) {
                        $temp[] = strtolower($item);
                        $temp[] = strtoupper($item);
                    }
                    $values = $temp;
                }

                $xpath .= '="';
                $xpath .= implode("\" or @$attribute=\"", $values);
                $xpath .= '"';
            }
            $xpath .= ']';
        }

        return $xpath;
    }

    /**
     * Load navigation points from TOC XML DOM into TOC object structure.
     *
     * @param DOMNodeList $navPointNodes List of nodes to load from.
     * @param TocNavPointList $navPointList List structure to load into.
     * @param DOMXPath $xp The XPath of the TOC document.
     */
    private static function loadNavPoints(DOMNodeList $navPointNodes, TocNavPointList $navPointList, DOMXPath $xp)
    {
        foreach ($navPointNodes as $navPointNode) {
            /** @var DOMElement $navPointNode */
            $id = $navPointNode->getAttribute('id');
            $class = $navPointNode->getAttribute('class');
            $playOrder = $navPointNode->getAttribute('playOrder');
            $labelTextNode = $xp->query('ncx:navLabel/ncx:text', $navPointNode)->item(0);
            $label = $labelTextNode ? $labelTextNode->nodeValue : '';
            /** @var DOMElement $contentNode */
            $contentNode = $xp->query('ncx:content', $navPointNode)->item(0);
            $contentSource = $contentNode ? $contentNode->getAttribute('src') : '';
            $navPoint = new TocNavPoint($id, $class, $playOrder, $label, $contentSource);
            $navPointList->addNavPoint($navPoint);
            $childNavPointNodes = $xp->query('ncx:navPoint', $navPointNode);
            $childNavPoints = $navPoint->getChildren();

            self::loadNavPoints($childNavPointNodes, $childNavPoints, $xp);
        }
    }

    /**
     * Load an XML file from the EPUB/ZIP archive into a new XPath object.
     *
     * @param $path string The XML file to load from the ZIP archive.
     * @return EpubDomXPath The XPath representation of the XML file.
     * @throws Exception If the given path could not be read.
     */
    private function loadXPathFromItem($path)
    {
        $data = $this->zip->getFromName($path);
        if (!$data) {
            throw new Exception("Failed to read from EPUB container: $path.");
        }
        $xml = new DOMDocument();
        $xml->registerNodeClass(DOMElement::class, EpubDomElement::class);
        $xml->loadXML($data);

        return new EpubDomXPath($xml);
    }

    /**
     * Get the identifier of the cover image manifest item.
     *
     * @return null|string
     */
    private function getCoverId()
    {
        $nodes = $this->packageXPath->query('//opf:metadata/opf:meta[@name="cover"]');
        if (!$nodes->length) {
            return null;
        }
        /** @var EpubDomElement $node */
        $node = $nodes->item(0);

        return (String)$node->getAttrib('opf:content');
    }

    /**
     * Get the manifest item identified as cover image.
     *
     * @return DataItem|null
     */
    private function getCoverItem()
    {
        $coverId = $this->getCoverId();
        if (!$coverId) {
            return null;
        }
        try {
            $manifest = $this->getManifest();
        } catch (Exception $e) {
            return null;
        }
        if (!isset($manifest[$coverId])) {
            return null;
        }

        return $manifest[$coverId];
    }

    /**
     * Get the internal path of the cover image file.
     *
     * @return string|null
     */
    private function getCoverPath()
    {
        $item = $this->getCoverItem();
        if (!$item) {
            return null;
        }

        return $item->getHref();
    }

    /**
     * Sync XPath object with updated DOM.
     */
    private function sync()
    {
        $dom = $this->packageXPath->document;
        $dom->loadXML($dom->saveXML());
        $this->packageXPath = new EpubDomXPath($dom);
        // reset structural members
        $this->manifest = null;
        $this->spine = null;
        $this->toc = null;
    }

    /**
     * Map the items of a ZIP file to their respective file sizes.
     *
     * @param string $file Path to a ZIP file
     * @return array (filename => file size)
     */
    private function loadSizeMap($file)
    {
        $sizeMap = [];

        $zip = new ZipArchive();
        $result = $zip->open($file);
        if ($result !== true) {
            throw new Exception("Unable to open file", $result);
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $sizeMap[$stat['name']] = $stat['size'];
        }
        $zip->close();

        return $sizeMap;
    }

    /**
     * @return int
     */
    public function getImageCount()
    {
        $images = array_filter($this->zipSizeMap, static function($k){
            return preg_match('/(.jpeg|.jpg|.png|.gif)/', $k);
        }, ARRAY_FILTER_USE_KEY);

        return count($images);
    }
}

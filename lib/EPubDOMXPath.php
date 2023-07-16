<?php
/**
 * PHP EPub Meta library
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Sébastien Lucas <sebastien@slucas.fr>
 */

namespace SebLucas\EPubMeta;

use DOMDocument;
use DOMXPath;

class EPubDOMXPath extends DOMXPath
{
    public function __construct(DOMDocument $doc)
    {
        parent::__construct($doc);

        $element = $doc->documentElement;
        if ($element instanceof EPubDOMElement) {
            foreach ($element->namespaces as $ns => $url) {
                $this->registerNamespace($ns, $url);
            }
        }
    }
}

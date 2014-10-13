<?php
/**
 *
 * 
 *
 * Generated by <a href="http://enunciate.codehaus.org">Enunciate</a>.
 *
 */

namespace Gedcomx\Common;

use Gedcomx\Links\HypermediaEnabledData;

/**
 * A note about a genealogical resource (e.g. conclusion or source).
 */
class Note extends HypermediaEnabledData
{

    /**
     * The language of the note.
     *
     * @var string
     */
    private $lang;

    /**
     * The subject of the note.
     *
     * @var string
     */
    private $subject;

    /**
     * The text of the note.
     *
     * @var string
     */
    private $text;

    /**
     * Attribution metadata for a note.
     *
     * @var \Gedcomx\Common\Attribution
     */
    private $attribution;

    /**
     * Constructs a Note from a (parsed) JSON hash
     *
     * @param mixed $o Either an array (JSON) or an XMLReader.
     *
     * @throws \Exception
     */
    public function __construct($o = null)
    {
        if (is_array($o)) {
            $this->initFromArray($o);
        }
        else if ($o instanceof \XMLReader) {
            $success = true;
            while ($success && $o->nodeType != \XMLReader::ELEMENT) {
                $success = $o->read();
            }
            if ($o->nodeType != \XMLReader::ELEMENT) {
                throw new \Exception("Unable to read XML: no start element found.");
            }

            $this->initFromReader($o);
        }
    }

    /**
     * The language of the note.
     *
     * @return string
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * The language of the note.
     *
     * @param string $lang
     */
    public function setLang($lang)
    {
        $this->lang = $lang;
    }
    /**
     * The subject of the note.
     *
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * The subject of the note.
     *
     * @param string $subject
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
    }
    /**
     * The text of the note.
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * The text of the note.
     *
     * @param string $text
     */
    public function setText($text)
    {
        $this->text = $text;
    }
    /**
     * Attribution metadata for a note.
     *
     * @return \Gedcomx\Common\Attribution
     */
    public function getAttribution()
    {
        return $this->attribution;
    }

    /**
     * Attribution metadata for a note.
     *
     * @param \Gedcomx\Common\Attribution $attribution
     */
    public function setAttribution($attribution)
    {
        $this->attribution = $attribution;
    }
    /**
     * Returns the associative array for this Note
     *
     * @return array
     */
    public function toArray()
    {
        $a = parent::toArray();
        if ($this->lang) {
            $a["lang"] = $this->lang;
        }
        if ($this->subject) {
            $a["subject"] = $this->subject;
        }
        if ($this->text) {
            $a["text"] = $this->text;
        }
        if ($this->attribution) {
            $a["attribution"] = $this->attribution->toArray();
        }
        return $a;
    }


    /**
     * Initializes this Note from an associative array
     *
     * @param array $o
     */
    public function initFromArray($o)
    {
        parent::initFromArray($o);
        if (isset($o['lang'])) {
            $this->lang = $o["lang"];
        }
        if (isset($o['subject'])) {
            $this->subject = $o["subject"];
        }
        if (isset($o['text'])) {
            $this->text = $o["text"];
        }
        if (isset($o['attribution'])) {
            if($o['attribution'] instanceof Attribution ){
                $this->attribution = $o['attribution'];
            } else {
                $this->attribution = new Attribution($o["attribution"]);
            }
        }
    }

    /**
     * Sets a known child element of Note from an XML reader.
     *
     * @param \XMLReader $xml The reader.
     * @return bool Whether a child element was set.
     */
    protected function setKnownChildElement($xml) {
        $happened = parent::setKnownChildElement($xml);
        if ($happened) {
          return true;
        }
        else if (($xml->localName == 'subject') && ($xml->namespaceURI == 'http://gedcomx.org/v1/')) {
            $child = '';
            while ($xml->read() && $xml->hasValue) {
                $child = $child . $xml->value;
            }
            $this->subject = $child;
            $happened = true;
        }
        else if (($xml->localName == 'text') && ($xml->namespaceURI == 'http://gedcomx.org/v1/')) {
            $child = '';
            while ($xml->read() && $xml->hasValue) {
                $child = $child . $xml->value;
            }
            $this->text = $child;
            $happened = true;
        }
        else if (($xml->localName == 'attribution') && ($xml->namespaceURI == 'http://gedcomx.org/v1/')) {
            $child = new \Gedcomx\Common\Attribution($xml);
            $this->attribution = $child;
            $happened = true;
        }
        return $happened;
    }

    /**
     * Sets a known attribute of Note from an XML reader.
     *
     * @param \XMLReader $xml The reader.
     * @return bool Whether an attribute was set.
     */
    protected function setKnownAttribute($xml) {
        if (parent::setKnownAttribute($xml)) {
            return true;
        }
        else if (($xml->localName == 'lang') && ($xml->namespaceURI == 'http://www.w3.org/XML/1998/namespace')) {
            $this->lang = $xml->value;
            return true;
        }

        return false;
    }

    /**
     * Writes this Note to an XML writer.
     *
     * @param \XMLWriter $writer The XML writer.
     * @param bool $includeNamespaces Whether to write out the namespaces in the element.
     */
    public function toXml($writer, $includeNamespaces = true)
    {
        $writer->startElementNS('gx', 'note', null);
        if ($includeNamespaces) {
            $writer->writeAttributeNs('xmlns', 'gx', null, 'http://gedcomx.org/v1/');
            $writer->writeAttributeNs('xmlns', 'xml', null, 'http://www.w3.org/XML/1998/namespace');
        }
        $this->writeXmlContents($writer);
        $writer->endElement();
    }

    /**
     * Writes the contents of this Note to an XML writer. The startElement is expected to be already provided.
     *
     * @param \XMLWriter $writer The XML writer.
     */
    public function writeXmlContents($writer)
    {
        if ($this->lang) {
            $writer->writeAttribute('xml:lang', $this->lang);
        }
        parent::writeXmlContents($writer);
        if ($this->subject) {
            $writer->startElementNs('gx', 'subject', null);
            $writer->text($this->subject);
            $writer->endElement();
        }
        if ($this->text) {
            $writer->startElementNs('gx', 'text', null);
            $writer->text($this->text);
            $writer->endElement();
        }
        if ($this->attribution) {
            $writer->startElementNs('gx', 'attribution', null);
            $this->attribution->writeXmlContents($writer);
            $writer->endElement();
        }
    }
}

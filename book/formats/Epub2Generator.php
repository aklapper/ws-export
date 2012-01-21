<?php
/**
* @author Thomas Pellissier Tanon
* @copyright 2011 Thomas Pellissier Tanon
* @licence http://www.gnu.org/licenses/gpl.html GNU General Public Licence
*/

/**
* create an epub 2 file
* @see http://idpf.org/epub/201
*/
class Epub2Generator implements Generator {

        protected $withCss = false;
        protected $linksList = array();

        /**
        * array key/value that contain translated strings
        */
        protected $i18n = array();

        /**
        * return the extension of the generated file
        * @return string
        */
        public function getExtension() {
                return 'epub';
        }

        /**
        * return the mimetype of the generated file
        * @return string
        */
        public function getMimeType() {
                return 'application/epub+zip';
        }

        /**
        * create the file
        * @var $data Book the title of the main page of the book in Wikisource
        * @return string
        * @todo images, cover, about...
        */
        public function create(Book $book) {
                $css = $this->getCssWikisource($book->lang);
                $this->i18n = $this->getI18n($book->lang);
                setLocale(LC_TIME, $book->lang . '_' . strtoupper($book->lang));
                $wsUrl = wikisourceUrl($book->lang, $book->title);
                if($css != '')
                        $this->withCss = true;
                $this->encodeTitles($book);
                $this->clean($book);
                $zip = new ZipCreator();
                $zip->addContentFile('mimetype', 'application/epub+zip', null, false); //the mimetype must be first and uncompressed
                $zip->addContentFile('META-INF/container.xml', $this->getXmlContainer());
                $zip->addContentFile('OPS/content.opf', $this->getOpfContent($book, $wsUrl));
                $zip->addContentFile('OPS/toc.ncx', $this->getNcxToc($book));
                if($book->cover != '')
                        $zip->addContentFile('OPS/cover.xhtml', $this->getXhtmlCover($book));
                $zip->addContentFile('OPS/title.xhtml', $this->getXhtmlTitle($book));
                $zip->addFile(dirname(__FILE__).'/images/Accueil_scribe.png', 'OPS/Accueil_scribe.png');
                $zip->addContentFile('OPS/' . $book->title . '.xhtml', $book->content->saveXML());
                if(!empty($book->chapters)) {
                        foreach($book->chapters as $chapter) {
                                $zip->addContentFile('OPS/' . $chapter->title . '.xhtml', $chapter->content->saveXML());
                        }
                }
                foreach($book->pictures as $picture) {
                        $zip->addContentFile('OPS/' . $picture->title, $picture->content);
                }
                if($this->withCss)
                        $zip->addContentFile('OPS/main.css', $css);
                return $zip->getContent();
        }

        protected function getXmlContainer() {
                $content = '<?xml version="1.0" encoding="UTF-8" ?>
                        <container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
                                <rootfiles>
                                        <rootfile full-path="OPS/content.opf" media-type="application/oebps-package+xml" />
                                </rootfiles>
                        </container>';
                return $content;
        }

        protected function getOpfContent(Book $book, $wsUrl) {
                $content = '<?xml version="1.0" encoding="UTF-8" ?>
                        <package xmlns="http://www.idpf.org/2007/opf" unique-identifier="uid" version="2.0">
                                <metadata xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:opf="http://www.idpf.org/2007/opf" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:dcterms="http://purl.org/dc/terms/">
                                        <dc:identifier id="uid" opf:scheme="URI">' . $wsUrl . '</dc:identifier>
                                        <dc:language xsi:type="dcterms:RFC4646">' . $book->lang . '</dc:language>
                                        <dc:title>' . $book->name . '</dc:title>
                                        <dc:source>' . $wsUrl . '</dc:source>
                                        <dc:date opf:event="ops-publication">' . date(DATE_W3C) . '</dc:date>
                                        <dc:rights>http://creativecommons.org/licenses/by-sa/3.0/</dc:rights>
                                        <dc:rights>http://www.gnu.org/copyleft/fdl.html</dc:rights>
                                        <dc:contributor opf:role="bkp">Wikisource</dc:contributor>';
                                if($book->author != '') {
                                        $content.= '<dc:creator opf:role="aut">' . $book->author . '</dc:creator>';
                                }
                                if($book->translator != '') {
                                        $content.= '<dc:contributor opf:role="trl">' . $book->translator . '</dc:contributor>';
                                }
                                if($book->illustrator != '') {
                                        $content.= '<dc:contributor opf:role="ill">' . $book->illustrator . '</dc:contributor>';
                                }
                                if($book->publisher != '') {
                                        $content.= '<dc:publisher>' . $book->publisher . '</dc:publisher>';
                                }
                                if($book->year != '') {
                                        $content.= '<dc:date opf:event="original-publication">' . $book->year . '</dc:date>';
                                }
                                if($book->cover != '')
                                        $content.= '<meta name="cover" content="cover" />';
                                else
                                        $content.= '<meta name="cover" content="title" />';
                                $content.= '</metadata>
                                <manifest>
                                        <item href="toc.ncx" id="ncx" media-type="application/x-dtbncx+xml"/>';
                                        if($book->cover != '')
                                                $content.= '<item id="cover" href="cover.xhtml" media-type="application/xhtml+xml" />';
                                        $content.= '<item id="title" href="title.xhtml" media-type="application/xhtml+xml" />
                                        <item id="Accueil_scribe.png" href="Accueil_scribe.png" media-type="image/png" />
                                        <item id="' . $book->title . '" href="' . $book->title . '.xhtml" media-type="application/xhtml+xml" />';
                                        if(!empty($book->chapters)) {
                                                foreach($book->chapters as $chapter) {
                                                        $content.= '<item id="' . $chapter->title . '" href="' . $chapter->title . '.xhtml" media-type="application/xhtml+xml" />';
                                                }
                                        }
                                        foreach($book->pictures as $picture) {
                                                $content.= '<item id="' . $picture->title . '" href="' . $picture->title . '" media-type="' . $picture->mimetype . '" />';
                                        }
                                        if($this->withCss)
                                                $content.= '<item id="mainCss" href="main.css" media-type="text/css" />';
                                        //$content.= '<item id="about" href="about.xhtml" media-type="application/xhtml+xml" /> //TODO: about...
                                $content.= '</manifest>
                                <spine toc="ncx">';
                                        if($book->cover != '')
                                                $content.= '<itemref idref="cover" linear="yes" />';
                                        $content.= '<itemref idref="title" linear="yes" />
                                        <itemref idref="' . $book->title . '" linear="yes" />';
                                        if(!empty($book->chapters)) {
                                                foreach($book->chapters as $chapter) {
                                                        $content.= '<itemref idref="' . $chapter->title . '" linear="yes" />';
                                                }
                                        }
                                        //$content.= '<itemref idref="about" linear="no" />
                                $content.= '</spine>
                                <guide>';
                                        if($book->cover != '')
                                                $content.= '<reference type="cover" title="' . $this->i18n['cover'] . '" href="cover.xhtml" />';
                                        else
                                                $content.= '<reference type="cover" title="' . $this->i18n['cover'] . '" href="title.xhtml" />';
                                        $content.= '<reference type="title-page" title="' . $this->i18n['title_page'] . '" href="title.xhtml" />
                                        <reference type="text" title="' . $book->name . '" href="' . $book->title . '.xhtml" />';
                                        if(isset($book->chapters[0])) {
                                                $content.= '<reference type="text" title="' . $book->chapters[0]->name . '" href="' . $book->chapters[0]->title . '.xhtml" />';
                                        }
                                        //$content.= '<reference type="copyright-page" title="' . $this->i18n['about'] . '" href="about.xml"/>
                                $content.= '</guide>
                        </package>';
                return $content;
        }

        protected function getNcxToc(Book $book) {
                $content = '<?xml version="1.0" encoding="UTF-8" ?>
                        <!DOCTYPE ncx PUBLIC "-//NISO//DTD ncx 2005-1//EN" "http://www.daisy.org/z3986/2005/ncx-2005-1.dtd">
                        <ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
                                <head>
                                        <meta name="dtb:uid" content="' . wikisourceUrl($book->lang, $book->title) . '" />
                                        <meta name="dtb:depth" content="1" />
                                        <meta name="dtb:totalPageCount" content="0" />
                                        <meta name="dtb:maxPageNumber" content="0" />
                                </head>
                                <docTitle><text>' . $book->name . '</text></docTitle>
                                <docAuthor><text>' . $book->author . '</text></docAuthor>
                                <navMap>
                                        <navPoint id="title" playOrder="1">
                                                <navLabel><text>' . $this->i18n['title_page'] . '</text></navLabel>
                                                <content src="title.xhtml"/>
                                        </navPoint>
                                        <navPoint id="' . $book->title . '" playOrder="2">
                                                <navLabel><text>' . $book->name . '</text></navLabel>
                                                <content src="' . $book->title . '.xhtml"/>
                                        </navPoint>';
                                        $order = 3;
                                        if(!empty($book->chapters)) {
                                                foreach($book->chapters as $chapter) {
                                                         $content.= '<navPoint id="' . $chapter->title . '" playOrder="' . $order . '">
                                                                        <navLabel><text>' . $chapter->name . '</text></navLabel>
                                                                        <content src="' . $chapter->title . '.xhtml"/>
                                                                </navPoint>';
                                                         $order++;
                                                }
                                        }
                                        /* $content.= '<navPoint id="about" playOrder="' . $order . '">
                                                <navLabel>
                                                        <text>' . $this->i18n['about'] . '</text>
                                                </navLabel>
                                                <content src="about.xhtml"/>
                                        </navPoint> */
                                $content.= '</navMap>
                        </ncx>';
                return $content;
        }

        protected function getXhtmlCover(Book $book) {
                $content = '<?xml version="1.0" encoding="UTF-8" ?>
                        <!DOCTYPE html>
                        <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="' . $book->lang . '">
                                <head>
                                        <title>' . $book->name . '</title>
                                        <meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8" />
                                </head>
                                <body>
                                        <div style="text-align: center; page-break-after: always;">
                                                <img src="' . $book->pictures[$book->cover]->title . '" alt="Cover" style="height: 100%; max-width: 100%;" />
                                        </div>
                                </body>
                        </html>';
                return $content;
        }

        protected function getXhtmlTitle(Book $book) {
                $content = '<?xml version="1.0" encoding="UTF-8" ?>
                        <!DOCTYPE html>
                        <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="' . $book->lang . '">
                                <head>
                                        <title>' . $book->name . '</title>
                                        <meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8" />
                                </head>
                                <body style="background-color:ghostwhite;"><div style="text-align:center; position:absolute;">
                                        <h1 id="heading_id_2">' . $book->name . '</h1>
                                        <h2>' . $book->author . '</h2>
                                        <br />
                                        <br />
                                        <img alt="" src="Accueil_scribe.png" />
                                        <br />
                                        <h4>' . $book->publisher;
                                        if($book->publisher != '' && ($book->year != '' || $book->place != ''))
                                                $content .= ', ';
                                        $content .= $book->place;
                                        if($book->year != '' && $book->place != '')
                                                $content .= ', ';
                                        $content .= $book->year . '</h4>
                                        <br style="margin-top: 3em; margin-bottom: 3em; border: none; background: black; width: 8em; height: 1px; display: block;" />
                                        <h5>' . str_replace('%d', strftime('%x'), $this->i18n['exported_from_wikisource_the']) . '</h5>
                                </div></body>
                        </html>';
                return $content;
        }

        protected function encodeTitles(Book $book) {
                $this->linksList[] = $book->title . '.xhtml';
                foreach($book->chapters as $chapter) {
                        $chapter->title = $this->encode($chapter->title);
                        $this->linksList[] = $chapter->title . '.xhtml';
                }
                $book->title = $this->encode($book->title);
                foreach($book->pictures as $picture) {
                        $picture->title = $this->encode($picture->title);
                        $this->linksList[] = $picture->title;
                }
        }

        /**
        * clean the files
        */
        protected function clean(Book $book) {
                $xPath = $this->getXPath($book->content);
                $this->setHtmlTitle($xPath, $book->name);
                $this->cleanHtml($xPath, $book);
                foreach($book->chapters as $chapter) {
                        $xPath = $this->getXPath($chapter->content);
                        $this->setHtmlTitle($xPath, $chapter->name);
                        $this->cleanHtml($xPath, $book);
                }
                $book->title = $this->encode($book->title);
        }


        protected function getXPath($file) {
                $xPath = new DOMXPath($file);
                $xPath->registerNamespace('html', 'http://www.w3.org/1999/xhtml');
                return $xPath;
        }

        protected function encode($string) {
                $search = array('@[éèêëÊË]@i','@[àâäÂÄ]@i','@[îïÎÏ]@i','@[ûùüÛÜ]@i','@[ôöÔÖ]@i','@[ç]@i','@[ ]@i','@[^a-zA-Z0-9_\.]@');
	        $replace = array('e','a','i','u','o','c','_','_');
                return preg_replace($search, $replace, $string);
        }

        /**
        * modified the XHTML
        */
        protected function cleanHtml(DOMXPath $xPath, $book) {
	        $this->setPictureLinks($xPath);
                $dom = $xPath->document;
                $this->setLinks($dom, $book);
                if($this->withCss)
                        $this->setCssLink($dom);
        }

        /**
        * change the picture links
        */
        protected function setHtmlTitle(DOMXPath $xPath, $name) {
                $title = $xPath->query('/html:html/html:head/html:title')->item(0);
                $title->nodeValue = $name;
        }

        /**
        * change the picture links
        */
        protected function setPictureLinks(DOMXPath $xPath) {
                $list = $xPath->query('//html:img');
                foreach($list as $node) {
                        $title = $this->encode($node->getAttribute('alt'));
                        if(in_array($title, $this->linksList))
                                $node->setAttribute('src', $title);
                        else
                                $node->parentNode->removeChild($node);
                }
        }

        /**
        * change the internal links
        */
        protected function setLinks(DOMDocument $dom, Book $book) {
                $list = $dom->getElementsByTagName('a');
                $title = Api::mediawikiUrlEncode($book->title);
                foreach($list as $node) {
                        $href = $node->getAttribute('href');
                        $title = $this->encode($node->getAttribute('title')) . '.xhtml';
                        if($href[0] == '#') {
                                continue;
                        } elseif(in_array($title, $this->linksList)) {
                                $node->setAttribute('href', $title);
                        } else {
                                $node->setAttribute('href', 'http:' . $href);
                        }
                }
        }

        /**
        * set css link to main.css
        */
        protected function setCssLink(DOMDocument $dom) {
                $node = $dom->getElementsByTagName('head')->item(0);
                $link = $dom->createElement('link');
                $link->setAttribute('type', 'text/css');
                $link->setAttribute('rel', 'stylesheet');
                $link->setAttribute('href', 'main.css');
                $node->appendChild($link);
        }

        protected function getCssWikisource($lang) {
                try {
                        $api = new Api($lang);
                        return $api->get('http://' . $lang . '.wikisource.org/w/index.php?title=MediaWiki:Epub.css&action=raw&ctype=text/css');
                } catch(Exception $e) {
                        return '';
                }
        }

        protected function getI18n($lang) {
                $ini = parse_ini_file(dirname(__FILE__) . '/i18n.ini');
                try {
                        $api = new Api($lang);
                        $response = $api->get('http://' . $lang . '.wikisource.org/w/index.php?title=MediaWiki:Wsexport_i18n.ini&action=raw&ctype=text/plain');
                        $temp = parse_ini_string($response);
                        if($ini != false)
                                $ini = array_merge($ini, $temp);
                } catch(Exception $e) {
                }
                return $ini;
        }
}


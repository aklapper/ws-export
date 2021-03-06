<?php

namespace App;

use App\Util\Api;
use Exception;
use Psr\Cache\CacheItemPoolInterface;

class Refresh {

	/** @var Api */
	protected $api;

	/** @var CacheItemPoolInterface */
	private $cacheItemPool;

	public function __construct( Api $api, CacheItemPoolInterface $cacheItemPool ) {
		$this->api = $api;
		$this->cacheItemPool = $cacheItemPool;
	}

	public function refresh() {
		if ( !is_dir( $this->getTempFileName( '' ) ) ) {
			mkdir( $this->getTempFileName( '' ) );
		}

		$this->getI18n();
		$this->getEpubCssWikisource();
		$this->cacheItemPool->deleteItems( [
			'namespaces_' . $this->api->getLang(),
			'about_' . $this->api->getLang(),
		] );
	}

	protected function getI18n() {
		$ini = parse_ini_file( dirname( __DIR__ ) . '/resources/i18n.ini' );
		try {
			$response = $this->api->get( 'https://' . $this->api->getLang() . '.wikisource.org/w/index.php?title=MediaWiki:Wsexport_i18n.ini&action=raw&ctype=text/plain' );
			$temp = parse_ini_string( $response );
			if ( $ini != false ) {
				$ini = array_merge( $ini, $temp );
			}
		} catch ( Exception $e ) {
		}
		$this->setTempFileContent( 'i18n.sphp', serialize( $ini ) );
	}

	protected function getEpubCssWikisource() {
		$content = file_get_contents( dirname( __DIR__ ) . '/resources/styles/mediawiki.css' );
		try {
			$content .= "\n" . $this->api->get( 'https://' . $this->api->getLang() . '.wikisource.org/w/index.php?title=MediaWiki:Epub.css&action=raw&ctype=text/css' );
		} catch ( Exception $e ) {
		}
		$this->setTempFileContent( 'epub.css', $content );
	}

	protected function setTempFileContent( $name, $content ) {
		return file_put_contents( $this->getTempFileName( $name ), $content );
	}

	protected function getTempFileName( $name ) {
		$cache = FileCache::singleton();

		return $cache->getDirectory() . '/' . $this->api->getLang() . '/' . $name;
	}
}

<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Extension\WikoraControl\WikiSettingsStore;

class LibertyApiRecentDiscussionsFeed extends ApiBase {
	public function execute() {
		global $wgLibertyRecentDiscussionsFeedFormat, $wgLibertyMaxRecent;

		// Read discussions URL from Wikoracontrol settings
		$store = new WikiSettingsStore();
		$settings = $store->getAllSettings();
		$discussionsUrl = $settings['discussions_url'] ?? '';
		$discussionsEnabled = ( $settings['discussions_enabled'] ?? '0' ) === '1';

		// Only use if discussions are enabled and URL is from wikora.io domain
		if ( !$discussionsEnabled || $discussionsUrl === '' || !$this->isWikoraDomain( $discussionsUrl ) ) {
			$this->getResult()->addValue( null, $this->getModuleName(), [
				'items' => [],
			] );
			return;
		}

		$url = trim( $discussionsUrl );
		// Append /rss to discussions URL if not already present
		if ( substr( $url, -1 ) === '/' ) {
			$url .= 'rss';
		} else {
			$url .= '/rss';
		}

		$format = strtolower( (string)$wgLibertyRecentDiscussionsFeedFormat );
		$limit = (int)$wgLibertyMaxRecent;

		if ( $limit < 1 ) {
			$limit = 10;
		}

		if ( !preg_match( '#^https?://#i', $url ) && substr( $url, 0, 1 ) !== '/' ) {
			$this->dieWithError( 'Recent discussions feed URL must be absolute http(s) or root-relative.' );
		}

		if ( substr( $url, 0, 1 ) === '/' ) {
			$url = wfExpandUrl( $url, PROTO_CURRENT );
		}

		$request = MediaWikiServices::getInstance()->getHttpRequestFactory()->create(
			$url,
			[
				'method' => 'GET',
				'timeout' => 10,
				'followRedirects' => true,
			],
			__METHOD__
		);

		$status = $request->execute();
		if ( !$status->isGood() ) {
			$this->dieWithError( 'Failed to fetch recent discussions feed.' );
		}

		$feedText = (string)$request->getContent();
		if ( $feedText === '' ) {
			$this->dieWithError( 'Recent discussions feed returned empty content.' );
		}

		$items = $this->parseFeedItems( $feedText, $limit, $format );

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'items' => $items,
		] );
	}

	/**
	 * @param string $feedText
	 * @param int $limit
	 * @param string $configuredFormat
	 * @return array
	 */
	private function parseFeedItems( $feedText, $limit, $configuredFormat ) {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$ok = $doc->loadXML( $feedText );
		libxml_clear_errors();

		if ( !$ok ) {
			return [];
		}

		$isAtom = $configuredFormat === 'atom';
		if ( !$isAtom ) {
			$isAtom = $doc->getElementsByTagName( 'entry' )->length > 0;
		}

		$nodeList = $isAtom ? $doc->getElementsByTagName( 'entry' ) : $doc->getElementsByTagName( 'item' );
		$items = [];
		$count = 0;

		foreach ( $nodeList as $node ) {
			if ( $count >= $limit ) {
				break;
			}

			$title = '';
			$link = null;
			$timestamp = wfTimestampNow();

			$titleNode = $this->firstChildByTagName( $node, 'title' );
			if ( $titleNode ) {
				$title = trim( $titleNode->textContent );
			}

			if ( $isAtom ) {
				$linkNode = $this->firstChildByTagName( $node, 'link' );
				if ( $linkNode ) {
					$link = $linkNode->attributes?->getNamedItem( 'href' )?->nodeValue ?: trim( $linkNode->textContent );
				}

				$dateNode = $this->firstChildByTagName( $node, 'updated' ) ?: $this->firstChildByTagName( $node, 'published' );
				if ( $dateNode ) {
					$timestamp = wfTimestamp( TS_ISO_8601, trim( $dateNode->textContent ) );
				}
			} else {
				$linkNode = $this->firstChildByTagName( $node, 'link' );
				if ( $linkNode ) {
					$link = trim( $linkNode->textContent );
				}

				$dateNode = $this->firstChildByTagName( $node, 'pubDate' );
				if ( $dateNode ) {
					$timestamp = wfTimestamp( TS_ISO_8601, trim( $dateNode->textContent ) );
				}
			}

			if ( $title === '' ) {
				continue;
			}

			$items[] = [
				'title' => $title,
				'url' => $link,
				'timestamp' => $timestamp,
				'type' => 'edit',
			];
			$count++;
		}

		return $items;
	}

	/**
	 * Check if URL is from a wikora.io domain
	 *
	 * @param string $url
	 * @return bool
	 */
	private function isWikoraDomain( string $url ): bool {
		if ( !preg_match( '#^https?://([^/]+)#i', $url, $matches ) ) {
			return false;
		}
		$host = $matches[1];
		return stripos( $host, '.wikora.io' ) !== false || $host === 'wikora.io';
	}

	/**
	 * @param DOMElement $node
	 * @param string $tagName
	 * @return DOMElement|null
	 */
	private function firstChildByTagName( DOMElement $node, $tagName ) {
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof DOMElement && $child->tagName === $tagName ) {
				return $child;
			}
		}

		return null;
	}

	public function isReadMode() {
		return true;
	}

	public function getAllowedParams() {
		return [];
	}
}

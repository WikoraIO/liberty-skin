$( function () {
	'use strict';
	var articleNamespaces, talkNamespaces, talkRssUrl, articleRecentChangesUrl, talkRecentChangesUrl,
		isArticleTab, limit;

	articleNamespaces = $( '.live-recent' ).attr( 'data-article-ns' );
	talkNamespaces = $( '.live-recent' ).attr( 'data-talk-ns' );
	talkRssUrl = $( '.live-recent' ).attr( 'data-talk-rss-url' );
	articleRecentChangesUrl = $( '.live-recent' ).attr( 'data-article-recentchanges-url' );
	talkRecentChangesUrl = $( '.live-recent' ).attr( 'data-talk-recentchanges-url' );
	isArticleTab = true;
	limit = $( '#live-recent-list' )[ 0 ].childElementCount;

	function escapeHtml( text ) {
		return $( '<div>' ).text( text || '' ).html();
	}

	function setViewMoreUrl() {
		var url = isArticleTab ? articleRecentChangesUrl : talkRecentChangesUrl;
		$( '.live-recent-footer a' ).attr( 'href', url );
	}

	function timeFormat( time ) {
		var aDayAgo, hour, minute, second;
		aDayAgo = new Date();
		aDayAgo.setDate( aDayAgo.getDate() - 1 );
		if ( time < aDayAgo ) {
			return ( time.getFullYear() ) + '/' + ( time.getMonth() + 1 ) + '/' + time.getDate();
		}
		hour = time.getHours();
		minute = time.getMinutes();
		second = time.getSeconds();
		if ( hour < 10 ) {
			hour = '0' + hour;
		}
		if ( minute < 10 ) {
			minute = '0' + minute;
		}
		if ( second < 10 ) {
			second = '0' + second;
		}
		return hour + ':' + minute + ':' + second;
	}

	function renderRecentChanges( recentChanges ) {
		var html;
		html = recentChanges.map( function ( item ) {
			var displayText, escapedDisplayText, safeTitle, title, time, line, itemUrl;
			title = item.title || '';
			safeTitle = escapeHtml( title );
			time = new Date( item.timestamp );
			itemUrl = item.url || mw.util.getUrl( title );
			line = '<li><a class="recent-item" href="' + itemUrl + '" title="' + safeTitle + '">[' +
				timeFormat( time ) + '] ';
			displayText = title;
			if ( displayText.length > 13 ) {
				displayText = displayText.substr( 0, 13 ) + '...';
			}
			escapedDisplayText = escapeHtml( displayText );
			if ( item.type === 'new' ) {
				line += '<span class="new">' + mw.message( 'liberty-feed-new' ).escaped() + ' </span>';
			}
			line += escapedDisplayText;
			line += '</a></li>';
			return line;
		} ).join( '\n' );
		$( '#live-recent-list' ).html( html );
	}

	function refreshFromRecentChangesApi() {
		var getParameter;

		getParameter = {
			action: 'query',
			list: 'recentchanges',
			rcprop: 'title|timestamp',
			rcshow: '!bot|!redirect',
			rctype: 'edit|new',
			rclimit: limit,
			format: 'json',
			rcnamespace: isArticleTab ? articleNamespaces : talkNamespaces,
			rctoponly: true
		};

		mw.loader.using( 'mediawiki.api' ).then( function () {
			var api = new mw.Api();
			api.get( getParameter ).then( function ( data ) {
				renderRecentChanges( data.query.recentchanges || [] );
			} )
			.catch( function () {} );
		} );
	}

	function refreshFromTalkRss() {
		return mw.loader.using( 'mediawiki.api' ).then( function () {
			var api = new mw.Api();
			return api.get( {
				action: 'libertyrecentdiscussionsfeed',
				format: 'json'
			} ).then( function ( data ) {
				var items = ( data.libertyrecentdiscussionsfeed && data.libertyrecentdiscussionsfeed.items ) || [];
				renderRecentChanges( items );
			} );
		} );
	}

	function refreshLiveRecent() {

		if ( !$( '#live-recent-list' ).length || $( '#live-recent-list' ).is( ':hidden' ) ) {
			return;
		}

		setViewMoreUrl();

		if ( !isArticleTab && talkRssUrl ) {
			refreshFromTalkRss().catch( function () {
				refreshFromRecentChangesApi();
			} );
			return;
		}

		refreshFromRecentChangesApi();
	}

	$( '#liberty-recent-tab1' ).click( function () {
		$( this ).addClass( 'active' );
		$( '#liberty-recent-tab2' ).removeClass( 'active' );
		isArticleTab = true;
		refreshLiveRecent();
	} );

	$( '#liberty-recent-tab2' ).click( function () {
		$( this ).addClass( 'active' );
		$( '#liberty-recent-tab1' ).removeClass( 'active' );
		isArticleTab = false;
		refreshLiveRecent();
	} );

	setInterval( refreshLiveRecent, 5 * 60 * 1000 );
	refreshLiveRecent();
} );

let cpkNotificationsModule = (function() {
	"use strict";

	let STORAGE_KEY = "cpk.notifications";

	let CACHE_TIME_LIMIT = 60 * 60 * 1000;

	return {

		init: function( user ) {
			 initialize( user );
		},

	}

	function initialize( user ) {
		let notifications = JSON.parse( localStorage.getItem( STORAGE_KEY ) );
		let now = Date.now();
		if ( notifications == null || notifications.user !== user ) {
			refresh ( user );
		} else if ( now - notifications.timestamp >= CACHE_TIME_LIMIT ) {
			show( notifications );
			refresh ( user );
		} else {
			show( notifications );
		}
		$( "#cpk-notifications-warning" ).click( function() { refresh( user ); } );
	}

	function refresh( user ) {
		$( "#cpk-notifications-list .notif-default" ).show();
		$.getJSON( "/AJAX/JSON?method=getAllNotificationsForUser", {}, function ( data ) {
			if ( data.status == 'OK' ) {
				data.user = user;
				data.timestamp = Date.now();
				localStorage.setItem( STORAGE_KEY, JSON.stringify( data ) );
				show( data );
			}
		} );
	}

	function show( notifications ) {
		let totalNotifications = 0;
		for ( let source in notifications[ 'data' ] ) {
			let noOfNotifs = 0;
			let placeholder = $( "#cpk-notifications-" + source );
			placeholder.find( ".notif" ).remove();
			for ( let i in notifications[ 'data' ][ source ][ 'notifications' ] ) {
				let notif = notifications[ 'data' ][ source ][ 'notifications' ][ i ];
				$( createNotification( notif ) ).appendTo( placeholder );
				noOfNotifs++;
				totalNotifications++;
			}
			$( "#cpk-notifications-" + source + " .notif-default" ).hide();
			$( "#cpk-notifications-" + source + " .notif-header" ).toggle( noOfNotifs > 0 );
		}
		$( "#cpk-notifications" ).toggle( totalNotifications > 0 );
		$( "#cpk-notifications-warning" ).toggle( totalNotifications > 0 );
	}

	function createNotification( notif ) {
		let elm = document.createElement( 'div' );
		elm.setAttribute( 'class', 'notif notif-' + notif.clazz );
		let link = document.createElement( 'a' );
		link.setAttribute( 'href', notif.href );
		link.textContent = notif['message'];
		$( link ).appendTo( elm );
		return elm;
	}

}());

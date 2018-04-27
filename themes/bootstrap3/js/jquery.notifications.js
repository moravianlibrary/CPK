(function ( $, document ) {
	"use strict";

	let STORAGE_KEY = "cpk.notifications";

	function init( user ) {
		let notifications = localStorage.getItem( STORAGE_KEY );
		if ( notifications == null ) {
			$.getJSON( "/AJAX/JSON?method=getAllNotificationsForUser", {}, function ( data ) {
				if ( data.status == 'OK' ) {
					localStorage.setItem( STORAGE_KEY, JSON.stringify( data ) );
					show( data );
				}
			} );
		} else {
			show( JSON.parse( notifications ) );
		}
	}

	function show( notifications ) {
		for ( var source in notifications['data'] ) {
			var noOfNotifs = 0;
			for ( var i in notifications['data'][source]['notifications'] ) {
				let notif = notifications['data'][source]['notifications'][i];
				let placeholder = $( "#cpk-notifications-" + source );
				$( createNotification( notif ) ).appendTo( placeholder );
				noOfNotifs++;
			}
			$( "#cpk-notifications-" + source + " .notif-default" ).hide();
			if ( noOfNotifs == 0 ) {
				$( "#cpk-notifications-" + source + " .notif-header" ).hide();
			}
		}
		$( "#cpk-notifications-warning" ).show();
	}

	function createNotification( notif ) {
		let elm = document.createElement( 'div' );
		elm.setAttribute( 'class', 'notif-' + notif.clazz );
		let link = document.createElement( 'a' );
		link.setAttribute( 'href', notif.href );
		link.textContent = notif['message'];
		$( link ).appendTo( elm );
		return elm;
	}

	$( document ).ready( function () {
		init();
	} );

	return this;

}( jQuery, document ));
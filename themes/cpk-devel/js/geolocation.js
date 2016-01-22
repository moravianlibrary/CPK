/**
 * Geolocation.js
 * 
 * @author	Martin Kravec <martin.kravec@mzk.cz>
 * @version	0.1
 */
if ( navigator.geolocation ) {
	var timeoutVal = 10 * 1000 * 1000;
	navigator.geolocation.getCurrentPosition(
		displayPosition, 
	    displayError,
	    { enableHighAccuracy: true, timeout: timeoutVal, maximumAge: 0 }
    );
}
else {
	console.log("Geolocation is not supported by this browser");
}

function displayPosition( position ) {
	console.log ( "Latitude: " + position.coords.latitude
			+ ", Longitude: " + position.coords.longitude );
}

function displayError( error ) {
	var errors = { 
		1: 'Permission denied',
	    2: 'Position unavailable',
	    3: 'Request timeout'
	};
	console.log( "Error: " + errors[error.code] );
}

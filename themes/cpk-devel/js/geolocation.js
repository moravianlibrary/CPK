/**
 * Geolocation.js
 * 
 * @author	Martin Kravec <martin.kravec@mzk.cz>
 * @version	0.2
 */

jQuery( document ).ready( function( $ ) {
	
	GEO = {
			
		getPositionForLoadingInstitutions: function( callback ) {
			if ( navigator.geolocation ) {
				var timeoutVal = 10 * 1000 * 1000;
				navigator.geolocation.getCurrentPosition(
					triggetInstitutionsReloading, 
					geo_error,
				    { enableHighAccuracy: true, timeout: timeoutVal, maximumAge: 0 }
			    );
			}
			else {
				alert("Geolocation is not supported by this browser");
			}
		},
		
	};
	
	function triggetInstitutionsReloading( position ) {
		var coords = {};
		coords['latitude'] = position.coords.latitude;
		coords['longitude'] = position.coords.longitude;
		
		FACETS.reloadInstitutionsByGeolocation( coords );
	};
	
	function geo_error( error ) {
		var errors = { 
			1: 'Permission denied',
		    2: 'Position unavailable',
		    3: 'Request timeout'
		};
		alert( "Error: " + errors[error.code] );
	};
	
});

jQuery( document ).ready( function( $ ) {
	$( 'select[name="citation_style"]' ).on( 'change', function() {
		$.ajax({
			type: 'POST',
			url: '/AJAX/JSON?method=setCitationStyle',
			dataType: 'json',
			async: true,
			data: {
			    citationStyleValue: $( this ).val()
			},
			success: function( response ) {
			    alert( VuFind.translate( 'citation_set_permanently' ) );
			}
		});
	});
});
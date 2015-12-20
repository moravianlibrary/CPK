jQuery( document ).ready( function( $ ) {
	$( 'select[name="citation-style"]' ).on( 'change', function() {
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
	$( 'select[name="records-per-page"]' ).on( 'change', function() {
		$.ajax({
			type: 'POST',
			url: '/AJAX/JSON?method=setRecordsPerPage',
			dataType: 'json',
			async: true,
			data: {
			    recordsPerPage: $( this ).val()
			},
			success: function( response ) {
			    alert( VuFind.translate( 'records_per_page_set_permanently' ) );
			}
		});
	});
	$( 'select[name="preferred-sorting"]' ).on( 'change', function() {
		$.ajax({
			type: 'POST',
			url: '/AJAX/JSON?method=setPreferredSorting',
			dataType: 'json',
			async: true,
			data: {
			    preferredSorting: $( this ).val()
			},
			success: function( response ) {
			    alert( VuFind.translate( 'default_sorting_set_permanently' ) );
			}
		});
	});
});
jQuery( document ).ready( function( $ ) {
	$( 'select[name="citation-style"]' ).on( 'change', function() {
		$( '.citation-style-status' ).html( "<i class='fa fa-spinner fa-spin'></i>" );
		$.ajax({
			type: 'POST',
			url: '/AJAX/JSON?method=setCitationStyle',
			dataType: 'json',
			async: true,
			data: {
			    citationStyleValue: $( this ).val()
			},
			success: function( response ) {
			    $( '.citation-style-status i' ).removeClass('fa-spinner fa-spin').addClass('fa-thumbs-o-up');
			}
		});
	});
	$( 'select[name="records-per-page"]' ).on( 'change', function() {
		$( '.records-per-page-status' ).html( "<i class='fa fa-spinner fa-spin'></i>" );
		$.ajax({
			type: 'POST',
			url: '/AJAX/JSON?method=setRecordsPerPage',
			dataType: 'json',
			async: true,
			data: {
			    recordsPerPage: $( this ).val()
			},
			success: function( response ) {
				$( '.records-per-page-status i' ).removeClass('fa-spinner fa-spin').addClass('fa-thumbs-o-up');
			}
		});
	});
	$( 'select[name="preferred-sorting"]' ).on( 'change', function() {
		$( '.preferred-sorting-status' ).html( "<i class='fa fa-spinner fa-spin'></i>" );
		$.ajax({
			type: 'POST',
			url: '/AJAX/JSON?method=setPreferredSorting',
			dataType: 'json',
			async: true,
			data: {
			    preferredSorting: $( this ).val()
			},
			success: function( response ) {
				$( '.preferred-sorting-status i' ).removeClass('fa-spinner fa-spin').addClass('fa-thumbs-o-up');
			}
		});
	});
});
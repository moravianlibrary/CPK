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
            beforeSend: function () {
                $( "body" ).addClass( "loading" );
            },
			success: function( response ) {
                $( "body" ).removeClass( "loading" );
			}
		});
	});
	$( 'select[name="records-per-page"]' ).on( 'change', function() {
		var value = $( this ).val();
		$.ajax({
			type: 'POST',
			url: '/AJAX/JSON?method=setRecordsPerPage',
			dataType: 'json',
			async: true,
			data: {
			    recordsPerPage: value
			},
            beforeSend: function () {
                $( "body" ).addClass("loading");
            },
			success: function( response ) {
				$( '.searchForm input[name="limit"]' ).val( value );
				$( "input[name='limit']" ).val( value );
                $( "body" ).removeClass( "loading" );
			}
		});
	});
	$( 'select[name="preferred-sorting"]' ).on( 'change', function() {
		var value = $( this ).val();
		$.ajax({
			type: 'POST',
			url: '/AJAX/JSON?method=setPreferredSorting',
			dataType: 'json',
			async: true,
			data: {
			    preferredSorting: value
			},
            beforeSend: function () {
                $( "body" ).addClass("loading");
            },
			success: function( response ) {
				$( '.searchForm input[name="sort"]' ).val( value );
                $( "body" ).removeClass( "loading" );
			}
		});
	});
});
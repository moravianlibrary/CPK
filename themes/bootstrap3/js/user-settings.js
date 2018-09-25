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
		let value = $( this ).val();
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
		let value = $( this ).val();
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

    /*
     * Save chosen institutions to DB
     */
    $( 'body' ).on( 'click', '#save-these-institutions', function( event ) {
        event.preventDefault();
        let data = {};
        let institutions = [];
        let selectedInstitutions = $('#facet_institution').jstree(true).get_bottom_selected();
        $.each( selectedInstitutions, function( index, value ){
            let explodedArray = value.split(":");
            institutions.push(explodedArray[1].slice(1, -1));
        });
        data['institutions'] = institutions;
        $.ajax({
            type: 'POST',
            cache: false,
            dataType: 'json',
            url: VuFind.getPath() + '/AJAX/JSON?method=saveTheseInstitutions',
            data: data,
            beforeSend: function() {
            },
            success: function( response ) {
                console.log( 'Save these institutions: ' );
                console.log( data );
                if (response.status == 'OK') {
                    $( '#save-these-institutions-confirmation' ).modal( 'show' );
                    setTimeout( function() {
                        $( '#save-these-institutions-confirmation' ).modal( 'hide' );
                    }, 1200 );
                } else {
                    console.error(response.data);
                }
            },
            error: function ( xmlHttpRequest, status, error ) {
                $( '#search-results-loader' ).remove();
                console.error(xmlHttpRequest.responseText);
                console.error(xmlHttpRequest);
                console.error(status);
                console.error(error);
            },
            complete: function ( xmlHttpRequest, textStatus ) {
            }
        });
    });

    $( '#institutions, #records-per-page, #citation-style, #preferred-sorting' ).select2();
});
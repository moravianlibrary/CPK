jQuery( document ).ready( function( $ ) {
    CKEDITOR.replace( 'content', {
        entities_latin: false,
    } );

    $( '#deletePage' ).on( 'click', function( event ) {
        event.preventDefault();
        let url = $( this ).attr( 'href' );
        if (confirm(VuFind.translate('Do you really want to delete this page?')) == true) {
            window.location.href = url;
        }
    });

    if ($( '#view' ).val() == 'advanced-search') {
        $( '.position-placeholder' ).addClass( 'hidden' );
    }
    $( '#view' ).on( 'change', function() {
        if ( $( this ).val() == 'advanced-search' ) {
            $( '.position-placeholder' ).addClass( 'hidden' );
        } else {
            $( '.position-placeholder' ).removeClass( 'hidden' );
        }
    });
});


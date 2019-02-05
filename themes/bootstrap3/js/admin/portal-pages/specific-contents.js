jQuery( document ).ready( function( $ ) {
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


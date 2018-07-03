jQuery( document ).ready( function( $ ) {
    $( '#menu-widgets-list' ).select2({
        placeholder: {
            id: '-1', // the value of the option
            text: VuFind.translate('Select widget')
        }
    });

    $( '.aside-menu' ).on( 'change', '#menu-widgets-list', function() {
        window.location.href = $( this ).val();
    });
});
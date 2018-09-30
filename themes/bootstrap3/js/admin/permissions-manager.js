jQuery( document ).ready( function( $ ) {
    $( '[data-toggle="tooltip"]' ).tooltip();
    $( '.permission-manager' ).on( 'click', '.remove-permissions', function( event ) {
        event.preventDefault();
        if (confirm(VuFind.translate('Do you really want to remove permissions to this user?'))) {
            window.location.href = '/Admin/PermissionsManager/RemovePermissions/' + $( this ).attr( 'id' );
        }
    });
});
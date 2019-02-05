jQuery( document ).ready( function( $ ) {
    /* Expand subcategories on click */
    $( '#conspectus' ).on( 'click', '.expand-category', function( event ){
        event.preventDefault();
        let expandedCategoryElement = $( this ).parent().find( '.expanded-category' );
        if (expandedCategoryElement.hasClass( 'hidden' )) {
            expandedCategoryElement.removeClass( 'hidden' )
                .parent()
                .find( 'i.pr-institution-arrow' )
                .removeClass( 'collapsed' )
                .addClass( 'expanded' );
        } else {
            expandedCategoryElement.addClass( 'hidden' )
                .parent()
                .find( 'i.pr-institution-arrow' )
                .removeClass( 'expanded' )
                .addClass( 'collapsed' );
        }
    });
});
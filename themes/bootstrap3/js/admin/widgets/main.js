jQuery( document ).ready( function( $ ) {

    /* HomePage Widgets */
    $( '#homepage-widgets' ).on( 'change', 'select', function( event ) {
        event.preventDefault();

        let data = {};

        $( '#homepage-widgets select' ).each(function( index, element ) {
            data[$( element ).attr( 'id' )] = $( element ).val();
        });

        $.ajax({
            type: 'POST',
            cache: false,
            dataType: 'json',
            data: data,
            url: VuFind.getPath() + '/Admin/Widgets/SaveFrontendWidgets',
            beforeSend: function() {
            },
            success: function( response ) {

                if (response.status == 'OK') {

                    $( '#save-widgets-confirmation' ).modal( 'show' );

                    setTimeout( function() {
                        $( '#save-widgets-confirmation' ).modal( 'hide' );
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

    /* Inspirations widgets */
    nextPosition = document.querySelectorAll('.inspiration-widget').length + 1;

    let widgetSelect = document.getElementById('widget-template').cloneNode(true);
    widgetSelect.classList.remove('hidden');

    /* Inspirations Widgets */
    $( '#inspirations-widgets' ).on( 'change', 'select', function( event ) {
        event.preventDefault();

        let widgetName = $( this ).val();
        let position = $( this ).parent().parent().attr( 'data-position' );
        console.log( 'Changing inspiration widget at position ' + position + ' to widget ' + widgetName);
    });

    $( '#inspirations-widgets' ).on( 'click', '#add-more-inspirations-widgets', function( event ) {
        console.log( 'Adding empty widget at position: ' + nextPosition );

        let html = `<div class='col-md-4 text-center inspiration-widget' data-position='${nextPosition}'>
          ${widgetSelect.outerHTML}
        </div>`;

        $( '#inspirations-placeholder' ).append( html );
        $( '#inspirations-placeholder' )
            .find( 'inspiration-widget' )
            .last()
            .attr('id', `inspiration-widget-${nextPosition}`)
            .val( '' );

        $('.selectpicker').selectpicker({
            style: 'btn-info',
            size: 8
        });
        nextPosition++;
    });

    $( '#inspirations-widgets' ).on( 'click', '#save-inspirations-widgets', function( event ) {

        let data = {};
        data['widgets'] = [];

        $( '.inspiration-widget' ).each(function( index, element ) {
            let position = $( element ).attr( 'data-position' );
            let value = $( element ).find( 'select' ).val();

            data['widgets'][position] = value;
        });

        $.ajax({
            type: 'POST',
            cache: false,
            dataType: 'json',
            data: data,
            url: VuFind.getPath() + '/Admin/Widgets/SaveInspirationsWidgets',
            beforeSend: function() {
            },
            success: function( response ) {

                if (response.status == 'OK') {

                    $( '#save-widgets-confirmation' ).modal( 'show' );

                    setTimeout( function() {
                        $( '#save-widgets-confirmation' ).modal( 'hide' );
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

    $('.selectpicker').selectpicker({
        style: 'btn-info',
        size: 8
    });
});

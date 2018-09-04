/* global VuFind */
jQuery( document ).ready( function ($) {
    let parentId = $( 'body' ).find( '.hiddenParentRecordId' ).val(),
        referer = getParameterByName( 'referer' ),
        extraRecords = [];

    if (typeof Storage !== 'undefined') {
        let records = localStorage.getItem( 'extraRecords' );
        if (records !== null) {
            extraRecords = JSON.parse( records );
        }
    }
    let extraResults = extraRecords.extraResults,
        extraRecordsCount = extraResults.length,
        currentPosition = getObjectKeyIndex( extraResults, parentId ),
        recordsSwitching = $( '#records-switching' ),
        database = $( 'input[name=\'database\']' ).val(),
        recordType = '/Record/';

    if (database === 'EDS') {
        recordType = '/EdsRecord/';
    }

    if (extraRecordsCount > 1 && currentPosition >= 0) {
        recordsSwitching.removeClass( 'hidden' );
        if (currentPosition > 0) {
            let previousRecordId = Object.values(extraResults[currentPosition - 1]);
            //create link to prev record with right record type
            recordsSwitching.find( '#extraPrevious' ).
                attr( 'href', recordType + previousRecordId + '?referer=' + referer );
        }
        else if (currentPosition === 0 && extraRecords.extraPage !== 1) {
            recordsSwitching.find( '#extraPreviousIcon' ).addClass( 'hidden' );
            recordsSwitching.find( '#extraPreviousIconSpiner' ).removeClass( 'hidden' );

            getExtraResults( referer, 'previous' ).done( function (response) {
                updateAjaxRecordSwitcher( response, 'Previous' );
            } ).fail( function () {
                console.error( 'Cant\'t load extra records' );
            } );
        }

        recordsSwitching.find( 'span' ).
            append( `${VuFind.translate( 'page' )} ${extraRecords.extraPage}, ${VuFind.translate(
                'record' )} ${currentPosition + 1}` );

        if (currentPosition < extraRecordsCount - 1) {
            let nextRecordId = Object.values(extraResults[currentPosition + 1]);
            //create link to next record with right record type
            recordsSwitching.find( '#extraNext' ).attr( 'href', recordType + nextRecordId + '?referer=' + referer );
        }
        else if (currentPosition === extraRecordsCount - 1) {
            recordsSwitching.find( '#extraNextIcon' ).addClass( 'hidden' );
            recordsSwitching.find( '#extraNextIconSpiner' ).removeClass( 'hidden' );

            getExtraResults( referer, 'next' ).done( function (response) {
                updateAjaxRecordSwitcher( response, 'Next' );
            } ).fail( function () {
                console.error( 'Can\'t load extra records' );
            } );
        }
    }

    /**
     * Make links, show animations and save extra results
     * to local storage
     *
     * @param response Takes response from ajax
     * @param direction Takes 'Next' or 'Previous' value
     */
    function updateAjaxRecordSwitcher (response, direction) {
        let referer = response.data.referer,
            extraResults = response.data.extraResults,
            extraPage = response.data.extraPage;

        if (direction === 'Previous') {
            let extraResultsCount = extraResults.length;
            recordsSwitching.find( '#extraPrevious' ).
                attr( 'href',
                    recordType + Object.values(extraResults[extraResultsCount - 1]) + '?referer=' + referer );
        }
        else {
            recordsSwitching.find( '#extraNext' ).
                attr( 'href', recordType + Object.values(extraResults[0]) + '?referer=' + referer );
        }

        recordsSwitching.find( '#extra' + direction + 'IconSpiner' ).addClass( 'hidden' );
        recordsSwitching.find( '#extra' + direction + 'Icon' ).removeClass( 'hidden' );

        recordsSwitching.on( 'click', '#extra' + direction, function () {
            localStorage.setItem( 'extraRecords', JSON.stringify( { referer, extraResults, extraPage } ) );
        } );
    }

    /**
     * Send ajax request to get extra records
     *
     * @param referer link
     * @param direction Takes 'next' or 'previous' value
     * @returns {promise}
     */
    function getExtraResults (referer, direction) {
        return $.ajax( {
            type: 'POST',
            cache: false,
            dataType: 'json',
            url: '/AJAX/JSON?method=updateExtraSearchResults',
            data: { referer, direction },
        } );
    }

    /**
     * Search for a parameter in url
     *
     * @param name Parameter's name
     * @param url
     * @returns {*} Parameter's value
     */
    function getParameterByName (name, url) {
        if (!url) {
            url = window.location.href;
        }
        name = name.replace( /[\[\]]/g, '\\$&' );
        let regex = new RegExp( '[?&]' + name + '(=([^&#]*)|&|#|$)', 'i' ),
            results = regex.exec( url );
        if (!results) {
            return null;
        }
        if (!results[2]) {
            return '';
        }
        return decodeURIComponent( results[2].replace( /\+/g, ' ' ) );
    }

    function getObjectKeyIndex (object, keyToFind) {
        let i = 0, key;

        for (key in object) {
            if (object[key].hasOwnProperty( keyToFind )) {
                return i;
            }
            i++;
        }
        return null;
    }
} );
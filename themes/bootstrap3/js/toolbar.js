/* global VuFind */
jQuery( document ).ready( function ( $ ) {
    let id = $( 'body' ).find( '.hiddenId' ).val()
    let referer = getParameterByName( 'referer' )
    let previousRecordText = VuFind.translate( 'Switch to previous record' )
    let nextRecordText = VuFind.translate( 'Switch to next record' )

    try {
        if (localStorage.getItem( 'extraRecords' ) !== null) {
            var extraRecords = JSON.parse( localStorage.getItem( 'extraRecords' ) )
        }
        else {
            throw true
        }
    }
    catch (e) {
        extraRecords = []
    }

    let extraRecordsCount = extraRecords.extraResults.length
    let currentPosition = arraySearch( extraRecords.extraResults, id )

    let html = ''
    let database = $( 'input[name=\'database\']' ).val()
    let recordType = '/Record/'
    if (database === 'EDS') {
        recordType = '/EdsRecord/'
    }

    if (extraRecordsCount > 1) {
        if (currentPosition > 0) {
            let previousRecordId = extraRecords.extraResults[currentPosition - 1]

            //create link to prev record with right record type
            html += `<a href='${recordType}${previousRecordId}?referer=${referer}' title='${previousRecordText}'>`
            html += '  <i class=\'pr-interface-arrowleft2\'></i>'
            html += '</a>'
        }
        else if (currentPosition === 0 && extraRecords.extraPage !== 1) {
            html += '<a id=\'extraPrevious\'  >'
            html += '  <i id=\'extraPreviousIconSpiner\' class=\'fa fa-spinner fa-spin\'></i>'
            html += '  <i id=\'extraPreviousIcon\' class=\'pr-interface-arrowleft2 hidden\'></i>'
            html += '</a>'

            getExtraResults( referer, 'previous' ).done( function ( response ) {
                let responseData = response.data
                referer = responseData.referer
                let extraResults = responseData.extraResults
                let extraResultsCount = extraResults.length

                $( '#extraPrevious' ).
                    attr( 'href', recordType + extraResults[extraResultsCount - 1] + '?referer=' + referer )
                $( '#extraPreviousIconSpiner' ).attr( 'class', 'hidden' )
                $( '#extraPreviousIcon' ).removeClass( 'hidden' )

                $( 'body' ).on( 'click', '#extraPrevious', function () {
                    localStorage.setItem( 'extraRecords', JSON.stringify(
                        {
                            referer: referer,
                            extraResults: extraResults,
                            extraPage: responseData.extraPage,
                        } ) )
                } )
            } ).fail( function () {
                console.error( 'Cant\'t load extra records' )
            } )
        }

        html += `<span> ${VuFind.translate( 'page' )} ${extraRecords.extraPage}, ${VuFind.translate(
            'record' )} ${currentPosition + 1}</span>`

        if (currentPosition < extraRecordsCount - 1) {
            let nextRecordId = extraRecords.extraResults[currentPosition + 1]

            //create link to next record with right record type
            html += `<a href='${recordType}${nextRecordId}?referer=${referer}' title='${nextRecordText}'>`
            html += '  <i class=\'pr-interface-arrowright2\'></i>'
            html += '</a>'
        }
        else if (currentPosition === extraRecordsCount - 1) {
            //create link to next record with right record type
            html += '<a id=\'extraNext\' >'
            html += '  <i id=\'extraNextIconSpiner\' class=\'fa fa-spinner fa-spin\'></i>'
            html += '  <i id=\'extraNextIcon\' class=\'pr-interface-arrowright2 hidden\'></i>'
            html += '</a>'
            getExtraResults( referer, 'next' ).done( function ( response ) {
                let responseData = response.data
                referer = responseData.referer
                let extraResults = responseData.extraResults

                $( '#extraNext' ).attr( 'href', recordType + extraResults[0] + '?referer=' + referer )
                $( '#extraNextIconSpiner' ).attr( 'class', 'hidden' )
                $( '#extraNextIcon' ).removeClass( 'hidden' )

                $( 'body' ).on( 'click', '#extraNext', function () {
                    localStorage.setItem( 'extraRecords', JSON.stringify(
                        {
                            referer: referer,
                            extraResults: extraResults,
                            extraPage: responseData.extraPage,
                        } ) )
                } )
            } ).fail( function () {
                console.error( 'Can\'t load extra records' )
            } )
        }

        $( '#records-switching' ).append( html )
    }

    function getExtraResults ( referer, direction ) {
        return $.ajax( {
            type: 'POST',
            cache: false,
            dataType: 'json',
            url: '/AJAX/JSON?method=updateExtraSearchResults',
            data: {
                referer: referer,
                direction: direction,
            },
        } )
    }

    function arraySearch ( arr, value ) {
        for (var i = 0; i < arr.length; i++) {
            if (arr[i] === value) {
                return i
            }
        }
        return false
    }

    function getParameterByName ( name, url ) {
        if (!url) {
            url = window.location.href
        }
        name = name.replace( /[\[\]]/g, '\\$&' )
        let regex = new RegExp( '[?&]' + name + '(=([^&#]*)|&|#|$)', 'i' ),
            results = regex.exec( url )
        if (!results) {
            return null
        }
        if (!results[2]) {
            return ''
        }
        return decodeURIComponent( results[2].replace( /\+/g, ' ' ) )
    }
} )
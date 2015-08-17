var cookies_agreed = function(yesNo) {

    if (yesNo) {
        var date = new Date();
        date.setFullYear(date.getFullYear() + 10);
        document.cookie = 'eu-cookies=1; path=/; expires=' + date.toGMTString();

        $('.eu-cookies').remove();
    } else {
        document.cookie = 'eu-cookies=1; path=/';

        // Do not remove the div as the user didn't agree
    }
}

function isRecordPage() {
    return document.location.pathname.match(/\/Record\/\w+[.].*/) != null;
}

function getHoldingsIds() {
    ids = [];
    $("div#holdings-tab tbody tr").each(function() {
        ids.push($(this).attr('id'));
    });
    return ids;
}

if (isRecordPage()) {

    // Async holdings loader
    // TODO: Make the request cancellable after user chooses any filter
    // FIXME: Call one id by one & everytime update everyone
    $(function() {

        if (document.location.pathname.indexOf('Record/'))
            var ajaxResponse = $.getJSON(
                    '/AJAX/JSON?method=getHoldingsStatuses', {
                        ids : getHoldingsIds()
                    }, function(resp) {

                        // Log each key in the response data
                        $.each(resp, function(key, value) {
                            console.log('Printing response: ' + key + " : "
                                    + value);
                        });
                    });

        console.log('ajaxResponse = ' + ajaxResponse);

        // TODO process every holding
    });
}
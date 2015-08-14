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

function getHoldingsIds() {
    
}

// Async holdings loader
// TODO: Make the request cancellable after user chooses any filter
// FIXME: Call one id by one & everytime update everyone
$(function() {
    var ids = [];
    
    $("div#holdings-tab tbody tr").each(function() {
        ids.push($(this).attr('id'))
    });
    
    var ajaxResponse = $.ajax({
        dataType : 'json',
        url : '/AJAX/JSON?method=getHoldingsStatuses',
        data : {
            ids : ids
        },
        async : true,
        complete : function(response) {

            if (response.status !== 'OK') {
                // display the error message on each of the ajax status place
                // holder
                $("#ajax-error-info").empty().append(response.data);
            }
        }
    });

    // TODO process every holding
});
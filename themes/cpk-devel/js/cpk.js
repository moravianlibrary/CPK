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

// Async holdings loader
// TODO: Make the request cancellable after user chooses any filter
$(function() {
    var urlSplitted = document.URL.split('/'),
    recordID = urlSplitted[urlSplitted.length - 1],
    ids = [], // FIXME get all 996 to parse data for
    ajaxResponse = $.ajax({
        dataType : 'json',
        url : '/AJAX/JSON?method=getHoldingsStatuses',
        data : {
            bibId : recordID,
            ids : ids
        },
        async : true,
        success : function(response) {

            console.log(response);

            if (response.status !== 'OK') {
                // display the error message on each of the ajax status place
                // holder
                $("#ajax-error-info").empty().append(response.data);
            }
        }
    });

    // TODO process every holding
});
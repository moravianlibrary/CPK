var cookies_agreed = function(yesNo) {

    if (yesNo) {
        Cookies.set('eu-cookies', 1, { expires: 30 }); // Stay for 30 days ..

        $('.eu-cookies').remove();
    } else {
        Cookies.remove('eu-cookies');

        // Do not remove the div as the user didn't agree
    }
}
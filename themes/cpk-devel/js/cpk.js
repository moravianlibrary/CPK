var cookies_agreed = function(yesNo) {
    
    if (yesNo) {
        var date = new Date();
        date.setFullYear(date.getFullYear() + 10);
        document.cookie = 'eu-cookies=1; path=/; expires=' + date.toGMTString();
    } else
        document.cookie = 'eu-cookies=1; path=/';
    
    $('.eu-cookies').remove();
}
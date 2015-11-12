var cookies_agreed = function(yesNo) {

    if (yesNo) {
        createCookie("eu-cookies", 1, 30 * 24); // Stay for 30 days ..

        $('.eu-cookies').remove();
    } else {
        eraseCookie("eu-cookies");

        // Do not remove the div as the user didn't agree
    }
}

function createCookie(name, value, hours, asObject) {
    
    if (typeof asObject == "undefined")
	asObject = 0;
    
    var expires;    

    if (hours) {
        var date = new Date();
        date.setTime(date.getTime() + (hours * 60 * 60 * 1000));
        expires = "; expires=" + date.toGMTString();
    } else {
        expires = "";
    }
    
    if (asObject)
	value = JSON.stringify(value);
    
    document.cookie = encodeURIComponent(name) + "=" + encodeURIComponent(value) + expires + "; path=/";
}

function readCookie(name, wasObject) {
    
    if (typeof wasObject == "undefined")
	wasObject = 0;    
    
    var nameEQ = encodeURIComponent(name) + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) {
            var toRet = decodeURIComponent(c.substring(nameEQ.length, c.length));
            
            if (wasObject) {
        	try {
        	    toRet = JSON.parse(toRet);
        	} catch (err) {
        	    return null;
        	}
            }
            
            return toRet;
        }
        
    }
    return null;
}

function eraseCookie(name) {
    createCookie(name, "", -1);
}
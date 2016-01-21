/**
 * An customization of
 * 
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * 
 * TODO: Should migrate this "Factory" to an AngularJS module
 */
(function() {
    'use strict';

    var localforageCustomizationBuffer = [];

    var addsessionStorageWrapper = function() {
	// Add the https://github.com/thgreasi/localForage-sessionStorageWrapper
	// sessionStorage project designed as a driver for localForage
	var sessionStorageWrapper = window.sessionStorageWrapper;
	localforage.defineDriver(sessionStorageWrapper).then(function() {

	});
    }

    var setLocalforageConfig = function() {
	localforage.config({
	    name : 'Knihovny.cz'
	});
    }

    localforageCustomizationBuffer.push(addsessionStorageWrapper);
    
    localforageCustomizationBuffer.push(setLocalforageConfig);
    
    if (typeof localforage === "object")
	localforageCustomizationBuffer.forEach(function(toCall) {
	    toCall.call();
	});
    else
	console.error("Customizing localforage before it is loaded by a script is not allowed !!");

}).call(typeof window !== 'undefined' ? window : self);

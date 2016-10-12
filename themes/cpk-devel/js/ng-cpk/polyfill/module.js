/**
 * Polyfill AngularJS dependency filler for VuFind.
 * 
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
(function() {

    var polyfill = angular.module('polyfill', []);

    polyfill.run(function() {

	var usesIE = (navigator.appName == 'Microsoft Internet Explorer' || !!(navigator.userAgent.match(/Trident/) || navigator.userAgent.match(/rv 11/)));

	if (usesIE) {
	    // Polyfill Array.find
	    if (!Array.prototype.find) {
		Array.prototype.find = function(predicate) {
		    'use strict';
		    if (this == null) {
			throw new TypeError('Array.prototype.find called on null or undefined');
		    }
		    if (typeof predicate !== 'function') {
			throw new TypeError('predicate must be a function');
		    }
		    var list = Object(this);
		    var length = list.length >>> 0;
		    var thisArg = arguments[1];
		    var value;

		    for (var i = 0; i < length; i++) {
			value = list[i];
			if (predicate.call(thisArg, value, i, list)) {
			    return value;
			}
		    }
		    return undefined;
		};
	    }

	    if (typeof window.CustomEvent === 'object') {

		function CustomEventCtor(eventName) {

		    var event;
		    if (document.createEvent) {
			event = document.createEvent('HTMLEvents');
			event.initEvent(eventName, true, true);
		    } else if (document.createEventObject) {
			event = document.createEventObject();
			event.eventType = eventName;
		    }
		    event.eventName = eventName;

		    return event;
		}

		window.CustomEvent = CustomEventCtor;
	    }
	}

	window.usesIE = usesIE;
    })
}())
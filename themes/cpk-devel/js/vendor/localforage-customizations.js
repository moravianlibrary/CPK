/**
 * An customization of localforage project hosted by Mozilla.
 * 
 * See http://mozilla.github.io/localForage/#data-api
 * 
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * 
 */
(function() {
    'use strict';

    localforage.ready().then(function() {

	if (typeof localforage === "object" && typeof localforage.config === "function") {

	    /**
	     * Configure the localforage
	     */
	    (function() {
		localforage.config({
		    name : 'Knihovny.cz'
		});

		// Other configuration / customization stuff goes here ..
	    })();

	} else {
	    console.error("Customizing localforage before it is loaded by a script is not allowed !!");
	}
    });
}).call(typeof window !== 'undefined' ? window : self);

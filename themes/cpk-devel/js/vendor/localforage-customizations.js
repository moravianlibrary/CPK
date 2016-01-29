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

	/**
	 * Configure the localforage
	 */
	localforage.config({
	    name : 'Knihovny.cz'
	});

	// Other configuration / customization stuff goes here ..
    });
})();

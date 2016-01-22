/**
 * An customization of
 * 
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * 
 */
(function() {
    'use strict';

    if (typeof localforage === "object" && typeof localforage._config === "object") {
	
	/**
	 * sessionStorage driver taken from:
	 * https://github.com/thgreasi/localForage-sessionStorageWrapper
	 */
	(function() {

	    var sessionStorageWrapper = window.sessionStorageWrapper;
	    localforage.defineDriver(sessionStorageWrapper).then(function() {

	    });
	})();

	/**
	 * Configure the localforage
	 */
	(function() {
	    localforage.config({
		name : 'Knihovny.cz'
	    });

	    localforage.SESSIONSTORAGE = "sessionStorageWrapper";
	    
	    // Let's override the setDriver method so that it remembers lastDriver used
	    localforage._setDriver = localforage.setDriver;
	    localforage._lastDriver = localforage.driver(); 
	    
	    localforage.setDriver = function(driver) {
		
		localforage._lastDriver = localforage.driver();
		localforage._setDriver(driver);
	    }
	    
	    
	    localforage.setLastDriver = function() {
		
		localforage.setDriver(localforage._lastDriver);
	    }
	})();
	
    } else {
	console.error("Customizing localforage before it is loaded by a script is not allowed !!");
    }

}).call(typeof window !== 'undefined' ? window : self);

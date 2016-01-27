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

	    // Private var
	    var locked = false, buffer = [];

	    // Let's override the setDriver method so that it remembers
	    // lastDriver used
	    localforage._setDriver = localforage.setDriver;
	    localforage._lastDriver = localforage.driver();

	    localforage.setDriver = function(driver) {

		/*
		 * lock the localforage to prevent use of session storage under
		 * inconvinient circumstances
		 */
		if (driver === localforage.SESSIONSTORAGE)
		    locked = true;

		localforage._lastDriver = localforage.driver();
		localforage._setDriver(driver);
	    }

	    localforage.setLastDriver = function() {

		// unlock it & execute the buffer collected
		if (localforage._driver === localforage.SESSIONSTORAGE) {
		    locked = false;

		    buffer.forEach(function(func) {
			func.call();
		    });

		    buffer = [];
		    
		    localforage.setDriver(localforage._lastDriver);
		    
		    locked = false;
		} else {

		    localforage.setDriver(localforage._lastDriver);
		    
		}
	    }

	    // lock implementation
	    localforage.setSessionItem = setSessionItem;
	    localforage.getSessionItem = getSessionItem;
	    
	    function setSessionItem(key, value, successCallback) {
		return new Promise(function(resolve, reject) {
		    
		    localforage.setDriver(localforage.SESSIONSTORAGE);
		    
		    localforage.setItem(key, value, successCallback).then(function(val) {
			
			localforage.setLastDriver();
			
			resolve(val);
			
		    }).catch(function(reason) {

			localforage.setLastDriver();
			
			reject(reason);
		    });
		});
	    }
	    
	    function getSessionItem(key, successCallback) {
		return new Promise(function(resolve, reject) {
		    
		    localforage.setDriver(localforage.SESSIONSTORAGE);
		    
		    localforage.getItem(key, successCallback).then(function(val) {
			
			localforage.setLastDriver();
			
			resolve(val);
			
		    }).catch(function(reason) {

			localforage.setLastDriver();
			
			reject(reason);
		    });
		});
	    }
	    
	    localforage._setItem = localforage.setItem;
	    localforage._getItem = localforage.getItem;
	    
	    localforage.setItem = function(key, value, successCallback) {
		return new Promise(function(resolve, reject) {
		    
		    var theJob = function() {
			localforage._setItem(key, value, successCallback).then(function(val) {
			    resolve(val);
			}).catch(function(reason) {
			    reject(reason);
			});
		    };
		    
		    if (locked) {			
			buffer.push(theJob);
		    } else {
			theJob.call();
		    }
		});
	    }
	    
	    localforage.getItem = function(key, successCallback) {
		return new Promise(function(resolve, reject) {
		    
		    var theJob = function() {
			localforage._getItem(key, successCallback).then(function(val) {
			    resolve(val);
			}).catch(function(reason) {
			    reject(reason);
			});
		    };
		    
		    if (locked) {			
			buffer.push(theJob);
		    } else {
			theJob.call();
		    }
		});
	    }

	})();

    } else {
	console.error("Customizing localforage before it is loaded by a script is not allowed !!");
    }

}).call(typeof window !== 'undefined' ? window : self);

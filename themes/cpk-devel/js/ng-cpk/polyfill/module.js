/**
 * Polyfill AngularJS dependency filler for VuFind.
 * 
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
(function() {

    var polyfill = angular.module('polyfill', []);

    polyfill.run(function($injector) {

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

	    SyncPromiseAsPromise();
	
	}

	window.usesIE = usesIE;

	function SyncPromiseAsPromise() {
		(function (exprt) {
		function isPromise(p) {
		  return p && typeof p.then === 'function';
		}
		function addReject(prom, reject) {
		  prom.then(null, reject) // Use this style for sake of non-Promise thenables (e.g., jQuery Deferred)
		}
		
		// States
		var PENDING = 2,
		    FULFILLED = 0, // We later abuse these as array indices
		    REJECTED = 1;
		
		function Promise(fn) {
		  var self = this;
		  self.v = 0; // Value, this will be set to either a resolved value or rejected reason
		  self.s = PENDING; // State of the promise
		  self.c = [[],[]]; // Callbacks c[0] is fulfillment and c[1] contains rejection callbacks
		  self.a = false; // Has the promise been resolved synchronously
		  var syncResolved = true;
		  function transist(val, state) {
		    self.a = syncResolved;
		    self.v = val;
		    self.s = state;
		    if (state === REJECTED && !self.c[state].length) {
		      throw val;
		    }
		    self.c[state].forEach(function(fn) { fn(val); });
		    self.c = null; // Release memory.
		  }
		  function resolve(val) {
		    if (!self.c) {
		      // Already resolved, do nothing.
		    } else if (isPromise(val)) {
		      addReject(val.then(resolve), reject);
		    } else {
		      transist(val, FULFILLED);
		    }
		  }
		  function reject(reason) {
		    if (!self.c) {
		      // Already resolved, do nothing.
		    } else if (isPromise(reason)) {
		      addReject(reason.then(reject), reject);
		    } else {
		      transist(reason, REJECTED);
		    }
		  }
		  fn(resolve, reject);
		  syncResolved = false;
		}
		
		var prot = Promise.prototype;
		
		prot.then = function(cb, errBack) {
		  var self = this;
		  if (self.a) { // Promise has been resolved synchronously
	//	    throw new Error('Cannot call `then` on synchronously resolved promise');
		  }
		  return new Promise(function(resolve, reject) {
		    var rej = typeof errBack === 'function' ? errBack : reject;
		    function settle() {
		      try {
		        resolve(cb ? cb(self.v) : self.v);
		      } catch(e) {
		        rej(e);
		      }
		    }
		    if (self.s === FULFILLED) {
		      settle();
		    } else if (self.s === REJECTED) {
		      rej(self.v);
		    } else {
		      self.c[FULFILLED].push(settle);
		      self.c[REJECTED].push(rej);
		    }
		  });
		};
		
		prot.catch = function(cb) {
		  var self = this;
		  if (self.a) { // Promise has been resolved synchronously
	//	    throw new Error('Cannot call `catch` on synchronously resolved promise');
		  }
		  return new Promise(function(resolve, reject) {
		    function settle() {
		      try {
		        resolve(cb(self.v));
		      } catch(e) {
		        reject(e);
		      }
		    }
		    if (self.s === REJECTED) {
		      settle();
		    } else if (self.s === FULFILLED) {
		      resolve(self.v);
		    } else {
		      self.c[REJECTED].push(settle);
		      self.c[FULFILLED].push(resolve);
		    }
		  });
		};
		
		Promise.all = function(promises) {
		  return new Promise(function(resolve, reject, l) {
		    l = promises.length;
		    var hasPromises = false;
		    promises.forEach(function(p, i) {
		      if (isPromise(p)) {
		        hasPromises = true;
		        addReject(p.then(function(res) {
		          promises[i] = res;
		          --l || resolve(promises);
		        }), reject);
		      } else {
		        --l || (hasPromises ? resolve(promises) : (function () {
		          throw new Error('Must use at least one promise within `Promise.all`');
		        }()));
		      }
		    });
		  });
		};
		
		Promise.race = function(promises) {
		  var resolved = false;
		  return new Promise(function(resolve, reject) {
		    promises.some(function(p, i) {
		      if (isPromise(p)) {
		        addReject(p.then(function(res) {
		          if (resolved) {
		            return;
		          }
		          resolve(res);
		          resolved = true;
		        }), reject);
		      } else {
		        throw new Error('Must use promises within `Promise.race`');
		      }
		    });
		  });
		};
		exprt.Promise = Promise;
		}(window));
	}
    })
}())

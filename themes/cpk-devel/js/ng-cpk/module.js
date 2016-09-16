/**
 * An CPK ng-app bundling all minor portal angular apps together so their
 * controllers can be injected anywhere on the portal.
 * 
 * @author Jiří Kozlovský
 */
(function() {
    
    var cpkApp = angular.module('cpk', [ 'polyfill', 'favorites', 'notifications', 'federativeLogin', 'admin', 'history' ]);
    	
    cpkApp.config(['$locationProvider', function($locationProvider) {
	$locationProvider.html5Mode({
	    enabled: true,
	    requireBase: false,
	    rewriteLinks: false
	});
    }]);
})();
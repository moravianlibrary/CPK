/**
 * An CPK ng-app bundling all minor portal angular apps together so their
 * controllers can be injected anywhere on the portal.
 * 
 * @author Jiří Kozlovský
 */
(function() {
    angular.module('cpk', [ 'favorites', 'notifications', 'federativeLogin' ]).controller('MainController', MainController);

    MainController.$inject = [ 'favsBroadcaster' ];

    function MainController(favsBroadcaster) {

	var vm = this;

	// We need to initialize injected services on every page ..

	return vm;
    }
})();
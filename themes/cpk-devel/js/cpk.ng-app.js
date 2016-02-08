/**
 * An CPK ng-app bundling all minor portal angular apps together so their
 * controllers can be injected anywhere on the portal.
 * 
 * @author Jiří Kozlovský
 */
(function() {
    angular.module('cpk', [ 'favorites' ]).controller('MainController', MainController);

    MainController.$inject = [ 'broadcaster' ];

    function MainController(broadcaster) {

	var vm = this;

	// We need to initialize broadcaster on every page ..

	return vm;
    }
})();
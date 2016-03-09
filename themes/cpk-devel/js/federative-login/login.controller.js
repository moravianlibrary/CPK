/**
 * Federation login controller.
 * 
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 */
(function() {
    angular.module('federativeLogin').controller('LoginController', LoginController).directive('ngLogin', loginDirective);

    LoginController.$inject = [ '$log' ];
    
    loginDirective.$inject = [ '$log' ];

    var loginModal = undefined;

    function LoginController($log) {

	var vm = this;

	vm.login = login;

	return vm;
	//

	function login(url) {
	    
	    if ((new URL(url)).host !== location.host) {
		// TODO: Send an attack report
		return $log.error('You are probably facing an Man-in-the-middle attack ! Please report this issue to portal administrator');
	    }

	    var overlay = document.createElement('iframe');

	    overlay.src = url;

	    // Needed for CSS styling
	    overlay.id = 'overl';
	    overlay.onload = onOverlayLoad;

	    loginModal.appendChild(overlay);

	    function onOverlayLoad() {

		var closer = document.createElement('div');

		// Needed for CSS styling
		closer.id = 'closer';

		closer.innerHTML = '&times;';

		closer.onclick = function() {
		    overl.remove();
		    closer.remove();
		};

		document.body.appendChild(closer);
	    }
	}

    }

    function loginDirective($log) {
	return {
	    restrict : 'A',
	    link : linker
	};

	function linker(scope, elements, attrs) {
	    // Assing the loader to the 'local' variable
	    switch (attrs.ngLogin) {

	    case 'modal':

		loginModal = elements.context;
		break;

	    default:
		$log.error('Linker for login controller failed linking');
	    }
	}
    }
})();
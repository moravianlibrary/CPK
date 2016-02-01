/**
 * Translate filter for angular using VuFind.translate built-in function
 * 
 * @author Jiří Kozlovský
 */
(function() {
    angular.module('favorites').filter('translate', translateFilter);

    function translateFilter() {
	return function(input) {
	    if ('translate' in VuFind) {
		return VuFind.translate(input);
	    }
	    
	    return input
	}
    }
})();
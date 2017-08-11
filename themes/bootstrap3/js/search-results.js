/**
 * Async search-results.js v 0.8
 * @Author Martin Kravec <martin.kravec@mzk.cz>
 */
jQuery( document ).ready( function( $ ) {

    if(typeof Storage == "undefined") {
        console.error( 'localStorage and sessionStorage  are NOT supported in this browser' );
    }
    console.log(localStorage.getItem('facetsApplied'));
    localStorage.setItem("facetsApplied", parseInt('0'));

    /*
    @TODO Shake button thaht applies clicked facets or show message 'Do not forget to apply your changes'. This could be done on background using Web Workers.

    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    while( true ) {
        if (localStorage['facetsApplied']) {
            parseInt(localStorage.getItem("facetsApplied") > 0) {
                //shake button or show message
            }
            sleep(30000); // 0.5 minutes
        }
    }
    */
	
	ADVSEARCH = {

        /**
         * Update form's DOM state for groups.
         *
         * This function updates numbers in form's DOM to ensure, that jQuery
         * will correctly handle showing or hiding some elements in form.
         *
         * @param    {string}        formSelector
         * @return    {undefined}
         */
        updateGroupsDOMState: function (formSelector) {
            var groupsCount = $(formSelector).find('.group').length;
            if (groupsCount == 1) {
                $('.remove-advanced-search-group').parent().hide('blind', {}, 200);
                $('#group-join-type-row').hide('blind', {}, 200);
                $('.not-query').attr('hidden', 'hidden');
            } else {
                $('.remove-advanced-search-group').parent().show('blind', {}, 200);
                $('#group-join-type-row').show('blind', {}, 200);
                $('.not-query').removeAttr('hidden');
            }
        },

        /**
         * Update form's DOM state for queries.
         *
         * This function updates numbers in form's DOM to ensure, that jQuery
         * will correctly handle showing or hiding some elements in form.
         *
         * @param    {string}        groupSelector
         * @return    {undefined}
         */
        updateQueriesDOMState: function (groupSelector) {
            var queriesCount = $(groupSelector + ' .queries').length;
            if (queriesCount == 1) {
                $(groupSelector).find('.remove-advanced-search-query').parent().hide('blind', {}, 200);
            } else {
                $(groupSelector).find('.remove-advanced-search-query').parent().show('blind', {}, 200);
            }
        },

        /**
         * Switch searchtype template
         *
         * @param    {object}        data    Object with lookFor, bool, etc.
         * @return    {undefined}
         */
        switchSearchTemplate: function (data, callbacks) {
            $('.search-panel').hide('blind', {}, 500, function () {

                if (data.searchTypeTemplate == 'advanced') {
                    $('.search-type-template-switch').text(VuFind.translate('Basic Search'));
                    $('.advanced-search-panel').removeClass('hidden');
                    $('.basic-search-panel').addClass('hidden');
                } else {
                    $('.search-type-template-switch').text(VuFind.translate('Advanced Search'));
                    $('.advanced-search-panel').addClass('hidden');
                    $('.basic-search-panel').removeClass('hidden');
                }

                ADVSEARCH.updateSearchTypeTemplates(data);

                $('.search-panel').show('blind', {}, 500, function() {
                    if (callbacks !== undefined && callbacks['afterSwitchSearchTemplate']) {
                        callbacks.afterSwitchSearchTemplate();
                    }
                });
            });
        },

        /**
         * This function gathers data from autocomplete|advancedSearch|windowHistory.
         * The data are sent via ajax to Solr, which returns results.
         * These results are displayed async via jQuery UI.
         *
         * This function also handles live url changes with window.history.pushState,
         * popState and replaceState.
         *
         * @param {JSON}    dataFromWindowHistory
         * @param {JSON}    dataFromAutocomplete
         * @param {string}  newSearchTypeTemplate        basic|advanced
         * @param {array}   extraData                    Associative array
         * @param {array}   callbacks
         *
         * @return {undefined}
         */
        updateSearchResults: function (dataFromWindowHistory, dataFromAutocomplete, newSearchTypeTemplate, extraData, callbacks) {

            var data = {};

			/* If we need to add some new paramts to URL we can use extraData argument */
            if (extraData !== undefined) {
                for (var key in extraData) {
                    if (extraData.hasOwnProperty(key)) {
                        data[key] = extraData[key];
                    }
                }
                console.log('Added extraData:');
                console.log(data);
            }

            var reloadResults = true;

            if (dataFromWindowHistory !== undefined) {
				/* 
				 * If moving in browser history, take data from window.history 
				 * instead of gather some form.
				 */
                data = dataFromWindowHistory;
                var activeDatabase = $('#set-database li.active a').attr('data-value');
                if (data['database'] != activeDatabase) {
                    if (data['database'] && data['database'] == 'EDS') {
                        $("#set-database li a[data-value='Solr']").parent().removeClass('active');
                        $("#set-database li a[data-value='EDS']").parent().addClass('active');
                    } else {
                        $("#set-database li a[data-value='EDS']").parent().removeClass('active');
                        $("#set-database li a[data-value='Solr']").parent().addClass('active');
                    }
                }

            } else if (dataFromAutocomplete) {
				/* 
				 * If search started in autocomplete, gather data from autocomplete form 
				 */
                data = queryStringToJson(dataFromAutocomplete.queryString);
                if (data.lookfor0) {
                    var templookfor0 = data.lookfor0[0];
                    data['lookfor0'] = [];
                    data['lookfor0'].push(templookfor0);
                }

                if (data.type0) {
                    var temptype0 = data.type0[0];
                    data['type0'] = [];
                    data['type0'].push(temptype0);
                }

                if (data.join[0]) {
                    data['join'] = data.join[0];
                }

                if (data.keepEnabledFilters[0]) {
                    data['keepEnabledFilters'] = data.keepEnabledFilters[0];
                }

                if (data.page[0]) {
                    data['page'] = data.page[0];
                }

                data['searchTypeTemplate'] = 'basic';

                var database = $('#set-database li.active a').attr('data-value');
                data['database'] = database;

                //console.log( 'Data fromautocomplete: ' );
                //console.log( data );

            } else {
				/* If search started in advanced search, gather data from
				 * advances search form and hidden facetFilters 
				 */
                $('select.query-type, input.query-string, select.group-operator').each(function (index, element) {
                    var key = $(element).attr('name').slice(0, -2);
                    if (!data.hasOwnProperty(key)) {
                        data[key] = [];
                    }
                    data[key].push($(element).val());
                });

                var allGroupsOperator = $('input[name="join"]').val();
                data['join'] = allGroupsOperator;

                if (!data.hasOwnProperty('searchTypeTemplate')) {
                    var searchTypeTemplate = $("input[name='searchTypeTemplate']").val();

                    if (searchTypeTemplate) {
                        data['searchTypeTemplate'] = searchTypeTemplate;
                    } else {
                        data['searchTypeTemplate'] = 'advanced';
                    }
                }

                var database = $('#set-database li.active a').attr('data-value');
                data['database'] = database;

            }

            if (dataFromWindowHistory !== undefined) {
                ADVSEARCH.removeAllFilters(false);

                var deCompressedFilters = LZString.decompressFromBase64(specialUrlDecode(data['filter']));
                if ((deCompressedFilters != '') && (null != deCompressedFilters)) {
                    if (deCompressedFilters.indexOf('|') > -1) {
                        deCompressedFilters = deCompressedFilters.split("|");
                    } else {
                        var onlyFilter = deCompressedFilters;
                        deCompressedFilters = [];
                        deCompressedFilters[0] = onlyFilter;
                    }
                }

                if (null != deCompressedFilters) {
                    if (deCompressedFilters[0] != null) {
                        var html = '';
                        deCompressedFilters.forEach(function (filter) {
                            html = "<input type='hidden' class='hidden-filter' name='filter[]' value='" + filter + "'>";
                        });
                        $('#hiddenFacetFilters').append(html);
                    }
                    data['filter'] = deCompressedFilters;
                }

            } else {
                var filters = [];
                $('.hidden-filter').each(function (index, element) {
                    filters.push($(element).val());
                });

                data['filter'] = filters;
            }


            if (dataFromAutocomplete) {
                var tempData = queryStringToJson(dataFromAutocomplete.queryString);
                if (tempData.keepEnabledFilters == 'false') {
                    data['filter'] = [];
                }
            }

			/* 
			 * Autocomplete form does not have all the data, that are 
			 * nessessary to perform search, thus this will set default ones.
			 */
            if (dataFromWindowHistory == undefined) {

                if ((!data.hasOwnProperty('bool0')) || ( !data.bool0 )) {
                    data['bool0'] = [];
                    data['bool0'].push('AND');
                }

                if ((!data.hasOwnProperty('join') ) || ( !data.join )) {
                    data['join'] = 'AND';
                }

				/* 
				 * Set search term and type from Autocomplete when provding 
				 * async results loading in basic search 
				 */
                if (!data.hasOwnProperty('lookfor0')) {
                    var lookfor0 = $("input[name='last_searched_lookfor0']").val();
                    data['lookfor0'] = [];
                    data['lookfor0'].push(lookfor0);
                }
                
                if (!data.hasOwnProperty('type0')) {
                    var type = $("input[name='last_searched_type0']").val();
                    data['type0'] = [];
                    data['type0'].push(type);
                }

                if (!data.hasOwnProperty('limit')) {
                    var limit = $("input[name='limit']").val();
                    data['limit'] = limit;
                } else {
                    if (Object.prototype.toString.call(data['limit']) === '[object Array]') {
                        data['limit'] = data['limit'][0];
                    }
                }

                if (!data.hasOwnProperty('sort')) {
                    var sort = $("input[name='sort']").val();
                    data['sort'] = sort;
                } else {
                    if (Object.prototype.toString.call(data['sort']) === '[object Array]') {
                        data['sort'] = data['sort'][0];
                    }
                }

                if (!data.hasOwnProperty('page')) {
                    var page = $("input[name='page']").val();
                    data['page'] = page;
                }

                var isSetDateRange = false;
                data['filter'].forEach(function (filter) {
                    if (filter.indexOf('daterange') !== -1) {
                        isSetDateRange = true;
                        return false;
                    }
                });

                if (isSetDateRange) {
                    if (!data.hasOwnProperty('publishDatefrom')) {
                        var publishDatefrom = $("input[name='publishDatefrom']").val();
                        data['publishDatefrom'] = publishDatefrom;
                    }

                    if (!data.hasOwnProperty('publishDateto')) {
                        var publishDateto = $("input[name='publishDateto']").val();
                        data['publishDateto'] = publishDateto;
                    }

                    if (!data.hasOwnProperty('daterange')) {
                        var daterange = $("input[name='daterange']").val();
                        data['daterange'] = daterange;
                    }
                }
            }

			/* Set last search */
            var lastSearchedLookFor0 = data['lookfor0'][0];
            $("input[name='last_searched_lookfor0']").val(lastSearchedLookFor0);

            var lastSearchedType0 = data['type0'][0];
            $("input[name='last_searched_type0']").val(lastSearchedType0);

            var searchTypeTemplate = data['searchTypeTemplate'];
            $("input[name='searchTypeTemplate']").val(searchTypeTemplate);

            var database = data['database'];
            $("input[name='database']").val(database);

            var publishDatefrom = data['publishDatefrom'];
            $("input[name='publishDatefrom']").val(publishDatefrom);

            var publishDateto = data['publishDateto'];
            $("input[name='publishDateto']").val(publishDateto);

            var daterange = data['daterange'];
            $("input[name='daterange']").val(daterange);

			/* 
			 * If we want to just switch template between basic and advanced search,
			 * we need to again to gather data from forms 
			 * (becasue of future movement in browser history) and then switch 
			 * the templates.
			 */
            if (newSearchTypeTemplate) {

                data['searchTypeTemplate'] = newSearchTypeTemplate;

                ADVSEARCH.switchSearchTemplate(data, callbacks);

                reloadResults = false;
            }

			/* 
			 * Live update url.
			 */

            if (data['filter'][0] != null) {
                //console.log( 'Filters:' );
                //console.log( data['filter'] );

                //console.log( 'Filters as string:' );
                var filtersAsString = data['filter'].join('|');
                //console.log( filtersAsString );

                //console.log( 'Compressed filters:' );
                var compressedFilters = specialUrlEncode(LZString.compressToBase64(filtersAsString));
                //console.log( compressedFilters );

                //console.log( 'DeCompressed filters:' );
                var deCompressedFilters = LZString.decompressFromBase64(specialUrlDecode(compressedFilters));
                //console.log( deCompressedFilters.split( "|" ) );

            }

            var dataForAjax = data;

            if (data['filter'].length > 0) {
                data['filter'] = compressedFilters;
            }

            //console.log('dataForAjax now:');
            //console.log(dataForAjax)

            //console.log('data now:');
            //console.log(data)

            if (dataFromWindowHistory == undefined) {
                ADVSEARCH.updateUrl(data);
            } else { // from history
                ADVSEARCH.replaceUrl(data);
                reloadResults = true;
            }

			/*
			 * Send current url (for link in full view Go back to search results)
			 * */
            data['searchResultsUrl'] = window.location.href;

			/*
			 * Get search results from Solr and display them
			 *
			 * There can be some situations where we do not want to reload
			 * search results. E.g. when we are just switching templates.
			 *
			 */
            if (reloadResults) {
				/* Search */
                $.ajax({
                    type: 'POST',
                    cache: false,
                    dataType: 'json',
                    url: VuFind.getPath() + '/AJAX/JSON?method=updateSearchResults',
                    data: dataForAjax,
                    beforeSend: function () {

                        scrollToTop();

                        var loader = "<div id='search-results-loader' class='text-center'></div>";
                        $('#result-list-placeholder').hide('blind', {}, 200, function () {
                            $('#result-list-placeholder').before(loader);
                        });
                        $('#results-amount-info-placeholder').html("<i class='fa fa-2x fa-refresh fa-spin'></i>");
                        $('#pagination-placeholder, #side-facets-placeholder').hide('blind', {}, 200);

                        // Disable submit button until ajax finishes
                        $('#submit-edited-advanced-search', '.ajax-update-limit', '.ajax-update-sort').attr('disabled', true);

                        // Let another applications know we are loading new results ..
                        var event = document.createEvent("CustomEvent");
                        event.initCustomEvent('searchResultsLoading', false, false, {});
                    },
                    success: function (response) {

                        if ($('#set-database li.active a').attr('data-value') != data['database']) {
                            return;
                        }

                        scrollToTop();

                        if (response.status == 'OK') {

                            if (data['filter'].length < 1) {
								/* Remove filters from containers when performing search without keeping enabled facets */
                                $('#hiddenFacetFilters').html('');
                            }

                            var responseData = response.data;
                            var resultsHtml = JSON.parse(responseData.resultsHtml);
                            var facetsHtml = JSON.parse(responseData.sideFacets);
                            var resultsAmountInfoHtml = JSON.parse(responseData.resultsAmountInfoHtml);
                            var paginationHtml = JSON.parse(responseData.paginationHtml);

							/* Save results to local storage for swithing to next/previous record of search results */
                            if (typeof(Storage) !== 'undefined') {
                                var extraResults = responseData.viewData.extraResults;
                                var referer = responseData.viewData.referer;
                                localStorage.setItem(referer, JSON.stringify(extraResults));
                            } else {
                                console.error('Sorry! No Web Storage support.');
                            }

							/* Ux content replacement */
                            $('#search-results-loader').remove();
                            $('#result-list-placeholder, #pagination-placeholder').css('display', 'none');
                            $('#result-list-placeholder').html(decodeHtml(resultsHtml.html));
                            $('#pagination-placeholder').html(paginationHtml.html);
                            $('#results-amount-info-placeholder').html(resultsAmountInfoHtml.html);
                            $('#side-facets-placeholder').html(facetsHtml.html);
                            $('#result-list-placeholder, #pagination-placeholder, #results-amount-info-placeholder').show('blind', {}, 500);
                            $('#side-facets-placeholder').show('blind', {}, 500);

							/* Update search identificators */
                            $('#rss-link').attr('href', window.location.href + '&view=rss');
                            $('.mail-record-link').attr('id', 'mailSearch' + responseData.searchId);
                            $('#add-to-saved-searches').attr('data-search-id', responseData.searchId);
                            $('#remove-from-saved-searches').attr('data-search-id', responseData.searchId);
                            $('#remove-from-saved-searches').attr('title', VuFind.translate('Save search'));
                            $('#remove-from-saved-searches').text(VuFind.translate('Save search'));
                            $('#remove-from-saved-searches').attr('id', 'add-to-saved-searches');

                            $(' #flashedMessage div .alert').hide('blind', {}, 500);

							/* Update lookfor inputs in both search type templates to be the same when switching templates*/
                            ADVSEARCH.updateSearchTypeTemplates(data);

							/* If filters enabled, show checkbox in autocomplete */
                            if (data.filter.length > 0) {
                                $('#keep-facets-enabled-checkbox').show('blind', {}, 500);
                            } else {
                                $('#keep-facets-enabled-checkbox').hide('blind', {}, 200);
                            }

                            //console.log(' responseData.recordTotal: ');
                            //console.log( responseData.recordTotal );
                            //console.log(' Success happened ');

							/* Hide no results container when there is more than 0 results */
                            if (responseData.recordTotal > 0) {
                                //console.log(' responseData.recordTotal: ');
                                //console.log( responseData.recordTotal );
                                //console.log( 'Hide no resuls, show new results' );
                                $('#no-results-container').hide('blind', {}, 200, function () {
                                    $(this).css('display', 'none');
                                });
                                $('.result-list-toolbar, #limit, #sort_options_1, #bulk-action-buttons-placeholder, #search-results-controls, #limit-container, .save-advanced-search-results, .save-basic-search-results').show('blind', {}, 500);
                            } else {
                                //console.log(' responseData.recordTotal: ');
                                //console.log( responseData.recordTotal );
                                //console.log( 'Show NO results' );
                                $('#no-results-container strong').text(data.lookfor0[0]);

                                $('#no-results-container').show('blind', {}, 500);
                                $('.result-list-toolbar, #limit, #sort_options_1, #bulk-action-buttons-placeholder, #search-results-controls, #limit-container, .save-advanced-search-results, .save-basic-search-results').hide('blind', {}, 200);
                            }

                            // Let another applications know we have loaded new results ..
                            var event = document.createEvent("CustomEvent");
                            event.initCustomEvent('searchResultsLoaded', false, false, {});

                            localStorage.setItem("facetsApplied", parseInt('0'));

                        } else {
                            console.error(response.data);
                            console.error(response);
                        }
                        $('#submit-edited-advanced-search', '.ajax-update-limit', '.ajax-update-sort').removeAttr('selected');

						/*
						 * Opdate sort and limit selects, when moving in history back or forward.
						 * We need to use this f****** stupid robust solution to prevent
						 * incompatibility and bad displaying of options that are
						 * in real selected
						 */
                        $('.ajax-update-limit option').prop('selected', false);
                        $('.ajax-update-limit').val([]);
                        $('.ajax-update-limit option').removeAttr('selected');

                        $('.ajax-update-sort option').prop('selected', false);
                        $('.ajax-update-sort').val([]);
                        $('.ajax-update-sort option').removeAttr('selected');

                        $('.ajax-update-limit').val(data.limit);
                        $('.ajax-update-limit option[value=' + data.limit + ']').attr('selected', 'selected');
                        $('.ajax-update-limit option[value=' + data.limit + ']').attr('selected', true);

                        $('.ajax-update-sort').val(data.sort);
                        $('.ajax-update-sort option[value="' + data.sort + '"]').attr('selected', 'selected');
                        $('.ajax-update-sort option[value="' + data.sort + '"]').attr('selected', true);

                        /* Load limit and sort options for EDS */
                        if (data['database'] == 'EDS') {
                            var limits = JSON.parse(responseData.edsLimits).data;
                            var sorts = JSON.parse(responseData.edsDefaultSorts).data;
                        } else if(data['database'] == 'Solr') {
                            var limits = JSON.parse(responseData.solrLimits).data;
                            var sorts = JSON.parse(responseData.solrDefaultSorts).data;
                        }
                        if (limits) {
                            var $limitsSelect = $('.apply-limit').parent().parent();
                            $limitsSelect.empty();
                            limits.forEach(function(limit){
                                $limitsSelect.append("<li><a href='#' class='apply-limit' data-limit='"+limit+"'>"+limit+"</a></li>");
                            });
                        }

                        if (responseData.edsMaxLimit) {
                            console.log(JSON.parse(responseData.edsMaxLimit).data);
                        }

                        if (responseData.edsDefaultSorts) {
                            console.log(JSON.parse(responseData.edsDefaultSorts).data);
                        }

                        if (sorts) {
                            var $sortsSelect = $('.apply-sort').parent().parent();
                            $sortsSelect .empty();
                            sorts.forEach(function(sort){
                                $sortsSelect .append("<li><a href='#' class='apply-sort' data-sort='"+sort.key+"'>"+sort.value+"</a></li>");
                            });
                        }

                    },
                    error: function (xmlHttpRequest, status, error) {
                        $('#search-results-loader').remove();
                        console.error(xmlHttpRequest.responseText);
                        //console.log(xmlHttpRequest);
                        console.error(status);
                        console.error(error);
                        //console.log( 'Sent data: ' );
                        //console.log( data );
                    },
                });
            }
        },

        /**
         * Add facet to container and update search results
         *
         * @param    {string}    value            institutions:"0/Brno/"
         * @param    {boolean}    updateResults    Wanna update results?
         * @return    {undefined}
         */
        addFacetFilter: function (value, updateResults) {
            var enabledFacets = 0;
            $('#hiddenFacetFilters input').each(function (index, element) {
                if ($(element).val() == value) {
                    ++enabledFacets;
                    return false; // javascript equivalent to php's break;
                }
            });

            if (enabledFacets == 0) { /* This filter not applied yet, apply it now */
                var html = "<input type='hidden' class='hidden-filter' name='filter[]' value='" + value + "'>";
                $('#hiddenFacetFilters').append(html);
            }

            if (updateResults) {
                ADVSEARCH.updateSearchResults(undefined, undefined);
            }
        },

        /**
         * Remove facet from container and update search results
         *
         * @param    {string}    value            institutions:"0/Brno/"
         * @param    {boolean}    updateResults    Wanna update results?
         * @return    {undefined}
         */
        removeFacetFilter: function (value, updateResults) {

            var extraData = {};
            $('#hiddenFacetFilters input').each(function (index, element) {
                if ($(element).val() == value) {
                    $(this).remove();
                }

				/* Special if, for publishDate */
                var substring = "publishDate";
                if (value.includes(substring) && $(element).val().includes(substring)) {
                    $(this).remove();
                    extraData['daterange'] = '';
                    extraData['publishDatefrom'] = '';
                    extraData['publishDateto'] = '';
                }
				/**/
            });

            if (updateResults) {
                ADVSEARCH.updateSearchResults(undefined, undefined, undefined, extraData);
            }
        },

        /**
         * Remove all filters
         *
         * @param    {boolean}    updateResults    Wanna update results?
         * @return    {undefined}
         */
        removeAllFilters: function (updateResults) {
            $('#hiddenFacetFilters input').remove();

            if (updateResults) {
                ADVSEARCH.updateSearchResults(undefined, undefined);
            }
        },

        /**
         * Update URL with provided data via pushing state to window history
         *
         * @param    {Object}    data    Object with lookFor, bool, etc.
         * @return    {undefined}
         */
        updateUrl: function (data) {
	    if (window.location.href.indexOf('/EDS/') != -1){
		data['database'] = 'EDS';
		$("#set-database li a[data-value='EDS']").parent().addClass("active");
		$("#set-database li a[data-value='Solr']").parent().removeClass("active");
	    }
            var stateObject = data;
            var title = 'New search query';
            var url = '/Search/Results/?' + jQuery.param(data)
            window.history.pushState(stateObject, title, url);
            //console.log( 'Pushing and replacing state: ' );
            //console.log( stateObject );
            window.history.replaceState(stateObject, title, url);
        },

        /**
         * Update URL with provided data via replacing state in window history
         *
         * @param    {Object}    data    Object with lookFor, bool, etc.
         * @return    {undefined}
         */
        replaceUrl: function (data) {
            var stateObject = data;
            var title = 'New search query';
            var url = '/Search/Results/?' + jQuery.param(data)
            window.history.replaceState(stateObject, title, url);
            ////console.log( 'Replacing state: ' );
            ////console.log( stateObject );
        },

        /**
         * Clear form in advanced search template
         *
         * @return    {undefined}
         */
        clearAdvancedSearchTemplate: function () {

			/*
			 * Remove redundand elements
			 */
            var d1 = $.Deferred();
            var d2 = $.Deferred();

            $.when(
                d1
            ).then(function (x) {
                ADVSEARCH.updateGroupsDOMState('#editable-advanced-search-form');
            });

            $.when(
                d2
            ).then(function (x) {
                ADVSEARCH.updateQueriesDOMState('#group_0');
            });

            d1.resolve(
                $('#editable-advanced-search-form .group').each(function (index, element) {
                    if ($(element).attr('id') != 'group_0') {
                        $(element).hide('blind', {}, 200, function () {
                            $(element).remove();
                        });
                    }
                })
            );

            d2.resolve(
                $('#group_0 .queries').each(function (index, element) {
                    if ($(element).attr('id') != 'query_0') {
                        $(element).hide('blind', {}, 200, function () {
                            $(element).remove();
                        });
                    }
                })
            );

			/* Previous callback does not work, so lets try to update it again after 300ms :/ */
            setTimeout(function () {
                ADVSEARCH.updateGroupsDOMState('#editable-advanced-search-form');
            }, 300);

			/* 
			 * Empty first query and set default values to selects
			 */
            $('#query_0 .query-string').val('');

            $('#group_0 select.group-operator').selectedIndex = 0;
            $('#group_0 select.query-type').selectedIndex = 0;

        },

        /**
         * Update lookfor inputs in both search type templates to be the same when switching templates
         *
         * @param    {Object}    data    Object with lookFor, bool, etc.
         * @return    {undefined}
         */
        updateSearchTypeTemplates: function (data) {

            ////console.log( 'Data: ' );
            ////console.log( data );

            if ((data.hasOwnProperty('lookfor1') ) || ( data.lookfor0.length > 1 )) {
				/* Search was made in advanced search */

				/* Fill autocomplete search form */
                ////console.log( 'Filling autocomplete with' );
                ////console.log( data.lookfor0[0] );
                $('#searchForm_lookfor').val(data.lookfor0[0]);
            } else {
				/* Search was made in autocomplete */

				/* Fill adv. search form */
                ADVSEARCH.clearAdvancedSearchTemplate();
                ////console.log( 'Clearing advanced search form' );

                ////console.log( 'Filling advanced search form with ' );
                ////console.log( data.lookfor0[0] );

                $('#query_0 .query-string').val(data.lookfor0[0]);

                ////console.log( 'Filling autocomplete with' );
                ////console.log( data.lookfor0[0] );
                $('#searchForm_lookfor').val(data.lookfor0[0]);
            }

            $( '.query-type' ).each( function( index, element ) {
                $( element ).empty();
                $.each(ADVSEARCH_CONFIG.data[data['database']], function(key, value) {
                    if (key == data.type0[index]) {
                        $( element ).append( $( "<option></option>" ).attr({ "value":key,"selected":"selected" }).text( value ));
                    } else {
                        $( element ).append( $( "<option></option>" ).attr( "value", key ).text( value ));
                    }
                });
            });

        },

    }
	
	/**
	 * Load correct content on history back or forward
	 */
	$( window ).bind( 'popstate', function() {
		var currentState = history.state;
		////console.log( 'POPing state: ' );
		////console.log( currentState );
		if (null != currentState) {
			if (currentState.searchTypeTemplate) {
				ADVSEARCH.updateSearchResults( currentState, undefined, currentState.searchTypeTemplate );
			} else {
				var currentUrl = window.location.href;
				var searchTypeTemplate = getParameterByName( 'searchTypeTemplate', currentUrl );
				ADVSEARCH.updateSearchResults( currentState, undefined, searchTypeTemplate );
			}
		} else {
			console.warn( 'Current history state is NULL.' );
			//window.history.back();
		}
	});
	
	/* Update form's DOM on page load 
	 * 
	 * This function updates numbers in form's DOM to ensure, that jQuery
	 * will correctly handle showing or hiding some elements in form.
	 */
	ADVSEARCH.updateGroupsDOMState( '#editable-advanced-search-form' );
	$( '#editable-advanced-search-form .group' ).each( function(){
		ADVSEARCH.updateQueriesDOMState( '#' + $( this ).attr( 'id' ) );
	});
	
	/*
	 * Add search group on click in advanced search template
	 */
	$( '#editable-advanced-search-form' ).on( 'click', '.add-search-group', function( event ) {
		event.preventDefault();
		var parentDiv = $( this ).parent().parent();
		var last = parentDiv.find( '.group' ).last();
		var clone = last.clone();
		var nextGroupNumber = parseInt( clone.attr( 'id' ).match( /\d+/ ) ) + 1;
		clone.attr( 'id', 'group_' + nextGroupNumber);
		clone.find( 'select' ).prop( 'selected', false );
		clone.find( 'select.group-operator' ).attr( 'name', 'bool' + nextGroupNumber + '[]' );
		clone.find( 'select.query-type' ).attr( 'name', 'type' + nextGroupNumber + '[]' );
		clone.find( '.queries:not(:first)').remove();
		clone.find( 'input:text' ).val( '' );
		clone.find( 'input:text' ).attr( 'name', 'lookfor' + nextGroupNumber + '[]' );
		clone.find( '.remove-advanced-search-query' ).addClass( 'hidden' );
		clone.css( 'display', 'none' )
		last.after( clone );
		clone.show( 'blind', {}, 400, function() {
			ADVSEARCH.updateGroupsDOMState( '#editable-advanced-search-form' );
		})
	});
	
	/*
	 * Add search query on click in advanced search template
	 */
	$( '#editable-advanced-search-form' ).on( 'click', '.add-search-query', function( event ) {
		event.preventDefault();
		var parentDiv = $( this ).parent().parent();
		var last = parentDiv.find( '.queries' ).last();
		var clone = last.clone();
		var nextQueryNumber = parseInt( clone.attr( 'id' ).match( /\d+/ ) ) + 1;
		var thisGroupElement = parentDiv.parent();
		var thisGroupNumber = parseInt( thisGroupElement.attr( 'id' ).match( /\d+/ ) );
		clone.attr( 'id', 'query_' + nextQueryNumber);
		clone.find( 'select' ).prop( 'selected', false );
		clone.find( 'select' ).attr( 'name', 'type' + thisGroupNumber + '[]' );
		clone.find( 'input:text' ).val( '' );
		clone.find( 'input:text' ).attr( 'name', 'lookfor' + thisGroupNumber + '[]' );
		clone.css( 'display', 'none' );
		last.after( clone );
		clone.show( 'blind', {}, 400, function() {
			var thisGroupSelector = $( this ).parent().parent().parent().parent();
			ADVSEARCH.updateQueriesDOMState( '#' + thisGroupElement.attr( 'id' ) );
			thisGroupElement.find( '.remove-advanced-search-query' ).removeClass( 'hidden' );
		});
	});
	
	/*
	 * Remove search group on click in advanced search template
	 */
	$( '#editable-advanced-search-form' ).on( 'click', '.remove-advanced-search-group', function( event ) {
		event.preventDefault();
		$( this ).parent().parent().hide( 'blind', {}, 400, function() {
			$( this ).remove();
			ADVSEARCH.updateGroupsDOMState( '#editable-advanced-search-form' );
		});
	});
	
	/*
	 * Remove search query on click in advanced search template
	 */
	$( '#editable-advanced-search-form' ).on( 'click', '.remove-advanced-search-query', function( event ) {
		event.preventDefault();
		var thisElement = $( this );
		var queryRow = thisElement.parent().parent();
		var thisGroupSelector = queryRow.parent().parent();
		queryRow.hide( 'blind', {},  400, function() {
			queryRow.remove();
			ADVSEARCH.updateQueriesDOMState( '#' + thisGroupSelector.attr( 'id' ) );
		});
	});
	
	/*
	 * Clear advanced search template
	 */
	$( '#editable-advanced-search-form' ).on( 'click', '#clearAdvancedSearchLink', function( event ) {
		event.preventDefault();
		ADVSEARCH.clearAdvancedSearchTemplate();
		
		// Clear also autocomplete
		$( '#searchForm_lookfor' ).val( '' );
	});

	/*
	 * Add or remove clicked facet
	 */
	$( 'body' ).on( 'click', '.facet-filter-or', function( event ) {
		event.preventDefault();
		
		if ( event.ctrlKey ){
		     window.open( $( this ).attr( 'href' ), '_blank' );
		     $( this ).removeClass( 'jstree-clicked active' );
		     return false;
		}

		$( "input[name='page']" ).val( '1' );

		if ($('#facet_cpk_detected_format_facet_str_mv').hasClass( "jstree-proton" ) ) { //only when facet initialized
			//remove all statuses
			var allStatuses = $('#facet_cpk_detected_format_facet_str_mv').jstree(true).get_json('#', {flat: true});
			$.each(allStatuses, function (index, value) {
				ADVSEARCH.removeFacetFilter(value['id'], false);
			});

			//add selected statuses
			var selectedStatuses = $('#facet_cpk_detected_format_facet_str_mv').jstree(true).get_bottom_selected();
			$.each(selectedStatuses, function (index, value) {
				ADVSEARCH.addFacetFilter(value, false);
			});
		};

		if ($('#facet_local_statuses_facet_str_mv').hasClass( "jstree-proton" ) ) { //only when facet initialized
			//remove all statuses
			var allStatuses = $('#facet_local_statuses_facet_str_mv').jstree(true).get_json('#', {flat: true});
			$.each(allStatuses, function (index, value) {
				ADVSEARCH.removeFacetFilter(value['id'], false);
			});

			//add selected statuses
			var selectedStatuses = $('#facet_local_statuses_facet_str_mv').jstree(true).get_bottom_selected();
			$.each(selectedStatuses, function (index, value) {
				ADVSEARCH.addFacetFilter(value, false);
			});
		};

		if ($('#facet_conspectus_str_mv').hasClass( "jstree-proton" ) ) { //only when facet initialized
			//remove all conspectus
			var allConspectus = $('#facet_conspectus_str_mv').jstree(true).get_json('#', {flat: true});
			$.each(allConspectus, function (index, value) {
				ADVSEARCH.removeFacetFilter(value['id'], false);
			});

			//add selected conspectus
			var selectedConspectus = $('#facet_conspectus_str_mv').jstree(true).get_bottom_selected();
			$.each(selectedConspectus, function (index, value) {
				ADVSEARCH.addFacetFilter(value, false);
			});
		}

		ADVSEARCH.updateSearchResults( undefined, undefined );

	});

	/*
	 * Add or remove clicked facet
	 */
	$( 'body' ).on( 'click', '.facet-filter', function( event ) {
		event.preventDefault();
		
		if ( event.ctrlKey ){
		     window.open( $( this ).attr( 'href' ), '_blank' );
		     return false;
		}
		
		$( "input[name='page']" ).val( '1' );
		
		if ( $( this ).hasClass( 'active' ) ) {
			//console.log( 'Removing facet filter.' );
			ADVSEARCH.removeFacetFilter( $( this ).attr( 'data-facet' ), true );
		} else {
			//console.log( 'Adding facet filter.' );
			ADVSEARCH.addFacetFilter( $( this ).attr( 'data-facet' ), true );
		}
	});

    /*
     * Add or remove clicked facet
     */
    $( 'body' ).on( 'click', '.checkbox input', function( event ) {
        event.preventDefault();

        $( "input[name='page']" ).val( '1' );

        if ( $( this ).is(':checked' )) {
            ADVSEARCH.addFacetFilter( $( this ).attr( 'value' ), true );
        } else {
            ADVSEARCH.removeFacetFilter( $( this ).attr( 'value' ), true );
        }
    });

	/*
	 * Remove all institutions facets and add checked ones
	 */
	$( 'body' ).on( 'click', '.institution-facet-filter-button', function( event ) {
		event.preventDefault();
		
		$( "input[name='page']" ).val( '1' );

		//remove all institutions
		var allInstitutions = $('#facet_institution').jstree(true).get_json('#', {flat:true});
		$.each( allInstitutions, function( index, value ){
		  ADVSEARCH.removeFacetFilter( value['id'], false );
		});

		//add selected institutions
		var selectedInstitutions = $('#facet_institution').jstree(true).get_bottom_selected();
		$.each( selectedInstitutions, function( index, value ){
		  ADVSEARCH.addFacetFilter( value, false );
		});
		ADVSEARCH.updateSearchResults( undefined, undefined );
	});
	
	/*
	 * Add or remove clicked facet
	 */
	$( 'body' ).on( 'click', '#remove-all-filters-async', function( event ) {
		event.preventDefault();
		
		ADVSEARCH.removeAllFilters( true );
	});
	
	/*
	 * Update search results on paginating
	 */
	$( 'body' ).on( 'click', '.ajax-update-page', function( event ) {
		event.preventDefault();
		var page = $( this ).attr( 'href' );
		$( "input[name='page']" ).val( page );
		ADVSEARCH.updateSearchResults( undefined, undefined );
	});
	
	/*
	 * Update search results on changing sorting
	 */
	$( 'body' ).on( 'click', '.apply-sort', function( event ) {
		event.preventDefault();
		var sort = $( this ).attr( 'data-sort' );
		var text = $( this ).text();
		$( '.ajax-update-sort' ).find( '.value' ).text( text );
		$( "input[name='sort']" ).val( sort );
		$( "input[name='page']" ).val( '1' );
		ADVSEARCH.updateSearchResults( undefined, undefined );
	});
	
	/*
	 * Update search results on changing limit
	 */
	$( 'body' ).on( 'click', '.apply-limit', function( event ) {
		event.preventDefault();
		var limit = $( this ).attr( 'data-limit' );
		var text = $( this ).text();
		$( '.ajax-update-limit' ).find( '.value' ).text( limit );
		
		var oldLimit = $( "input[name='limit']" ).val();
		var oldPage = $( "input[name='page']" ).val();
		
		var newPage = Math.floor(((oldPage - 1) * oldLimit) / limit) + 1
		$( "input[name='page']" ).val( newPage );
		$( "input[name='limit']" ).val( limit );
		
		ADVSEARCH.updateSearchResults( undefined, undefined );
	});
	
	/*
	 * Add data range as facet
	 */
	$( 'body' ).on( 'click', '.apply-facet-filter-range', function( event ) {
		event.preventDefault();
		
		var extraData = {};
		extraData['publishDatefrom'] = $( '#publishDatefrom' ).val();
		if ($( '#publishDatefrom' ).val() == undefined){
			extraData['publishDatefrom'] = $( '#PublicationDatefrom' ).val();
		}
		extraData['publishDateto'] = $( '#publishDateto' ).val();
		if ($( '#publishDateto' ).val() == undefined){
			extraData['publishDateto'] = $( '#PublicationDateto' ).val();
		}
		extraData['daterange'] = 'publishDate';
		
		var value = 'publishDate:"['+extraData['publishDatefrom']+' TO '+extraData['publishDateto']+']"';
		ADVSEARCH.addFacetFilter(value, false);
		if ($( "input[name='database']" ).val() == 'EDS'){	
			window.location.href = '/EDS/Search?lookfor=&type=AllFields&searchTypeTemplate=basic&page=1&database=EDS&limit=&sort=&PublicationDatefrom='+extraData['publishDatefrom']+'&PublicationDateto='+extraData['publishDateto']+'&daterange=PublicationDate';
		}else{	
			ADVSEARCH.updateSearchResults( undefined, undefined, undefined, extraData );
		}
	});
	
	/*
	 * Update search results on submiting advanced search form
	 */
	$( '#editable-advanced-search-form' ).on( 'click', '#submit-edited-advanced-search', function( event ) {
		event.preventDefault();

		//add chosen filters
		$(".chosen-select").each(function() {
			var selectedFilters = $( this ).val();
			if(selectedFilters!=null) {
				$.each(selectedFilters, function (index, value) {
					ADVSEARCH.addFacetFilter(value, false);
				});
			}
		});

		$( "input[name='page']" ).val( '1' );
		$( "input[name='searchTypeTemplate']" ).val( 'advanced' );
		ADVSEARCH.updateSearchResults( undefined, undefined );
	});
	
	/*
	 * Save or remove search
	 */
	$( 'body' ).on( 'click', '.save-search-link', function( event ) {
		event.preventDefault();
		
		var action = $( this ).attr( 'id' );
		
		if (action == 'add-to-saved-searches') {
			//console.log( 'Saving search.' );
			
			var thisElement = this;
			
			var data = {};
			data['searchId'] = $( this ).attr( 'data-search-id' );
			
			$.ajax({
	        	type: 'POST',
	        	cache: false,
	        	dataType: 'json',
	        	url: VuFind.getPath() + '/AJAX/JSON?method=saveSearch',
	        	data: data,
	        	success: function( response ) {
	        		
	        		scrollToTop();
	        		
	        		if (response.status == 'OK') {
	        			//console.log('Search saved.');
	        			var html = '<div class="alert alert-success"><a href="#" class="close closeFlashedMessage">×</a>'+VuFind.translate('search_save_success')+'</div>';
	        			$( '#flashedMessage div' ).html( html );
	        			$( '#flashedMessage' ).show( 'blind', {}, 500);
	        			$( thisElement ).attr( 'title', VuFind.translate('Delete saved search'));
	        			$( thisElement ).text( VuFind.translate('Delete saved search'));
	        			$( thisElement ).attr( 'id', 'remove-from-saved-searches');
	        		} else {
	        			console.error(response.data);
	        			var message = '';
	        			if (response.data.indexOf( 'authentication_error_loggedout' ) >= 0) {
	        				message = VuFind.translate( 'login_to_use_this' );
	        			} else {
	        				message = VuFind.translate( 'reload_or_save_again' );
	        			}
	        			var html = '<div class="alert alert-warning"><a href="#" class="close closeFlashedMessage">×</a>'+message+'</div>';
	        			$( '#flashedMessage div' ).html( html );
	        			$( '#flashedMessage' ).show( 'blind', {}, 500);
	        		}
	        	}
			});
		}
		
		if (action == 'remove-from-saved-searches') {
			//console.log( 'Removing search.' );
			
			var thisElement = this;
			
			var data = {};
			data['searchId'] = $( this ).attr( 'data-search-id' );
			
			$.ajax({
	        	type: 'POST',
	        	cache: false,
	        	dataType: 'json',
	        	url: VuFind.getPath() + '/AJAX/JSON?method=removeSearch',
	        	data: data,
	        	success: function( response ) {
	        		
	        		scrollToTop();
	        		
	        		if (response.status == 'OK') {
	        			//console.log('Search removed.');
	        			var html = '<div class="alert alert-success"><a href="#" class="close closeFlashedMessage">×</a>'+VuFind.translate('search_unsave_success')+'</div>';
	        			$( '#flashedMessage div' ).html( html );
	        			$( '#flashedMessage' ).show( 'blind', {}, 500);
	        			$( thisElement ).attr( 'title', VuFind.translate('Save search'));
	        			$( thisElement ).text( VuFind.translate('Save search'));
	        			$( thisElement ).attr( 'id', 'add-to-saved-searches');
	        		} else {
	        			console.error(response.data);
	        			var message = '';
	        			if (response.data.indexOf( 'authentication_error_loggedout' ) >= 0) {
	        				message = VuFind.translate( 'login_to_use_this' );
	        			} else {
	        				message = VuFind.translate( 'reload_or_save_again' );
	        			}
	        			var html = '<div class="alert alert-warning"><a href="#" class="close closeFlashedMessage">×</a>'+message+'</div>';
	        			$( '#flashedMessage div' ).html( html );
	        			$( '#flashedMessage' ).show( 'blind', {}, 500);
	        		}
	        	}
			});
		}
		
	});
	
	/*
	 * Save search
	 */
	$( 'body' ).on( 'click', '.closeFlashedMessage', function( event ) {
		event.preventDefault();
		$( this ).parent().hide( 'blind', {}, 200);
	});

	/*
	 * Switch search type template to basic search (autocomplete) or advanced search
	 */
	$( 'body' ).on( 'click', '.search-type-template-switch', function( event ) {
		event.preventDefault();
		
		var currentUrl = window.location.href;
		var searchTypeTemplate = getParameterByName( 'searchTypeTemplate', currentUrl );
		
		var newSearchTypeTemplate = 'basic';
		
		if (searchTypeTemplate == 'basic') {
			newSearchTypeTemplate = 'advanced';
		}
		
		$( 'input[name=searchTypeTemplate]' ).val(newSearchTypeTemplate);
		
		ADVSEARCH.updateSearchResults( undefined, undefined, newSearchTypeTemplate );
	});
	
	$( 'body' ).on( 'mouseover', '.result', function( event ) {
	
		$( this ).find( '.search-results-favorite-button' ).removeClass( 'hidden' );
	});
	
	$( 'body' ).on( 'mouseleave', '.result', function( event ) {
		$( this ).find( '.search-results-favorite-button' ).addClass( 'hidden' );
	});

	/*
	* Load search results from selected database
	* */
    $( 'body' ).on( 'click', '#set-database li a', function( event ) {
        event.preventDefault();

        if ($( this ).parent().hasClass( 'active' )) {
        	return false;
		}

        var extraData = {};
        var database = $( this ).attr( 'data-value' )
        extraData['database'] = database;
/*
 @TODO: Something like this should be called before calling searchResultsAjax action.
 When we have limit set in e.g. 80 in Solr, we should check, whether it is allowed value in EDS.ini config.
 The same from the other site, from EDS to Solr.
 This ajax prepreparation needs to know all the correct values, so they have to be stored somewhere,
 to be able to foreach them.
        if (database == 'EDS') {
            extraData['limit'] = $( "input[name='edsDefaultLimit']" ).val();
        } else if (database == 'Solr') {
            extraData['limit'] = $( "input[name='solrDefaultLimit']" ).val();
        }
*/
	var previousSort = $( "input[name='sort']" ).val();
	var text_sort = null;
	if (database == 'EDS') {
		if (previousSort == 'publishDateSort desc'){
		    extraData['sort'] = 'date';
            text_sort = VuFind.translate('date_newest');
		} else if (previousSort == 'publishDateSort asc'){
		    extraData['sort'] = 'date2';
            text_sort = VuFind.translate('date_oldest');
		} else{
			extraData['sort'] = 'relevance';
			text_sort = VuFind.translate('sort_relevance');
		}
	} else if (database == 'Solr') {
        if (previousSort == 'date') {
            extraData['sort'] = 'publishDateSort desc';
            text_sort = VuFind.translate('date_newest');
        } else if (previousSort == 'date2') {
            extraData['sort'] = 'publishDateSort asc';
            text_sort = VuFind.translate('date_oldest');
        } else {
            extraData['sort'] = 'relevance';
            text_sort = VuFind.translate('sort_relevance');
        }
	}
	$( '.ajax-update-sort' ).find( '.value' ).text( text_sort );
        
	$( "input[name='sort']" ).val( extraData['sort']);
	$( 'input[name=database]' ).val(database);

        ADVSEARCH.removeAllFilters();

        $( this ).parent().parent().find( 'li' ).removeClass( 'active' );
        $( this ).parent().addClass( 'active' );

        if ( $( '.basic-search-panel' ).hasClass( 'hidden' ) ) {
            callbacks = {};
            callbacks.afterSwitchSearchTemplate = function() {
                ADVSEARCH.updateSearchResults( undefined, undefined, false, extraData);
            };
            extraData['searchTypeTemplate'] = 'advanced';
            ADVSEARCH.updateSearchResults( undefined, undefined, 'advanced', extraData, callbacks);
        } else {
            ADVSEARCH.updateSearchResults( undefined, undefined, false, extraData);
        }
    });

	/**
	 * Get param from url
	 * 
	 * This function doesn't handle parameters that aren't followed by equals sign
	 * This function also doesn't handle multi-valued keys
	 * 
	 * @param	{string}	name	Param name
	 * @param	{string}	url		Url
	 * 
	 * @return	{string}
	 */
	var getParameterByName = function( name, url ) {
	    var url = url.toLowerCase(); // avoid case sensitiveness  
	    var name = name.replace( /[\[\]]/g, "\\$&" ).toLowerCase(); // avoid case sensitiveness
	    
	    var regex = new RegExp( "[?&]" + name + "(=([^&#]*)|&|#|$)" ),
	        results = regex.exec( url );
	    
	    if ( ! results ) return null;
	    if ( ! results[2] ) return '';
	    
	    return decodeURIComponent( results[2].replace( /\+/g, " " ) );
	};
	
	/**
	 * Smooth scroll to the top of the element
	 * 
	 * @param	{string}	elementId
	 * @return	{undefined}
	 */
	var smoothScrollToElement = function( elementId ) {
		$( 'body' ).animate( {
	        scrollTop: $( elementId ).offset().top
	    }, 1000);
	};
	
	/**
	 * Scroll to the top of the document
	 * 
	 * @return	{undefined}
	 */
	var scrollToTop = function( elementId ) {
		$( 'html, body' ).animate( { scrollTop: 0 }, 'slow' );
	};
	
	
	/**
  	 * Returns JSON from query string
  	 * Function supports multi-valued keys
  	 * 
  	 * @param	{string}	queryString	?param=value&param2=value2
  	 * @return	{JSON}
  	 */
  	var queryStringToJson = function ( queryString ) {            
  	    var pairs = queryString.slice( 1 ).split( '&' );
  	    
  	    var results = {};
  	    pairs.forEach( function( pair ) {
  	        var pair = pair.split('=');
  	        var key = pair[0];
  	        var value = decodeURIComponent(pair[1] || '');
  	        
  	        if (! results.hasOwnProperty( key )) {
  	        	results[key] = [];
  			}
  	        results[key].push( value );
  	    });

  	    return JSON.parse( JSON.stringify( results ) );
  	};
  	
  	var replaceAll = function ( str, find, replace ) {
  	  return str.replace( new RegExp( (find+'').replace(/[.?*+^$[\]\\(){}|-]/g, "\\$&") , 'g' ), replace );
  	};
  	
  	/**
  	 * This functions is used like standard php's urlencode,
  	 * but insted of double encode, this creates url friedly string for
  	 * base64 encoding/decoding.
  	 *
  	 * @param   {string } input
  	 *
  	 * @return  {string}
  	 */
  	var specialUrlEncode = function( input ) {
  		if ( typeof input[0] == 'undefined' || input[0] == null || !input ) {
  			return '';
  		}
  		var output = replaceAll( input, '+', '-' );
  		output = replaceAll( output, '/', '_' );
  		output = replaceAll( output, '=', '.' );
  		return output;
  	};
  	
  	/**
  	 * This functions is used like standard php's urldecode,
  	 * but insted of double decode, this creates url friedly string for
  	 * base64 encoding/decoding.
  	 *
  	 * @param   {string } input
  	 *
  	 * @return  {string}
  	 */
  	var specialUrlDecode = function( input ) {
  		if ( typeof input[0] == 'undefined' || input[0] == null || !input ) {
  			return '';
  		}
  		var output = replaceAll( input, '-', '+' );
  		output = replaceAll( output, '_', '/' );
  		output = replaceAll( output, '.', '=' );
  		return output;
  	};
  	
  	/**
  	 * Convert html entities to chars 
  	 * 
  	 * @param	{string}	html
  	 * @return	{string}
  	 */
  	var decodeHtml = function( html ) {
  	    var txt = document.createElement( 'textarea' );
  	    txt.innerHTML = html;
  	    return txt.value;
  	}
  	
  	/* Search email form client-side validation */
	$( '#email-search-results' ).validate({ // initialize the plugin
        rules: {
            from: {
                required: true,
                email: true
            },
            to: {
                required: true,
                email: true
            }
        },
        messages: {
        	from: {
              required: VuFind.translate( 'Enter email' ),
              email: VuFind.translate( 'Wrong email format' )
            },
            to: {
              required: VuFind.translate( 'Enter email' ),
              email: VuFind.translate( 'Wrong email format' )
            }
          }
    });
});

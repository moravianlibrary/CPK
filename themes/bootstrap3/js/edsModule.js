/**
 * Module for actions with EDS
 *
 * @type {{renameWrongValues: EDS_MODULE.renameWrongValues, updateEdsFacets: EDS_MODULE.updateEdsFacets}}
 */
EDS_MODULE = {

	/**
	 * True source types which combines with ContentProviders if facets in EDS module
	 */
	trueSourceTypes: [
		['SourceType:"Conference Materials"', 'SourceType:"Conference Papers"'],
		['SourceType:"Dissertations"', 'SourceType:"Dissertations/Theses"'],
		['SourceType:"Reviews"', 'SourceType:"Book Reviews"'],
		['SourceType:"News"', 'SourceType:"Newspapers"'],
		['SourceType:"Primary Source Documents"', 'SourceType:"Government Documents"']
	],

	/**
	 * Renames wrong SourceType values to true values
	 *
	 * @param filter
	 * @param mappedWrongValuesToTrue
	 * @param reverseRemoving
	 */
	renameWrongValues: function (filter, mappedWrongValuesToTrue, reverseRemoving) {
		//go through all source types
		for (var i = 0; i < filter.length; i++) {
			//find current source type in mapped array
			for (var j = 0; j < mappedWrongValuesToTrue.length; j++) {
				//if found current source type in wrong types
				if (filter[i] === mappedWrongValuesToTrue[j][0] && !reverseRemoving) {
					//write fixed source types
					filter[i] = mappedWrongValuesToTrue[j][1];
					//fix values in hidden inputs
					$("#hiddenFacetFilters").find("input").each(function () {
						if ($(this).val() === mappedWrongValuesToTrue[j][0]) {
							$(this).val(mappedWrongValuesToTrue[j][1]);
						}
					});
					break;
				} else if (filter[i] === mappedWrongValuesToTrue[j][1] && reverseRemoving) {
					filter[i] = mappedWrongValuesToTrue[j][0];
					$("#hiddenFacetFilters").find("input").each(function () {
						if ($(this).val() === mappedWrongValuesToTrue[j][1]) {
							$(this).val(mappedWrongValuesToTrue[j][0])
						}
					});
					break;
				}
			}
		}
		return filter;
	},

	/**
	 * Fix source type for bad data from EDS API
	 *
	 * @param filter
	 * @param databaseType
	 * @param selectedValue
	 * @returns {*}
	 */
	updateEdsFacets: function (filter, databaseType, selectedValue) {
		//Don`t think a lot about this part of code. Mission of this part of code is fix bug in EDS
		//It translates bad text in data-facet to true values
		try {
			var operator = (selectedValue.isAdd ) ? false : ( selectedValue.isRemove ? true : null);

			if (operator !== null) {
				//If we`ve added source type and no Content Providers added, just add it
				if(~selectedValue.value.indexOf('SourceType') && !operator) {
					return filter;
				}

				//if we have added or removed source type or provider
				//if we`ve removed, operator is true and we are doing reverse removing
				filter = EDS_MODULE.renameWrongValues(filter, this.trueSourceTypes, operator);
			}
		} catch (e) {
			return filter;
		}

		return filter;
	}
};
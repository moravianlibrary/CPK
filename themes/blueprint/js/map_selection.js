function loadMapSelection(facetField, boundingBox, baseURL, searchParams, showSelection) {
    var map = $("#geo_map").geomap({
        bbox: boundingBox,
        mode: "dragBox",
        shift: "dragBox",
        shape: function(e, geo) {
            map.geomap("empty");
            if (geo.type == 'Polygon') {
                map.geomap("append", geo);
                box = geo.bbox;
                rawFilter = encodeURIComponent('bbox_geo:"Intersects(' + box.join(' ') + ')"');
                location.href = baseURL + searchParams + "&" + 'filter[]=' + rawFilter;
            }
        }
    });
    $("#geo_modes [name=mode]").click(function() {
        map.geomap("option", "mode", $(this).val());
    });
    if (showSelection) {
        map.geomap("append", createGeoJSONFromBoundingBox(boundingBox));
    }
}

function createGeoJSONFromBoundingBox(bbox) {
    return {
        type: "Polygon",
        coordinates: [ [ 
            [ bbox[0], bbox[1] ],
            [ bbox[2], bbox[1] ],
            [ bbox[2], bbox[3] ],
            [ bbox[0], bbox[3] ],
            [ bbox[0], bbox[1] ],
        ] ],
    };
}

function loadMapSelection(facetField, boundingBox, baseURL, searchParams, showSelection) {
    $("#geo_map").show();
    var map = $("#geo_map").geomap({
        bbox: boundingBox,
        mode: "dragBox",
        shift: "dragBox",
        shape: function(e, geo) {
            map.geomap("empty");
            if (geo.type == 'Polygon') {
                map.geomap("append", geo);
                box = geo.bbox;
                box[0] = Math.max(-180, box[0]);
                box[1] = Math.max(-85, box[1]);
                box[2] = Math.min(180, box[2]);
                box[3] = Math.min(85, box[3]);
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

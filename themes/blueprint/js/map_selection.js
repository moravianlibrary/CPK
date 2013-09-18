function loadMapSelection(field, boundingBox) {
    var map = $("#geo_map").geomap({
        bbox: boundingBox,
        mode: "dragBox",
        shift: "dragBox",
        shape: function(e, geo) {
            map.geomap("empty");
            map.geomap("append", geo);
            if (geo.type == 'Polygon') {
                box = geo.bbox;
                $("#geo_modes [name='coordinates']").val(box[0].toFixed(5) + ' ' + box[1].toFixed(5) + ' ' + box[2].toFixed(5) + ' ' + box[3].toFixed(5));
            }
        }
    });

    $("#geo_modes [name=mode]").click(function() {
        map.geomap("option", "mode", $(this).val());
    });
    $("#geo_form").submit(function(event) {
        coordinates = $("#geo_modes [name='coordinates']").val();
        $("#geo_map_filter").val('bbox_geo:"Intersects(' + coordinates + ')"');
        $("#geo_form").submit();
        return false;
    });
    map.geomap("append", createGeoJSONFromBoundingBox(boundingBox));
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

function loadMapSelection(
        geoField, boundingBox, baseURL, searchParams, showSelection) {
    var init = true;
    var srcProj = new OpenLayers.Projection('EPSG:4326');
    var dstProj = new OpenLayers.Projection('EPSG:900913')
    $('#geo_search').show();
    var vectorLayer = new OpenLayers.Layer.Vector(
        'vector-layer',
        {
            eventListeners: {
                sketchcomplete: function(feature) {
                    this.removeAllFeatures();
                    return true;
                },
                featureadded: function(e) {
                    if (init) {
                        return;
                    }    
                    var box = e.feature.geometry.getBounds().transform(
                            dstProj, srcProj).toArray();
                    box[0] = Math.max(-180, box[0]);
                    box[1] = Math.max(-85, box[1]);
                    box[2] = Math.min(180, box[2]);
                    box[3] = Math.min(85, box[3]);
                    
                    var rawFilter = encodeURIComponent(
                            geoField + ':"Intersects(' + box.join(' ') + ')"');
                    location.href = baseURL + searchParams
                                    + "&filter[]=" + rawFilter;
                }
            }
        }
    );
    var map = new OpenLayers.Map({
        div: 'geo_search_map',
        projection: dstProj,
        layers: [
            new OpenLayers.Layer.OSM('MapQuest',[
                'http://otile1.mqcdn.com/tiles/1.0.0/map/${z}/${x}/${y}.png',
                'http://otile2.mqcdn.com/tiles/1.0.0/map/${z}/${x}/${y}.png',
                'http://otile3.mqcdn.com/tiles/1.0.0/map/${z}/${x}/${y}.png',
                'http://otile4.mqcdn.com/tiles/1.0.0/map/${z}/${x}/${y}.png'
            ]),
            vectorLayer
        ],
        center: [0, 0],
        zoom: 1
    });
    var createBBoxControl = new OpenLayers.Control.DrawFeature(
        vectorLayer,
        OpenLayers.Handler.RegularPolygon,
        {
            handlerOptions: {
                sides: 4,
                irregular: true
            }
        }
    );
    map.addControl(createBBoxControl);
    createBBoxControl.activate();
    $("#geo_search_modes [name=mode]").change(function() {
        if ($(this).val() == "dragBox") {
            createBBoxControl.activate();
        } else {
            createBBoxControl.deactivate();
        }
    });
    if (showSelection) {
        vectorLayer.removeAllFeatures();
        var bounds = OpenLayers.Bounds.fromArray(boundingBox).transform(
                srcProj, dstProj);
        var feature = new OpenLayers.Feature.Vector(bounds.toGeometry());
        vectorLayer.addFeatures([feature]);
        map.zoomToExtent(bounds);
    }
    init = false;
}

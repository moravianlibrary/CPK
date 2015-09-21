function loadMapSelection(params) {
    var geoField = params['geoField'];
    var boundingBox = params['boundingBox'];
    var searchParams = params['searchParams'];
    var showSelection = params['showSelection'];
    var useWKT = params['useWKT'];
    var baseURL = path;
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
                    var rawFilter = null;
                    if (useWKT) {
                        var polygon = "POLYGON(({0} {1}, {2} {1}, {2} {3}, {0} {3}, {0} {1}))"
                            .replace(/\{0\}/g, box[0])
                            .replace(/\{1\}/g, box[1])
                            .replace(/\{2\}/g, box[2])
                            .replace(/\{3\}/g, box[3]);
                        rawFilter = encodeURIComponent(
                                geoField + ':"Intersects(' + polygon + ')"');
                    } else {
                        box[0] = Math.max(-180, box[0]);
                        box[1] = Math.max(-85, box[1]);
                        box[2] = Math.min(180, box[2]);
                        box[3] = Math.min(85, box[3]);
                        rawFilter = encodeURIComponent(
                                geoField + ':"Intersects(' + box.join(' ') + ')"');
                    }
                    if (rawFilter != null) {
                        location.href = baseURL + searchParams + "&filter[]=" + rawFilter;
                    }
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

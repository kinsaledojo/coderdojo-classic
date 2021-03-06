<div class="map">
	<div id="map-box" style="height: 350px;"></div>
</div>
<div class="search-block">
	<div id="find-yours-box" class="content-box">
		<div id="find-yours-form">
			<div class="search-form form-inline">
				<fieldset>
					<label for="search">Find a Dojo near you</label>
					<div class="search-row">
						<div class="form-group search-city">
							<input type="search" id="location" class="form-control" placeholder="Enter your city e.g. Dublin" />
						</div>
						<input class="btn btn-default" type="submit" value="Search" />
					</div>
					<div>
					</div>
				</fieldset>
			</div>
		</div>
		<div id="closeness" class="hidden"></div>
	</div>
	<div id="found-yours-box" class="content-box hidden"></div>
</div>

<script type="text/javascript" src="//maps.googleapis.com/maps/api/js?sensor=false"></script>
<script type="text/javascript" src="{{site.theme.link}}/static/js/geolib.js"></script>
<script type="text/javascript" src="{{site.theme.link}}/static/js/markercluster.js"></script>
<script type="text/javascript">
var map;
var markers = [];
var markerClusterer = null;
var geocoder;
var data;
var centerLocation = new google.maps.LatLng(25, 0);
var usersLocation;

function refreshMap() {
  if (markerClusterer) {
    markerClusterer.clearMarkers();
  }

  var currentInfoWindow;

  data.forEach(function (dataPoint) {
    if (dataPoint.geoPoint) {
      var latLng = new google.maps.LatLng(dataPoint.geoPoint.lat,
        dataPoint.geoPoint.lon)
      var marker = new google.maps.Marker({
        dojoId: dataPoint.id,
        name: dataPoint.name,
        url: dataPoint.urlSlug,
        private: dataPoint.private,
        position: latLng,
        map: map,
        title: dataPoint.name,
        clickable: true,
        icon: {
          url: "https://chart.googleapis.com/chart?chst=d_map_pin_letter&chld=%E2%80%A2|" + (dataPoint.private ? '9b1c20' : '49b749')
        }
      });
      var infowindow = new google.maps.InfoWindow({
        content: "<h3><a href='https://zen.coderdojo.com/dojo/" + dataPoint.urlSlug + "'>" + dataPoint.name + "</a></h3>",
        position: latLng
      });
      marker.addListener('click', function() {
        if (currentInfoWindow) {
          currentInfoWindow.close();
        }
        currentInfoWindow = infowindow;
        infowindow.open(map, marker);
      });
      markers.push(marker);
    }
  });

  markerClusterer = new MarkerClusterer(map, markers, {
		imagePath: '{{site.theme.link}}/static/img/maps/m'
  });
  handleGeo();
}

function initialize() {
  geocoder = new google.maps.Geocoder();
  var mapOptions = {
    center: centerLocation,
    zoom: 2,
    mapTypeId: google.maps.MapTypeId.ROADMAP,
    scrollwheel: false
  };
  map = new google.maps.Map(document.getElementById("map-box"), mapOptions);
  DojoList();
}

function handleGeo() {
	// Try W3C Geolocation (Preferred)
  if(navigator.geolocation) {
    browserSupportFlag = true;
    navigator.geolocation.getCurrentPosition(function(position) {
      usersLocation = new google.maps.LatLng(position.coords.latitude,position.coords.longitude);
			map.setCenter(usersLocation);
      setZoom();
    }, function() {
      handleNoGeolocation(browserSupportFlag);
    });
  }

	// Browser doesn't support Geolocation
  else {
    browserSupportFlag = false;
    handleNoGeolocation(browserSupportFlag);
  }

  function handleNoGeolocation(errorFlag) {
    map.setCenter(centerLocation);
  }

}

function setZoom() {
  //if (_.isEmpty($scope.search.dojo)) {
    var zoom = 14, set = false;
    map.setZoom(zoom);
    map.setCenter(usersLocation || centerLocation);
    while (zoom > 2 && !set && usersLocation !== centerLocation) {
      zoom--;
      markers.forEach(function (marker){
        if (map.getBounds().contains(marker.getPosition())){
          set = true
        }
      });
      map.setZoom(zoom);
    }
    if (usersLocation === centerLocation) {
      zoom = 2;
    }
    map.setZoom(zoom);
  //}
}

function DojoList(dojos) {
  jQuery.ajax({
      url: "https://zen.coderdojo.com/api/2.0/dojos",
      method: "POST",
      data: JSON.stringify({
        query: {
          verified: 1,
          deleted: 0,
	  stage: { ne$: 4 },
          fields$: ["name", "geo_point", "stage", "url_slug", "private"]
        }
      }),
      contentType: "application/json",
      dataType: "json"
    })
    .done(function(dojos) {
      data = dojos;
      refreshMap();
    });
}

function searchAddress(address) {
  if (usersLocation) {
    var latlng = {lat: usersLocation.lat(), lng: usersLocation.lng()};
    geocoder.geocode({location: latlng}, function (results, status) {
      if (status === google.maps.GeocoderStatus.OK) {
        if (!results.length) return;
        var country = results[0].address_components[results[0].address_components.length-1].short_name;
        codeAddress(address, country);
      }
    });
  } else {
    codeAddress(address);
  }
}

function codeAddress(address, country) {
  try {
    gtag('event', 'dojo-search', {
      event_category: 'Dojos',
      event_action: 'Search',
      event_label: address
    });
  } catch (e) {}
  geocoder.geocode({
    'address': address,
    'region': country
  }, function(results, status) {
    if (status == google.maps.GeocoderStatus.OK) {
      var dojoGeoPoints = data.map(function (dojo) {
        return dojo.geoPoint;
      }).filter(function (geoPoint) {
        return !!geoPoint;
      });
      if (results[0].geometry.bounds) {
        map.fitBounds(results[0].geometry.bounds);
        var dojos = new Array();
        for (var i in dojoGeoPoints) {
          if (results[0].geometry.bounds.contains(new google.maps.LatLng(dojoGeoPoints[i].lat, dojoGeoPoints[i].lon))) {
            dojos.push(dojoGeoPoints[i]);
          }
        }
        if (dojos.length === 0) {
          var closest = geolib.findNearest({
            latitude: results[0].geometry.location.lat(),
            longitude: results[0].geometry.location.lng()
          }, dojoGeoPoints);
          map.setCenter(new google.maps.LatLng(closest.lat, closest.lon));
        } else {
          for (var dojo in dojos) {
            map.setCenter(new google.maps.LatLng(dojos[dojo].lat, dojos[dojo].lon));
          }
        }
      } else {
        var closest = geolib.findNearest({
          latitude: results[0].geometry.location.lat(),
          longitude: results[0].geometry.location.lng()
        }, dojoGeoPoints);
        map.setCenter(new google.maps.LatLng(closest.lat, closest.lon));
      }
      map.setZoom(15);
    }
  });
}
google.maps.event.addDomListener(window, 'load', initialize);
google.maps.event.addDomListener(window, 'load', function() {
  el = document.getElementById("location");
  if (el.addEventListener) {
    el.addEventListener('change', function() {
      myLocation = this.value;
      searchAddress(myLocation);
    });
  } else if (el.attachEvent) {
    el.attachEvent('onchange', function() {
      myLocation = this.value;
      searchAddress(myLocation);
    });
  }
});
</script>
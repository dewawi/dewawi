<?php

class Zend_View_Helper_ContactsMap extends Zend_View_Helper_Abstract
{
	public function ContactsMap(array $contacts): string
	{
		if (!$contacts) {
			return '';
		}

		$markers = [];

		foreach ($contacts as $contact) {
			if (empty($contact['latitude']) || empty($contact['longitude'])) {
				continue;
			}

			$markers[] = [
				'name' => $contact['name1'],
				'lat' => (float)$contact['latitude'],
				'lng' => (float)$contact['longitude'],
				'url' => $this->view->baseUrl() . '/contacts/contact/edit/id/' . (int)$contact['id'],
				'contactid' => $contact['contactid'],
			];
		}

		if (!$markers) {
			return '';
		}

		$json = Zend_Json::encode($markers);

		return '
			<div class="dw-map-section" id="contactsmap">
				<div id="map"></div>
			</div>

			<script type="text/javascript">
				$(document).ready(function(){
					const startmarkers = ' . $json . ';
					const map = L.map("map").setView([51.2254018, 6.7763137], 5);

					map.attributionControl.setPrefix("Leaflet");

					L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
						maxZoom: 19,
						attribution: "&copy; OpenStreetMap"
					}).addTo(map);

					const bounds = [];

					startmarkers.forEach(function(item) {
						L.marker([item.lat, item.lng])
							.bindPopup(
								"<a href=\"" + item.url + "\">" +
								item.name + "<br>" + item.contactid +
								"</a>"
							)
							.addTo(map);

						bounds.push([item.lat, item.lng]);
					});

					if (bounds.length) {
						map.fitBounds(bounds);
					}
				});
			</script>
		';
	}
}

/*global dotclear */
'use strict';

dotclear.ready(() => {
  const provider = document.getElementById('eventHandlermap_provider');
  if (!provider) {
    return;
  }
  const toggle = () => {
    const osm = provider.value === 'osm';
    for (const elt of document.querySelectorAll('.map-tile-layer')) {
      elt.style.display = osm ? '' : 'none';
    }
    for (const elt of document.querySelectorAll('.map-api-key')) {
      elt.style.display = osm ? 'none' : '';
    }
  };
  provider.addEventListener('change', toggle);
  toggle();
});

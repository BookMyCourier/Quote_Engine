(function(){
  'use strict';

  window.BMCInitGooglePlaces = function(){
    const collection = document.getElementById('bmc_collection');
    const delivery = document.getElementById('bmc_delivery');
    if (!collection || !delivery || !window.google || !google.maps || !google.maps.places) return;
    new google.maps.places.Autocomplete(collection, { componentRestrictions: { country: 'gb' }, fields: ['formatted_address', 'geometry', 'name'] });
    new google.maps.places.Autocomplete(delivery, { componentRestrictions: { country: 'gb' }, fields: ['formatted_address', 'geometry', 'name'] });
  };

  function selectedVehicle(){
    const selected = document.querySelector('input[name="bmc_vehicle"]:checked');
    return selected ? selected.value : 'small';
  }

  function updateStartingPrice(){
    if (!window.BMCQuoteData || !BMCQuoteData.vehicles) return;
    const vehicle = BMCQuoteData.vehicles[selectedVehicle()];
    if (!vehicle) return;
    const price = document.getElementById('bmc_price');
    const note = document.getElementById('bmc_price_note');
    const distance = document.getElementById('bmc_distance');
    if (price) price.textContent = '£' + Number(vehicle.base).toFixed(2);
    if (note) note.textContent = 'Including up to ' + vehicle.included + ' miles';
    if (distance) distance.textContent = '—';
  }

  document.addEventListener('change', function(e){
    if (e.target && e.target.name === 'bmc_vehicle') updateStartingPrice();
  });

  document.addEventListener('click', function(e){
    const button = e.target && e.target.id === 'bmc_get_quote' ? e.target : null;
    if (!button) return;

    const collection = document.getElementById('bmc_collection');
    const delivery = document.getElementById('bmc_delivery');
    const distance = document.getElementById('bmc_distance');
    const price = document.getElementById('bmc_price');
    const note = document.getElementById('bmc_price_note');
    const msg = document.getElementById('bmc_quote_message');

    if (!collection || !delivery) return;
    msg.textContent = '';

    if (!collection.value.trim() || !delivery.value.trim()) {
      msg.textContent = 'Please enter both addresses.';
      return;
    }
    if (!window.BMCQuoteData || !BMCQuoteData.hasApiKey) {
      msg.textContent = 'Google API key has not been added yet.';
      return;
    }

    button.disabled = true;
    button.textContent = 'Calculating...';
    distance.textContent = '—';

    const formData = new FormData();
    formData.append('action', 'bmc_calculate_quote');
    formData.append('nonce', BMCQuoteData.nonce);
    formData.append('collection', collection.value.trim());
    formData.append('delivery', delivery.value.trim());
    formData.append('vehicle', selectedVehicle());

    fetch(BMCQuoteData.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function(res){ return res.json(); })
      .then(function(data){
        if (!data || !data.success) {
          msg.textContent = data && data.data && data.data.message ? data.data.message : 'Could not calculate that route.';
          return;
        }
        distance.textContent = data.data.miles + ' miles';
        price.textContent = '£' + data.data.price;
        note.textContent = 'Includes ' + data.data.included + ' miles, then £' + data.data.rate + ' per extra mile';
      })
      .catch(function(){
        msg.textContent = 'Something went wrong calculating the quote.';
      })
      .finally(function(){
        button.disabled = false;
        button.textContent = 'Get Instant Quote';
      });
  });
})();

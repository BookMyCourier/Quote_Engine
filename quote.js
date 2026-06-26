(function(){
  let quoteTimer = null;
  let isCalculating = false;
  let pendingCalculate = false;
  let latestQuote = null;

  window.bmcqeInitGoogle = function(){
    const collection = document.getElementById('bmcqe_collection');
    const delivery = document.getElementById('bmcqe_delivery');
    if (!collection || !delivery || !window.google || !google.maps || !google.maps.places) return;

    const collectionAutocomplete = new google.maps.places.Autocomplete(collection, { componentRestrictions: { country: 'gb' } });
    const deliveryAutocomplete = new google.maps.places.Autocomplete(delivery, { componentRestrictions: { country: 'gb' } });

    collectionAutocomplete.addListener('place_changed', scheduleQuote);
    deliveryAutocomplete.addListener('place_changed', scheduleQuote);
  };

  function selectedVehicle(){
    const selected = document.querySelector('input[name="bmcqe_vehicle"]:checked');
    return selected ? selected.value : 'small';
  }

  function selectedRadio(name, fallback){
    const selected = document.querySelector('input[name="' + name + '"]:checked');
    return selected ? selected.value : fallback;
  }

  function effectiveCollectionDate(){
    const data = window.bmcqeData || {};
    const today = data.today || '';
    const collectionDate = document.getElementById('bmcqe_collection_date');
    const collectionOption = selectedRadio('bmcqe_collection_option', 'asap');
    return collectionOption === 'dated' && collectionDate ? collectionDate.value : today;
  }

  function values(){
    const collection = document.getElementById('bmcqe_collection');
    const delivery = document.getElementById('bmcqe_delivery');
    const collectionDate = document.getElementById('bmcqe_collection_date');

    return {
      collection: collection ? collection.value.trim() : '',
      delivery: delivery ? delivery.value.trim() : '',
      vehicle: selectedVehicle(),
      collectionOption: selectedRadio('bmcqe_collection_option', 'asap'),
      collectionDate: collectionDate ? collectionDate.value : '',
      collectionPeriod: selectedRadio('bmcqe_collection_period', 'am'),
      deliveryOption: selectedRadio('bmcqe_delivery_option', 'same_day')
    };
  }

  function setMessage(text){
    const msg = document.getElementById('bmcqe_message');
    if (msg) msg.textContent = text || '';
  }

  function setPrice(text, note){
    const price = document.getElementById('bmcqe_price');
    const priceNote = document.getElementById('bmcqe_price_note');
    if (price) price.textContent = text;
    if (priceNote) priceNote.textContent = note || '';
  }

  function setBookEnabled(enabled){
    const button = document.getElementById('bmcqe_book_now');
    if (button) button.disabled = !enabled;
  }

  function sameDayIsAvailable(){
    const data = window.bmcqeData || {};
    const today = data.today || '';
    const cutoff = parseInt(data.sameDayCutoffHour || 12, 10);
    const currentHour = parseInt(data.currentHour || 0, 10);
    const selectedDate = effectiveCollectionDate();

    // Future collection dates can use Same Day for that selected collection date.
    if (selectedDate && today && selectedDate > today) return true;
    return currentHour < cutoff;
  }

  function updateDeliveryAvailability(){
    const sameDayChoice = document.getElementById('bmcqe_same_day_choice');
    const sameDayRadio = document.querySelector('input[name="bmcqe_delivery_option"][value="same_day"]');
    const nextDayRadio = document.querySelector('input[name="bmcqe_delivery_option"][value="next_day"]');
    const note = document.getElementById('bmcqe_delivery_note');
    const available = sameDayIsAvailable();

    if (sameDayChoice) sameDayChoice.classList.toggle('disabled', !available);
    if (sameDayRadio) sameDayRadio.disabled = !available;

    if (!available && sameDayRadio && sameDayRadio.checked && nextDayRadio) {
      nextDayRadio.checked = true;
    }

    if (note) {
      note.textContent = available
        ? 'Same Day is available when booked before 12pm. Next Day saves 5%. Within 2 days saves 10%.'
        : 'Same Day is no longer available for today. Next Day has been selected and saves 5%.';
    }

    updateChoiceStates(false);
  }

  function updateChoiceStates(shouldUpdateDelivery = true){
    document.querySelectorAll('.bmcqe-choice').forEach(label => {
      const input = label.querySelector('input');
      label.classList.toggle('selected', !!input && input.checked);
      label.classList.toggle('disabled', !!input && input.disabled);
    });

    const collectionOption = selectedRadio('bmcqe_collection_option', 'asap');
    const datedPanel = document.querySelector('.bmcqe-dated-collection');
    if (datedPanel) datedPanel.classList.toggle('bmcqe-hidden', collectionOption !== 'dated');

    if (shouldUpdateDelivery) updateDeliveryAvailability();
  }

  function requiredOptionsReady(){
    const v = values();
    if (v.collectionOption === 'dated') {
      if (!v.collectionDate) return false;
      if (!['am', 'pm'].includes(v.collectionPeriod)) return false;
    }
    return true;
  }

  function scheduleQuote(){
    clearTimeout(quoteTimer);
    quoteTimer = setTimeout(calculateQuote, 650);
  }

  function calculateQuote(){
    updateDeliveryAvailability();
    const v = values();

    latestQuote = null;
    setBookEnabled(false);

    if (!v.collection || !v.delivery) {
      setPrice('—', 'Enter both addresses to calculate your quote.');
      setMessage('');
      return;
    }

    if (!requiredOptionsReady()) {
      setPrice('—', 'Choose your collection option to calculate your quote.');
      setMessage('');
      return;
    }

    if (!window.bmcqeData || !bmcqeData.hasApiKey) {
      setPrice('—', 'Google API key has not been installed.');
      setMessage('');
      return;
    }

    if (isCalculating) {
      pendingCalculate = true;
      return;
    }

    isCalculating = true;
    pendingCalculate = false;
    setPrice('Calculating...', 'Checking the best driving route.');
    setMessage('');

    const fd = new FormData();
    fd.append('action', 'bmcqe_get_quote');
    fd.append('nonce', bmcqeData.nonce);
    fd.append('collection', v.collection);
    fd.append('delivery', v.delivery);
    fd.append('vehicle', v.vehicle);
    fd.append('collection_option', v.collectionOption);
    fd.append('collection_date', v.collectionDate);
    fd.append('collection_period', v.collectionPeriod);
    fd.append('delivery_option', v.deliveryOption);

    fetch(bmcqeData.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (!data.success) throw new Error(data.data || 'Could not calculate quote.');
        latestQuote = {
          collection: v.collection,
          delivery: v.delivery,
          vehicle: v.vehicle,
          collectionOption: v.collectionOption,
          collectionDate: data.data.collection_date || v.collectionDate,
          collectionPeriod: data.data.collection_period || v.collectionPeriod,
          deliveryOption: v.deliveryOption,
          price: data.data.price,
          miles: data.data.miles
        };

        let note = 'Same Day delivery selected.';
        if (data.data.delivery_option === 'next_day') note = 'Includes 5% saving for Next Day delivery.';
        if (data.data.delivery_option === 'within_2_days') note = 'Includes 10% saving for delivery within 2 days.';

        setPrice('£' + data.data.price, note);
        setBookEnabled(true);
      })
      .catch(err => {
        setPrice('—', 'Please check the details and try again.');
        setMessage(err.message);
      })
      .finally(() => {
        isCalculating = false;
        if (pendingCalculate) scheduleQuote();
      });
  }

  document.addEventListener('change', function(e){
    if (!e.target) return;

    if (e.target.name === 'bmcqe_vehicle') {
      document.querySelectorAll('.bmcqe-vehicle').forEach(el => el.classList.remove('selected'));
      const card = e.target.closest('.bmcqe-vehicle');
      if (card) card.classList.add('selected');
      scheduleQuote();
    }

    if (
      e.target.name === 'bmcqe_collection_option' ||
      e.target.name === 'bmcqe_collection_period' ||
      e.target.name === 'bmcqe_delivery_option' ||
      e.target.id === 'bmcqe_collection_date'
    ) {
      updateChoiceStates();
      scheduleQuote();
    }
  });

  document.addEventListener('input', function(e){
    if (!e.target) return;
    if (e.target.id === 'bmcqe_collection' || e.target.id === 'bmcqe_delivery') {
      scheduleQuote();
    }
  });

  document.addEventListener('click', function(e){
    if (!e.target || e.target.id !== 'bmcqe_book_now') return;
    if (!latestQuote) {
      setMessage('Please enter both addresses and wait for your quote first.');
      return;
    }

    const baseUrl = (window.bmcqeData && bmcqeData.paymentUrl) ? bmcqeData.paymentUrl : '/payment-details/';
    const url = new URL(baseUrl, window.location.origin);
    url.searchParams.set('collection', latestQuote.collection);
    url.searchParams.set('delivery', latestQuote.delivery);
    url.searchParams.set('vehicle', latestQuote.vehicle);
    url.searchParams.set('collection_option', latestQuote.collectionOption);
    url.searchParams.set('collection_date', latestQuote.collectionDate);
    url.searchParams.set('collection_period', latestQuote.collectionPeriod);
    url.searchParams.set('delivery_option', latestQuote.deliveryOption);
    url.searchParams.set('price', latestQuote.price);
    url.searchParams.set('miles', latestQuote.miles);
    window.location.href = url.toString();
  });

  document.addEventListener('DOMContentLoaded', function(){
    updateChoiceStates();
    scheduleQuote();
  });
})();

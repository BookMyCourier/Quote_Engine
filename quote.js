(function(){
  'use strict';

  let quoteTimer = null;
  let lastQuote = null;

  function storeQuote(){
    try { if (lastQuote) sessionStorage.setItem('bmcqeLastQuote', JSON.stringify(lastQuote)); } catch(e) {}
  }

  window.bmcqeInitGoogle = function(){
    const collection = document.getElementById('bmcqe_collection');
    const delivery = document.getElementById('bmcqe_delivery');
    if (!collection || !delivery || !window.google || !google.maps || !google.maps.places) return;

    const options = { componentRestrictions: { country: 'gb' }, fields: ['formatted_address', 'geometry', 'name'] };

    const collectionAutocomplete = new google.maps.places.Autocomplete(collection, options);
    const deliveryAutocomplete = new google.maps.places.Autocomplete(delivery, options);

    collectionAutocomplete.addListener('place_changed', scheduleQuote);
    deliveryAutocomplete.addListener('place_changed', scheduleQuote);
  };

  function qs(id){ return document.getElementById(id); }

  function selected(name){
    const el = document.querySelector('input[name="' + name + '"]:checked');
    return el ? el.value : '';
  }

  function setSelectedStyles(){
    document.querySelectorAll('.bmcqe-vehicle, .bmcqe-choice, .bmcqe-selector-card').forEach(function(label){
      const input = label.querySelector('input');
      label.classList.toggle('selected', !!input && input.checked);
    });
  }

  function updateOptionVisibility(){
    const collectionOption = selected('bmcqe_collection_option') || 'asap';
    const dated = document.querySelector('.bmcqe-dated-collection');
    if (dated) dated.classList.toggle('bmcqe-hidden', collectionOption !== 'dated');

    const today = (window.bmcqeData && bmcqeData.today) ? bmcqeData.today : '';
    const currentHour = (window.bmcqeData && bmcqeData.currentHour) ? Number(bmcqeData.currentHour) : 0;
    const cutoff = (window.bmcqeData && bmcqeData.sameDayCutoffHour) ? Number(bmcqeData.sameDayCutoffHour) : 12;
    const dateInput = qs('bmcqe_collection_date');
    const sameDayChoice = qs('bmcqe_same_day_choice');
    const sameDayRadio = sameDayChoice ? sameDayChoice.querySelector('input') : null;
    const note = qs('bmcqe_delivery_note');

    const activeDate = collectionOption === 'asap' ? today : (dateInput ? dateInput.value : today);
    const sameDayUnavailable = activeDate === today && currentHour >= cutoff;

    if (sameDayChoice && sameDayRadio) {
      sameDayChoice.classList.toggle('disabled', sameDayUnavailable);
      sameDayRadio.disabled = sameDayUnavailable;
      if (sameDayUnavailable && sameDayRadio.checked) {
        const nextDay = document.querySelector('input[name="bmcqe_delivery_option"][value="next_day"]');
        if (nextDay) nextDay.checked = true;
      }
    }

    if (note) {
      note.textContent = sameDayUnavailable
        ? 'Same Day is unavailable for today after 12pm. Please choose Next Day or Within 2 days.'
        : 'Same Day is available when booked before 12pm.';
    }

    setSelectedStyles();
  }

  function scheduleQuote(){
    clearTimeout(quoteTimer);
    quoteTimer = setTimeout(calculateQuote, 650);
  }

  function getPayload(){
    const collection = qs('bmcqe_collection');
    const delivery = qs('bmcqe_delivery');
    const dateInput = qs('bmcqe_collection_date');

    return {
      collection: collection ? collection.value.trim() : '',
      delivery: delivery ? delivery.value.trim() : '',
      vehicle: selected('bmcqe_vehicle') || 'small',
      collection_option: selected('bmcqe_collection_option') || 'asap',
      collection_date: dateInput ? dateInput.value : '',
      collection_period: selected('bmcqe_collection_period') || 'am',
      delivery_option: selected('bmcqe_delivery_option') || 'same_day'
    };
  }

  function setMessage(message){
    const msg = qs('bmcqe_message');
    if (msg) msg.textContent = message || '';
  }

  function setLoading(isLoading){
    const note = qs('bmcqe_price_note');
    if (note && isLoading) note.textContent = 'Calculating your quote...';
  }

  function resetQuote(noteText){
    lastQuote = null;
    try { sessionStorage.removeItem('bmcqeLastQuote'); } catch(e) {}
    const price = qs('bmcqe_price');
    const note = qs('bmcqe_price_note');
    const button = qs('bmcqe_book_now');

    if (price) price.textContent = '—';
    if (note) note.textContent = noteText || 'Enter both addresses to calculate your quote.';
    if (button) button.disabled = true;
  }

  function calculateQuote(){
    updateOptionVisibility();
    const payload = getPayload();

    if (!payload.collection || !payload.delivery) {
      resetQuote('Enter both addresses to calculate your quote.');
      return;
    }

    if (!window.bmcqeData || !bmcqeData.hasApiKey) {
      resetQuote('Google API key has not been added yet.');
      return;
    }

    setMessage('');
    setLoading(true);

    const formData = new FormData();
    formData.append('action', 'bmcqe_get_quote');
    formData.append('nonce', bmcqeData.nonce);
    Object.keys(payload).forEach(function(key){ formData.append(key, payload[key]); });

    fetch(bmcqeData.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function(res){ return res.json(); })
      .then(function(data){
        if (!data || !data.success) {
          resetQuote('Check your options and try again.');
          setMessage(data && data.data && data.data.message ? data.data.message : 'Could not calculate that route.');
          return;
        }

        lastQuote = Object.assign({}, payload, data.data);
        storeQuote();

        const price = qs('bmcqe_price');
        const note = qs('bmcqe_price_note');
        const button = qs('bmcqe_book_now');

        if (price) price.textContent = '£' + data.data.price;

        let noteText = 'Based on your selected vehicle and delivery option.';
        if (Number(data.data.discount_percent) > 0) {
          noteText = data.data.discount_percent + '% saving applied to this quote.';
        }
        if (note) note.textContent = noteText;
        if (button) button.disabled = false;
      })
      .catch(function(){
        resetQuote('Something went wrong calculating the quote.');
      });
  }

  function goToBooking(){
    if (!window.bmcqeData || !bmcqeData.paymentUrl) return;

    if (!lastQuote) {
      try {
        const stored = sessionStorage.getItem('bmcqeLastQuote');
        if (stored) lastQuote = JSON.parse(stored);
      } catch(e) {}
    }

    if (!lastQuote) {
      const payload = getPayload();
      const priceText = (qs('bmcqe_price') && qs('bmcqe_price').textContent) ? qs('bmcqe_price').textContent.replace('£','').trim() : '';
      if (payload.collection && payload.delivery && priceText && priceText !== '—') {
        lastQuote = Object.assign({}, payload, { price: priceText, miles: '', discount_percent: 0 });
      }
    }

    if (!lastQuote || !lastQuote.collection || !lastQuote.delivery || !lastQuote.price) {
      setMessage('Please wait for the quote to calculate, then try Book Now again.');
      scheduleQuote();
      return;
    }

    const params = new URLSearchParams();
    [
      'collection','delivery','vehicle','price','miles','collection_option',
      'collection_date','collection_period','delivery_option','discount_percent'
    ].forEach(function(key){
      if (lastQuote[key] !== undefined && lastQuote[key] !== null) params.set(key, lastQuote[key]);
    });

    const separator = bmcqeData.paymentUrl.indexOf('?') === -1 ? '?' : '&';
    window.location.href = bmcqeData.paymentUrl + separator + params.toString();
  }

  document.addEventListener('input', function(e){
    if (!e.target) return;
    if (e.target.id === 'bmcqe_collection' || e.target.id === 'bmcqe_delivery') scheduleQuote();
  });

  document.addEventListener('change', function(e){
    if (!e.target) return;
    if (
      e.target.name === 'bmcqe_vehicle' ||
      e.target.name === 'bmcqe_collection_option' ||
      e.target.name === 'bmcqe_collection_period' ||
      e.target.name === 'bmcqe_delivery_option' ||
      e.target.id === 'bmcqe_collection_date'
    ) {
      updateOptionVisibility();
      scheduleQuote();
    }
  });

  document.addEventListener('click', function(e){
    const button = e.target && e.target.closest ? e.target.closest('#bmcqe_book_now') : null;
    if (button) {
      e.preventDefault();
      goToBooking();
      return;
    }

    const selector = e.target && e.target.closest ? e.target.closest('.bmcqe-selector-card, .bmcqe-vehicle') : null;
    if (selector && !selector.classList.contains('disabled')) {
      const input = selector.querySelector('input');
      if (input && !input.disabled) {
        input.checked = true;
        updateOptionVisibility();
        scheduleQuote();
      }
    }
  });

  document.addEventListener('DOMContentLoaded', function(){
    updateOptionVisibility();
    setSelectedStyles();
    scheduleQuote();
  });
})();

(function(){
  let quoteTimer = null;
  let isCalculating = false;
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

  function values(){
    const collection = document.getElementById('bmcqe_collection');
    const delivery = document.getElementById('bmcqe_delivery');
    return {
      collection: collection ? collection.value.trim() : '',
      delivery: delivery ? delivery.value.trim() : '',
      vehicle: selectedVehicle()
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

  function scheduleQuote(){
    clearTimeout(quoteTimer);
    quoteTimer = setTimeout(calculateQuote, 650);
  }

  function calculateQuote(){
    const v = values();

    latestQuote = null;
    setBookEnabled(false);

    if (!v.collection || !v.delivery) {
      setPrice('—', 'Enter both addresses to calculate your quote.');
      setMessage('');
      return;
    }

    if (!window.bmcqeData || !bmcqeData.hasApiKey) {
      setPrice('—', 'Google API key has not been installed.');
      setMessage('');
      return;
    }

    if (isCalculating) return;
    isCalculating = true;
    setPrice('Calculating...', 'Checking the best driving route.');
    setMessage('');

    const fd = new FormData();
    fd.append('action', 'bmcqe_get_quote');
    fd.append('nonce', bmcqeData.nonce);
    fd.append('collection', v.collection);
    fd.append('delivery', v.delivery);
    fd.append('vehicle', v.vehicle);

    fetch(bmcqeData.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (!data.success) throw new Error(data.data || 'Could not calculate quote.');
        latestQuote = {
          collection: v.collection,
          delivery: v.delivery,
          vehicle: v.vehicle,
          price: data.data.price,
          miles: data.data.miles
        };
        setPrice('£' + data.data.price, 'No hidden fees. Final price may vary if extra services are required.');
        setBookEnabled(true);
      })
      .catch(err => {
        setPrice('—', 'Please check the addresses and try again.');
        setMessage(err.message);
      })
      .finally(() => {
        isCalculating = false;
      });
  }

  document.addEventListener('change', function(e){
    if (e.target && e.target.name === 'bmcqe_vehicle') {
      document.querySelectorAll('.bmcqe-vehicle').forEach(el => el.classList.remove('selected'));
      const card = e.target.closest('.bmcqe-vehicle');
      if (card) card.classList.add('selected');
      scheduleQuote();
    }
  });

  document.addEventListener('input', function(e){
    if (e.target && (e.target.id === 'bmcqe_collection' || e.target.id === 'bmcqe_delivery')) {
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
    url.searchParams.set('price', latestQuote.price);
    url.searchParams.set('miles', latestQuote.miles);
    window.location.href = url.toString();
  });
})();
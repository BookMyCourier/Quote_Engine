(function(){
  'use strict';

  function qs(sel){ return document.querySelector(sel); }

  document.addEventListener('change', function(e){
    if (e.target && e.target.name === 'collection_same_as_customer') {
      const fields = document.querySelector('.bmcqe-collection-contact-fields');
      if (fields) fields.classList.toggle('bmcqe-hidden', e.target.checked);
    }
  });

  document.addEventListener('submit', function(e){
    const form = e.target && e.target.id === 'bmcqe_booking_form' ? e.target : null;
    if (!form) return;

    e.preventDefault();

    const button = qs('#bmcqe_test_payment_button');
    const message = qs('#bmcqe_booking_message');
    const confirmation = qs('#bmcqe_confirmation');
    const ref = qs('#bmcqe_booking_reference');

    if (button) {
      button.disabled = true;
      button.textContent = 'Processing test payment...';
    }
    if (message) message.textContent = '';

    const formData = new FormData(form);
    formData.append('action', 'bmcqe_create_test_booking');
    formData.append('nonce', bmcqeBookingData.nonce);

    fetch(bmcqeBookingData.ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function(res){ return res.json(); })
      .then(function(data){
        if (!data || !data.success) {
          if (message) message.textContent = data && data.data && data.data.message ? data.data.message : 'Could not create the test booking.';
          return;
        }

        form.classList.add('bmcqe-hidden');
        if (confirmation) confirmation.classList.remove('bmcqe-hidden');
        if (ref) ref.textContent = data.data.reference;
        window.scrollTo({ top: 0, behavior: 'smooth' });
      })
      .catch(function(){
        if (message) message.textContent = 'Something went wrong creating the test booking.';
      })
      .finally(function(){
        if (button) {
          button.disabled = false;
          button.textContent = 'Complete Test Payment';
        }
      });
  });
})();

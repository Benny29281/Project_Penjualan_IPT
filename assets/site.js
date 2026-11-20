const languageSelect = document.querySelector('[data-language-select]');
languageSelect?.addEventListener('change', () => languageSelect.form.requestSubmit());

const pageBack = document.querySelector('[data-back-link]');
pageBack?.addEventListener('click', event => {
  if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
  if (window.navigation?.canGoBack) {
    event.preventDefault();
    window.history.back();
  }
});

const search = document.querySelector('[data-search]');
const products = Array.from(document.querySelectorAll('[data-product]'));
const filters = { category: '', edition: '' };
const groups = Array.from(document.querySelectorAll('[data-filters]'));

function filterCatalog() {
  const term = search.value.trim().toLocaleLowerCase();
  let count = 0;
  for (const product of products) {
    product.hidden = Boolean(
      (filters.category && product.dataset.category !== filters.category) ||
      (filters.edition && product.dataset.edition !== filters.edition) ||
      (term && !product.dataset.text.toLocaleLowerCase().includes(term))
    );
    if (!product.hidden) count += 1;
  }
  document.querySelector('[data-result-count]').textContent = count;
  document.querySelector('[data-empty]').hidden = count !== 0;
}
for (const group of groups) {
  group.addEventListener('click', event => {
    const chip = event.target.closest('[data-filter]');
    if (!chip) return;
    filters[group.dataset.filters] = chip.dataset.filter;
    for (const button of group.querySelectorAll('[data-filter]')) {
      button.setAttribute('aria-pressed', String(button === chip));
    }
    filterCatalog();
  });
}
search?.addEventListener('input', filterCatalog);
document.querySelector('[data-reset]')?.addEventListener('click', () => {
  search.value = '';
  for (const group of groups) group.querySelector('[data-filter=""]').click();
  search.focus();
});

let toastTimeout;
function showToast(text) {
  const toast = document.querySelector('[data-toast]');
  if (!toast) return;
  toast.hidden = false;
  toast.textContent = text;
  clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => { toast.hidden = true; }, 3400);
}

for (const gallery of document.querySelectorAll('[data-gallery]')) {
  const photos = Array.from(gallery.querySelectorAll('[data-gallery-photo]'));
  if (!photos.length) continue;
  const thumbnails = Array.from(gallery.querySelectorAll('[data-gallery-index]'));
  const zoom = gallery.querySelector('[data-gallery-zoom]');
  const dialog = gallery.querySelector('[data-gallery-dialog]');
  const previous = gallery.querySelector('[data-gallery-step="-1"]');
  const next = gallery.querySelector('[data-gallery-step="1"]');
  const counter = gallery.querySelector('[data-gallery-current]');
  let index = 0;

  function showPhoto(selected) {
    photos[index].hidden = true;
    thumbnails[index].removeAttribute('aria-current');
    index = selected;
    photos[index].hidden = false;
    thumbnails[index].setAttribute('aria-current', 'true');
    counter.textContent = index + 1;
    previous.disabled = index === 0;
    next.disabled = index === photos.length - 1;
    zoom.href = photos[index].src;
  }
  previous?.addEventListener('click', () => showPhoto(index - 1));
  next?.addEventListener('click', () => showPhoto(index + 1));
  for (const thumbnail of thumbnails) {
    thumbnail.addEventListener('click', event => {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      showPhoto(Number(thumbnail.dataset.galleryIndex));
    });
  }
  zoom.addEventListener('click', event => {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || typeof dialog.showModal !== 'function') return;
    event.preventDefault();
    dialog.querySelector('img').src = zoom.href;
    dialog.showModal();
  });
}

document.addEventListener('click', async event => {
  const dialogButton = event.target.closest('[data-dialog]');
  if (dialogButton) document.getElementById(dialogButton.dataset.dialog).showModal();
  const copyButton = event.target.closest('[data-copy]');
  if (copyButton) {
    const dialog = document.getElementById(copyButton.dataset.copy);
    const textarea = dialog.querySelector('textarea');
    try {
      await navigator.clipboard.writeText(textarea.value);
      showToast(copyButton.dataset.copied);
    } catch {
      dialog.showModal();
      textarea.focus();
      textarea.select();
    }
  }
});

const acknowledgment = document.querySelector('#confirm-order [name="confirmation_ack"]');
if (acknowledgment) {
  const confirmButton = acknowledgment.form.querySelector('button[type="submit"]');
  const syncConfirmation = () => { confirmButton.disabled = !acknowledgment.checked; };
  acknowledgment.addEventListener('change', syncConfirmation);
  acknowledgment.closest('dialog').addEventListener('close', () => {
    acknowledgment.checked = false;
    syncConfirmation();
  });
  window.addEventListener('pageshow', syncConfirmation);
  syncConfirmation();
}

const quantityInput = document.querySelector('#product-quantity');
if (quantityInput) {
  const buttons = quantityInput.parentElement.querySelectorAll('[data-quantity-step]');
  function syncQuantity() {
    const value = quantityInput.valueAsNumber;
    buttons[0].disabled = !Number.isFinite(value) || value <= Number(quantityInput.min);
    buttons[1].disabled = !Number.isFinite(value) || value >= Number(quantityInput.max);
  }
  quantityInput.addEventListener('input', syncQuantity);
  for (const button of buttons) {
    button.addEventListener('click', () => {
      if (button.dataset.quantityStep === '1') quantityInput.stepUp();
      else quantityInput.stepDown();
      syncQuantity();
    });
  }
  const form = quantityInput.form;
  if (form.classList.contains('add-to-cart-form')) {
    const submit = form.querySelector('.detail-add');
    const status = document.querySelector('[data-cart-status]');
    const report = (message, failed = false) => {
      status.classList.toggle('alert-error', failed);
      status.hidden = false;
      status.textContent = message;
    };
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (submit.disabled) return;
      const data = new FormData(form);
      submit.disabled = true;
      status.hidden = true;
      try {
        const response = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json' }, body: data });
        const result = await response.json();
        if (!response.ok) {
          report(result.error || status.dataset.failure, true);
          return;
        }
        for (const badge of document.querySelectorAll('[data-cart-count]')) badge.textContent = result.count;
        quantityInput.max = result.remaining;
        if (result.remaining > 0) {
          quantityInput.value = Math.max(1, Math.min(quantityInput.valueAsNumber || 1, result.remaining));
          syncQuantity();
        } else {
          quantityInput.closest('.detail-quantity').hidden = true;
          submit.textContent = status.dataset.limit;
        }
        report(result.message);
      } catch {
        report(status.dataset.failure, true);
      } finally {
        submit.disabled = Number(quantityInput.max) < 1;
      }
    });
  }
}

const category = document.querySelector('[data-product-category]');
if (category) {
  function showUnits() {
    const isDevice = category.value.startsWith('IQOS');
    for (const fieldset of document.querySelectorAll('[data-unit]')) {
      fieldset.hidden = isDevice !== (fieldset.dataset.unit === 'unit');
      fieldset.disabled = fieldset.hidden;
    }
  }
  category.addEventListener('change', showUnits);
  showUnits();
}
const box = document.querySelector('[data-box]');
if (box) {
  box.addEventListener('input', () => {
    for (const tier of document.querySelectorAll('[data-tier]')) {
      tier.hidden = Number(tier.dataset.tier) > Number(box.value);
      tier.querySelector('input').required = !tier.hidden;
    }
  });
}

const delivery = document.querySelector('[data-delivery-fields]');
if (delivery) {
  const country = delivery.querySelector('[data-delivery-country]');
  const localFields = delivery.querySelector('[data-indonesian-regions]');
  const manualFields = delivery.querySelector('[data-manual-regions]');
  const manualHelp = delivery.querySelector('[data-manual-help]');
  const selectFields = Array.from(localFields.querySelectorAll('[data-region-select]'));
  const postal = delivery.querySelector('[data-delivery-postal]');
  const postalHelp = delivery.querySelector('[data-postal-automatic]');
  const loading = delivery.querySelector('[data-region-loading]');
  const error = delivery.querySelector('[data-region-error-box]');
  const retry = delivery.querySelector('[data-region-retry]');
  const submit = delivery.closest('form').querySelector('[data-delivery-submit]');
  let request;
  let retryIndex = -1;

  function updatePostal(clear) {
    const local = country.value === 'ID';
    const code = local ? selectFields.at(-1).selectedOptions[0]?.dataset.postal : '';
    postal.readOnly = Boolean(code);
    postalHelp.hidden = !code;
    if (local) {
      postal.pattern = '[0-9]{5}';
      postal.inputMode = 'numeric';
    } else {
      postal.removeAttribute('pattern');
      postal.removeAttribute('inputmode');
    }
    if (code) postal.value = code;
    else if (clear) postal.value = '';
  }

  function resetOptions(select, label) {
    select.replaceChildren(new Option(label, ''));
    select.disabled = true;
  }

  function regionStatus(isLoading, failed = false) {
    loading.hidden = !isLoading;
    error.hidden = !failed;
    localFields.setAttribute('aria-busy', String(isLoading));
    submit.disabled = isLoading || failed;
  }

  async function updateRegions(index) {
    request?.abort();
    regionStatus(false);
    for (const select of selectFields.slice(index + 1)) resetOptions(select, select.dataset.parentPrompt);
    updatePostal(true);
    const next = selectFields[index + 1];
    const parent = selectFields[index].value;
    if (!next || !parent) return;
    const controller = new AbortController();
    request = controller;
    regionStatus(true);
    const url = new URL(delivery.dataset.regionUrl, window.location.href);
    url.searchParams.set('parent', parent);
    try {
      const response = await fetch(url, { signal: controller.signal });
      if (!response.ok) throw new Error();
      const regions = await response.json();
      if (controller.signal.aborted) return;
      resetOptions(next, delivery.dataset.regionPlaceholder);
      for (const region of regions) {
        const option = new Option(region.name, region.id);
        option.dataset.postal = region.postal;
        next.add(option);
      }
      next.disabled = false;
      regionStatus(false);
    } catch {
      if (controller.signal.aborted) return;
      retryIndex = index;
      regionStatus(false, true);
    }
  }

  country.addEventListener('change', () => {
    const local = country.value === 'ID';
    localFields.hidden = localFields.disabled = !local;
    manualFields.hidden = manualFields.disabled = local;
    manualHelp.hidden = local;
    if (!local) request?.abort();
    regionStatus(false);
    updatePostal(true);
    if (local) {
      const incomplete = selectFields.findIndex((select, index) => index > 0 && select.disabled && selectFields[index - 1].value);
      if (incomplete > 0) updateRegions(incomplete - 1);
    }
  });
  selectFields.forEach((select, index) => select.addEventListener('change', () => updateRegions(index)));
  retry.addEventListener('click', () => { if (retryIndex >= 0) updateRegions(retryIndex); });
  updatePostal(false);
}

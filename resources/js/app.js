// Before/after slider (resources/views/components/site/before-after.blade.php): the range input is
// the accessible control; mirror its value into the --position the clip-path and handle use.
for (const input of document.querySelectorAll('[data-before-after]')) {
    const figure = input.closest('.before-after');
    const update = () => figure.style.setProperty('--position', `${input.value}%`);

    input.addEventListener('input', update);
    update();
}

// Where a visitor came from, for the quote form (App\Livewire\QuoteWizard reads it). Content pages
// set no cookies, so it lives in localStorage: the first landing page and outside referrer, unless a
// later visit carries campaign tags, which replace it. Kept for 90 days.
const attributionKey = 'shoreline.attribution';

window.storedAttribution = () => {
    try {
        return JSON.parse(localStorage.getItem(attributionKey) || '{}');
    } catch {
        return {};
    }
};

try {
    const key = attributionKey;
    const params = new URLSearchParams(location.search);
    const tags = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'].filter((tag) => params.get(tag));
    const stored = JSON.parse(localStorage.getItem(key) || 'null');
    const isFresh = stored && Date.now() - (stored.captured_at || 0) < 90 * 24 * 60 * 60 * 1000;

    if (tags.length > 0 || !isFresh) {
        const visit = { captured_at: Date.now(), landing_page: location.pathname + location.search };
        const referrer = document.referrer ? new URL(document.referrer) : null;

        if (referrer && referrer.host !== location.host) {
            visit.referrer = referrer.origin + referrer.pathname;
        }

        for (const tag of tags) {
            visit[tag] = params.get(tag);
        }

        localStorage.setItem(key, JSON.stringify(visit));
    }
} catch {
    // Private browsing or storage turned off: no attribution, nothing else lost.
}

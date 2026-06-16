import './bootstrap';

// Public marketing pages (landing, tools, guest PageSpeed/audit reports) are
// NOT Livewire pages, so they don't get the Alpine instance Livewire injects
// on portal pages. The shared report partials rely on Alpine (x-data tabs,
// x-show panels, x-cloak), so without it those panels stay hidden behind the
// `[x-cloak]{display:none}` rule. Boot a standalone Alpine here. This bundle
// is loaded ONLY by the marketing layout, so it never collides with the
// Livewire-bundled Alpine on authenticated pages.
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

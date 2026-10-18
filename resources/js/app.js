import './bootstrap';
import Alpine from 'alpinejs';
import { createMap } from './peta';
import { laporanForm } from './laporan-form';
import { pickLocation } from './pick-location';

window.Alpine = Alpine;
Alpine.data('peta', createMap);
Alpine.data('laporanForm', laporanForm);
Alpine.data('pickLocation', pickLocation);
Alpine.start();

if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

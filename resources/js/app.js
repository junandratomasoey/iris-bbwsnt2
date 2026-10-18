import './bootstrap';
import './lihat-foto';
import Alpine from 'alpinejs';
import { createMap } from './peta';
import { laporanForm } from './laporan-form';
import { pickLocation } from './pick-location';
import { petaEditor } from './peta-editor';
import { pasangAplikasi } from './pasang-aplikasi';
import { unggahFoto } from './foto';
import { ASSET } from './base';

window.Alpine = Alpine;
Alpine.data('peta', createMap);
Alpine.data('laporanForm', laporanForm);
Alpine.data('pickLocation', pickLocation);
Alpine.data('petaEditor', petaEditor);
Alpine.data('pasangAplikasi', pasangAplikasi);
Alpine.data('unggahFoto', unggahFoto);
Alpine.start();

if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
    window.addEventListener('load', () => navigator.serviceWorker.register(`${ASSET}/sw.js`, { scope: `${ASSET}/` }).catch(() => {}));
}

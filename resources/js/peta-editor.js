import L from 'leaflet';
import '@geoman-io/leaflet-geoman-free';

/**
 * Editor batas D.I.: gambar, ubah titik, potong lubang, hapus bagian poligon.
 * Hasil disimpan sebagai GeoJSON (Polygon/MultiPolygon) ke input tersembunyi.
 */
export function petaEditor(opsi) {
    let map = null;
    let grup = null;      // bagian poligon yang sedang diedit
    let acuan = null;     // batas D.I. lain sebagai acuan

    const R = 6378137;
    const luasRing = (r) => {
        let s = 0;
        for (let i = 0; i < r.length - 1; i++) {
            const [x1, y1] = r[i]; const [x2, y2] = r[i + 1];
            s += (x2 - x1) * Math.PI / 180 * (2 + Math.sin(y1 * Math.PI / 180) + Math.sin(y2 * Math.PI / 180));
        }
        return Math.abs(s * R * R / 2);
    };

    return {
        geometri: opsi.geometri ? JSON.stringify(opsi.geometri) : '',
        jumlahBagian: 0,
        luasHa: 0,
        berubah: false,
        tampilAcuan: true,

        init() {
            map = L.map(this.$refs.peta, { zoomControl: true });
            const citra = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 20, maxNativeZoom: 19, attribution: 'Esri' });
            const jalan = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' });
            citra.addTo(map);
            L.control.layers({ 'Citra satelit': citra, 'Peta jalan': jalan }, null, { position: 'topright' }).addTo(map);

            grup = L.featureGroup().addTo(map);
            const gaya = { color: '#D9622B', weight: 2, fillOpacity: 0.25 };
            if (opsi.geometri) {
                // pecah MultiPolygon menjadi poligon terpisah agar tiap bagian bisa diedit sendiri
                const polys = opsi.geometri.type === 'Polygon' ? [opsi.geometri.coordinates] : opsi.geometri.coordinates;
                polys.forEach((c) => L.geoJSON({ type: 'Polygon', coordinates: c }, { style: gaya }).eachLayer((l) => grup.addLayer(l)));
            }

            if (opsi.acuanUrl) {
                fetch(opsi.acuanUrl).then((r) => r.json()).then((gj) => {
                    acuan = L.geoJSON(gj, {
                        filter: (f) => f.properties.id !== opsi.id,
                        style: { color: '#ffffff', weight: 1, dashArray: '4 3', fillOpacity: 0.08, interactive: false },
                        pmIgnore: true,
                    });
                    if (this.tampilAcuan) acuan.addTo(map);
                    acuan.bringToBack();
                }).catch(() => {});
            }

            if (grup.getLayers().length) map.fitBounds(grup.getBounds(), { padding: [30, 30] });
            else map.setView(opsi.pusat || [-9.6, 122.0], opsi.pusat ? 13 : 7);

            map.pm.setLang('id');
            map.pm.addControls({
                position: 'topleft', drawMarker: false, drawCircleMarker: false, drawPolyline: false, drawRectangle: true,
                drawCircle: false, drawText: false, rotateMode: false, dragMode: true, cutPolygon: true, editMode: true, removalMode: true,
            });
            map.pm.setGlobalOptions({ layerGroup: grup, snappable: true, snapDistance: 15, allowSelfIntersection: false, pathOptions: gaya, templineStyle: { color: '#D9622B' }, hintlineStyle: { color: '#D9622B', dashArray: '5 5' } });

            const ubah = () => { this.berubah = true; this.serialisasi(); };
            map.on('pm:create', (e) => { grup.addLayer(e.layer); e.layer.on('pm:edit pm:dragend', ubah); ubah(); });
            map.on('pm:remove pm:cut', () => setTimeout(ubah, 0));
            grup.eachLayer((l) => l.on('pm:edit pm:dragend', ubah));
            map.on('pm:globaleditmodetoggled pm:globaldragmodetoggled', ubah);

            this.$watch('tampilAcuan', (v) => { if (acuan) v ? acuan.addTo(map) : map.removeLayer(acuan); });
            this.serialisasi();
        },

        serialisasi() {
            const polys = [];
            grup.eachLayer((l) => {
                const g = l.toGeoJSON(7).geometry;
                if (g.type === 'Polygon') polys.push(g.coordinates);
                else if (g.type === 'MultiPolygon') polys.push(...g.coordinates);
            });
            this.jumlahBagian = polys.length;
            this.luasHa = polys.reduce((t, rings) => t + rings.reduce((a, r, i) => a + (i ? -1 : 1) * luasRing(r), 0), 0) / 10000;
            this.geometri = polys.length === 0 ? ''
                : JSON.stringify(polys.length === 1 ? { type: 'Polygon', coordinates: polys[0] } : { type: 'MultiPolygon', coordinates: polys });
        },

        async muatBerkas(e) {
            const f = e.target.files[0];
            e.target.value = '';
            if (!f) return;
            try {
                const gj = JSON.parse(await f.text());
                const feats = gj.type === 'FeatureCollection' ? gj.features : gj.type === 'Feature' ? [gj] : [{ geometry: gj }];
                let n = 0;
                feats.forEach((ft) => {
                    const g = ft.geometry;
                    if (!g || !['Polygon', 'MultiPolygon'].includes(g.type)) return;
                    const polys = g.type === 'Polygon' ? [g.coordinates] : g.coordinates;
                    polys.forEach((c) => L.geoJSON({ type: 'Polygon', coordinates: c }, { style: { color: '#D9622B', weight: 2, fillOpacity: 0.25 } })
                        .eachLayer((l) => { grup.addLayer(l); l.on('pm:edit pm:dragend', () => { this.berubah = true; this.serialisasi(); }); n++; }));
                });
                if (!n) throw new Error('kosong');
                map.fitBounds(grup.getBounds(), { padding: [30, 30] });
                this.berubah = true;
                this.serialisasi();
            } catch (err) {
                this.$dispatch('galat-peta', 'File tidak berisi poligon GeoJSON yang bisa dibaca.');
            }
        },

        hapusSemua() {
            grup.clearLayers();
            this.berubah = true;
            this.serialisasi();
        },
    };
}

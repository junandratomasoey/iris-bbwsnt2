import L from 'leaflet';

/** Pilih titik koordinat dengan klik di peta (form Sub-D.I., D.I., kejadian). */
export function pickLocation(opsi) {
    let map = null;
    let penanda = null;

    return {
        lat: opsi.lat ?? '',
        lng: opsi.lng ?? '',
        init() {
            const pusat = this.lat && this.lng ? [+this.lat, +this.lng] : (opsi.pusat || [-9.6, 122.0]);
            map = L.map(this.$refs.peta, { scrollWheelZoom: false }).setView(pusat, this.lat ? 13 : (opsi.pusat ? 11 : 7));
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Esri' }).addTo(map);
            if (this.lat && this.lng) this.pasang();
            map.on('click', (e) => {
                this.lat = e.latlng.lat.toFixed(7);
                this.lng = e.latlng.lng.toFixed(7);
                this.pasang();
            });
            this.$watch('lat', () => this.pasang());
            this.$watch('lng', () => this.pasang());
        },
        pasang() {
            if (!this.lat || !this.lng || isNaN(+this.lat) || isNaN(+this.lng)) return;
            const ll = [+this.lat, +this.lng];
            if (penanda) penanda.setLatLng(ll);
            else penanda = L.circleMarker(ll, { radius: 8, color: '#fff', weight: 2, fillColor: '#D9622B', fillOpacity: 1 }).addTo(map);
        },
        gpsSaya() {
            navigator.geolocation?.getCurrentPosition((p) => {
                this.lat = p.coords.latitude.toFixed(7);
                this.lng = p.coords.longitude.toFixed(7);
                map.setView([+this.lat, +this.lng], 15);
            });
        },
    };
}

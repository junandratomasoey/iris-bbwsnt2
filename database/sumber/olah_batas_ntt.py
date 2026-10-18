"""SHP batas administrasi NTT (UTM 51S) → GeoJSON WGS84 per kabupaten, disederhanakan untuk web."""
import json, sys, collections
import shapefile
from pyproj import Transformer
from shapely.geometry import shape, mapping
from shapely.ops import unary_union, transform

sumber, tujuan = sys.argv[1], sys.argv[2]
r = shapefile.Reader(sumber, encoding='utf-8', encodingErrors='replace')
utm = Transformer.from_crs('EPSG:32751', 'EPSG:4326', always_xy=True)
grup = collections.defaultdict(list)
for sh, rec in zip(r.shapes(), r.records()):
    g = shape(sh.__geo_interface__).buffer(0)
    if g.is_empty:
        continue
    grup[(rec['komentar'] or '').strip(), rec['k_prokab']].append(g)

fitur, total = [], 0
for (nama, kode), gs in sorted(grup.items()):
    g = unary_union(gs)
    g = transform(utm.transform, g)
    # ±0,0008° ≈ 90 m: cukup halus untuk zoom provinsi s.d. kabupaten
    g = g.simplify(0.0008, preserve_topology=True).buffer(0)
    # buang pulau sangat kecil (< ~1 ha) agar file ringan
    if g.geom_type == 'MultiPolygon':
        g = unary_union([p for p in g.geoms if p.area > 1e-6]) or g
    def bulat(geo):
        m = mapping(geo)
        def r6(c):
            return [r6(x) for x in c] if isinstance(c[0], (list, tuple)) else [round(c[0], 5), round(c[1], 5)]
        m['coordinates'] = r6(m['coordinates'])
        return m
    geom = bulat(g)
    n = sum(len(ring) for poly in (geom['coordinates'] if geom['type'] == 'MultiPolygon' else [geom['coordinates']]) for ring in poly)
    total += n
    fitur.append({'type': 'Feature', 'properties': {'nama': nama.replace('Kab.', 'Kabupaten').replace('Kota ', 'Kota ').strip(), 'kode': kode}, 'geometry': geom})
    print(f'{kode} {nama:28} {n:6} titik  bbox {[round(x, 2) for x in g.bounds]}')
json.dump({'type': 'FeatureCollection', 'name': 'batas_kabupaten_ntt', 'features': fitur}, open(tujuan, 'w'), separators=(',', ':'))
print(len(fitur), 'kabupaten,', total, 'titik')

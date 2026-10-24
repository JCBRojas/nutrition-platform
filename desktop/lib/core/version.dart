/// Compara versiones «x.y.z»: negativo si a < b, 0 si son iguales, positivo si a > b
int compareVersions(String a, String b) {
  List<int> parts(String v) => v.split('+').first.split('.').map((p) => int.tryParse(p) ?? 0).toList();
  final pa = parts(a), pb = parts(b);
  for (var i = 0; i < 3; i++) {
    final x = i < pa.length ? pa[i] : 0, y = i < pb.length ? pb[i] : 0;
    if (x != y) return x - y;
  }
  return 0;
}

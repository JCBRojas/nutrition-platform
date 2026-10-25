#!/usr/bin/env bash
# Empaqueta NutriTrack para macOS: compila en release, firma con Developer ID (runtime
# endurecido, requisito de la notarización), crea el .dmg y lo notariza con Apple.
# Sin MACOS_SIGN_IDENTITY deja un .dmg sin firmar, solo para pruebas internas (Gatekeeper
# lo bloquea: clic derecho → Abrir).
#
# Variables (en la CI vienen de los secretos de GitHub; nunca en el repositorio):
#   MACOS_SIGN_IDENTITY  «Developer ID Application: Nombre (TEAMID)», o «-» para una firma
#                        ad-hoc de prueba (sin notarizar; solo abre en el equipo que la firmó)
#   MACOS_KEYCHAIN       llavero con el certificado (opcional; por defecto, el del usuario)
#   APPLE_API_KEY_PATH   clave .p8 de la API de App Store Connect (notarización)
#   APPLE_API_KEY_ID, APPLE_API_ISSUER
#   SKIP_BUILD=1         usa la compilación existente
set -euo pipefail
cd "$(dirname "$0")/.."

VERSION=$(sed -n 's/^version: *\([^+]*\).*/\1/p' pubspec.yaml)
APP=build/macos/Build/Products/Release/NutriTrack.app
DMG="dist/NutriTrack-$VERSION-macos.dmg"
IDENTITY="${MACOS_SIGN_IDENTITY:-}"
# Arreglos con «${a[@]+...}»: el bash 3.2 de macOS falla con un arreglo vacío y set -u
KEYCHAIN_ARGS=()
[ -n "${MACOS_KEYCHAIN:-}" ] && KEYCHAIN_ARGS=(--keychain "$MACOS_KEYCHAIN")
# Runtime endurecido y sello de tiempo (exigidos por la notarización). Con «-» se omiten: sin
# Team ID, la validación de bibliotecas del runtime endurecido impide cargar FlutterMacOS.framework
SIGN_ARGS=(--timestamp --options runtime)
[ "$IDENTITY" = "-" ] && SIGN_ARGS=()

sign() {
  codesign --force ${SIGN_ARGS[@]+"${SIGN_ARGS[@]}"} --sign "$IDENTITY" ${KEYCHAIN_ARGS[@]+"${KEYCHAIN_ARGS[@]}"} "$@"
}

[ "${SKIP_BUILD:-0}" = 1 ] || flutter build macos --release

if [ -n "$IDENTITY" ]; then
  # De adentro hacia afuera: frameworks y bibliotecas, luego la app con sus permisos
  find "$APP/Contents/Frameworks" -maxdepth 1 \( -name '*.framework' -o -name '*.dylib' \) -print0 |
    while IFS= read -r -d '' item; do sign "$item"; done
  sign --entitlements macos/Runner/Release.entitlements "$APP"
  codesign --verify --deep --strict --verbose=2 "$APP"
fi

mkdir -p dist
rm -f "$DMG"
STAGE=$(mktemp -d)
trap 'rm -rf "$STAGE"' EXIT
cp -R "$APP" "$STAGE/"
ln -s /Applications "$STAGE/Aplicaciones"
hdiutil create -quiet -volname "NutriTrack $VERSION" -srcfolder "$STAGE" -ov -format UDZO "$DMG"

if [ -z "$IDENTITY" ]; then
  echo "Aviso: .dmg sin firmar (falta MACOS_SIGN_IDENTITY). Solo para pruebas internas."
elif [ "$IDENTITY" = "-" ] || [ -z "${APPLE_API_KEY_PATH:-}" ]; then
  sign "$DMG"
  echo "Aviso: .dmg firmado pero sin notarizar (firma ad-hoc o falta APPLE_API_KEY_PATH)."
else
  sign "$DMG"
  RESULT=$(xcrun notarytool submit "$DMG" --key "$APPLE_API_KEY_PATH" --key-id "$APPLE_API_KEY_ID" \
    --issuer "$APPLE_API_ISSUER" --wait --output-format json)
  echo "$RESULT"
  if ! echo "$RESULT" | grep -q '"status" *: *"Accepted"'; then
    ID=$(echo "$RESULT" | sed -n 's/.*"id" *: *"\([^"]*\)".*/\1/p')
    [ -n "$ID" ] && xcrun notarytool log "$ID" --key "$APPLE_API_KEY_PATH" --key-id "$APPLE_API_KEY_ID" --issuer "$APPLE_API_ISSUER"
    echo "Error: Apple rechazó la notarización." >&2
    exit 1
  fi
  xcrun stapler staple "$DMG"
fi

echo "$DMG"

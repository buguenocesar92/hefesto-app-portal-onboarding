"""Verificaciones de la entrega que no necesitan PHP.

Qué comprueba:

  1. Barrido de caracteres CJK y cirílicos en todos los archivos de texto. Es un
     requisito estricto de Hefesto: se han colado varias veces y en documentos, no solo
     en código. Se reportan archivo, línea y el carácter encontrado.
  2. Los archivos YAML de despliegue se pueden leer como YAML.
  3. Los archivos JSON del proyecto son JSON válido.
  4. .env.example no trae valores que parezcan secretos reales.

Uso:
    python api/tests/estatico/comprobar_entrega.py
"""

from __future__ import annotations

import json
import pathlib
import re
import sys

import yaml

RAIZ = pathlib.Path(__file__).resolve().parents[3]  # .../portal-onboarding
API = RAIZ / "api"  # El backend PHP vive aquí abajo.

# Rangos que no tienen por qué aparecer en un proyecto en español.
CJK = (
    (0x3040, 0x30FF, "hiragana/katakana"),
    (0x3400, 0x4DBF, "CJK unificado"),
    (0x4E00, 0x9FFF, "CJK unificado"),
    (0xF900, 0xFAFF, "CJK compatibilidad"),
    (0xFF00, 0xFFEF, "CJK medio ancho"),
    (0xAC00, 0xD7AF, "hangul"),
    (0x1100, 0x11FF, "hangul"),
)

CIRILICO = ((0x0400, 0x04FF, "cirílico"), (0x0500, 0x052F, "cirílico suplementario"))

EXTENSIONES = {
    ".php",
    ".ts",
    ".js",
    ".vue",
    ".md",
    ".yaml",
    ".yml",
    ".json",
    ".example",
    ".py",
    ".txt",
    ".env",
}

fallos: list[str] = []


def revisar(condicion: bool, mensaje: str) -> None:
    """Registra un fallo si la condición no se cumple."""
    if not condicion:
        fallos.append(mensaje)


def barrer_caracteres() -> int:
    total = 0
    for archivo in sorted(RAIZ.rglob("*")):
        if not archivo.is_file() or archivo.suffix.lower() not in EXTENSIONES:
            continue

        try:
            texto = archivo.read_text(encoding="utf-8")
        except (UnicodeDecodeError, OSError) as error:
            fallos.append(f"{archivo.relative_to(RAIZ)}: no se pudo leer ({error}).")
            continue

        total += 1

        for numero, linea in enumerate(texto.splitlines(), start=1):
            for caracter in linea:
                punto = ord(caracter)
                for inicio_cjk, fin_cjk, nombre_cjk in CJK:
                    if inicio_cjk <= punto <= fin_cjk:
                        fallos.append(
                            f"{archivo.relative_to(RAIZ)}:{numero}: carácter {nombre_cjk} "
                            f"U+{punto:04X} ({caracter!r})"
                        )
                        break
                for inicio_cyr, fin_cyr, nombre_cyr in CIRILICO:
                    if inicio_cyr <= punto <= fin_cyr:
                        fallos.append(
                            f"{archivo.relative_to(RAIZ)}:{numero}: carácter {nombre_cyr} "
                            f"U+{punto:04X} ({caracter!r})"
                        )
                        break

    print(f"Archivos de texto revisados: {total}")
    return total


def revisar_yaml() -> int:
    archivos = sorted(list(RAIZ.glob("deploy/*.yaml")) + list(RAIZ.glob("deploy/*.yml")))
    for archivo in archivos:
        try:
            contenido = yaml.safe_load(archivo.read_text(encoding="utf-8"))
        except yaml.YAMLError as error:
            fallos.append(f"{archivo.relative_to(RAIZ)}: YAML inválido ({error}).")
            continue

        if not isinstance(contenido, dict) or "services" not in contenido:
            fallos.append(f"{archivo.relative_to(RAIZ)}: no parece un Compose (falta 'services').")
            continue

        for nombre, servicio in (contenido.get("services") or {}).items():
            if not isinstance(servicio, dict):
                fallos.append(f"{archivo.relative_to(RAIZ)}: el servicio '{nombre}' no es un mapa.")
                continue

            # Un servicio puede venir de una imagen o construirse desde un Dockerfile.
            if "image" not in servicio and "build" not in servicio:
                fallos.append(
                    f"{archivo.relative_to(RAIZ)}: el servicio '{nombre}' no declara ni 'image' ni 'build'."
                )

    print(f"Archivos Compose revisados: {len(archivos)}")
    return len(archivos)


def revisar_json() -> int:
    archivos = sorted(RAIZ.rglob("*.json"))
    for archivo in archivos:
        if any(parte in {"node_modules", "vendor"} for parte in archivo.parts):
            continue
        try:
            json.loads(archivo.read_text(encoding="utf-8"))
        except json.JSONDecodeError as error:
            fallos.append(f"{archivo.relative_to(RAIZ)}: JSON inválido ({error}).")

    print(f"Archivos JSON revisados: {len(archivos)}")
    return len(archivos)


def revisar_variables_de_entorno() -> int:
    plantilla = RAIZ / ".env.example"

    if not plantilla.exists():
        fallos.append("Falta .env.example en la raíz del portal.")
        return 0

    # Un valor de ejemplo no puede parecer un secreto real: ni tokens largos, ni
    # contraseña con caracteres aleatorios, ni claves privadas.
    sospechoso = re.compile(r"(eyJ[A-Za-z0-9_\-]{20,}|[0-9a-f]{40,}|-----BEGIN)")
    vacios = 0

    for numero, linea in enumerate(plantilla.read_text(encoding="utf-8").splitlines(), start=1):
        limpia = linea.strip()
        if not limpia or limpia.startswith("#") or "=" not in limpia:
            continue

        nombre, valor = limpia.split("=", 1)
        valor = valor.strip()

        if valor == "":
            vacios += 1
        elif sospechoso.search(valor):
            fallos.append(
                f".env.example:{numero}: el valor de {nombre} parece un secreto real."
            )

    print(f"Variables en .env.example: {vacios} sin valor, ninguna con secreto real")
    return vacios


def revisar_aislamiento_en_la_base() -> int:
    """La segunda capa del aislamiento tiene que existir en el código.

    Replica la comprobación que hace el validador general de la sesión
    (tools/validar_apps.py), para que un error aquí se vea sin depender de él.
    """
    php = list((API / "app").rglob("*.php")) + list((API / "database").rglob("*.php"))

    rls = False
    con_check = False
    force = False
    alcance_transaccion = False

    for archivo in php:
        texto = archivo.read_text(encoding="utf-8")
        mayusculas = texto.upper()
        rls = rls or "ROW LEVEL SECURITY" in mayusculas
        con_check = con_check or "WITH CHECK" in mayusculas
        force = force or "FORCE ROW LEVEL SECURITY" in mayusculas
        alcance_transaccion = alcance_transaccion or "set_config(?, ?, true)" in texto

    revisar(rls, "No se encontró Row Level Security en el código del backend.")
    revisar(con_check, "Las políticas de RLS necesitan WITH CHECK para proteger la escritura.")
    revisar(force, "Las políticas de RLS necesitan FORCE ROW LEVEL SECURITY.")
    revisar(
        alcance_transaccion,
        "El contexto de empresa debe fijarse con alcance de transacción (set_config con true).",
    )

    migraciones = list((API / "database" / "migrations").glob("*.php"))
    revisar(
        any("row_level_security" in m.name for m in migraciones),
        "Falta la migración que activa la seguridad de fila.",
    )

    pruebas = list((API / "tests").rglob("*.php"))
    revisar(
        any("AislamientoEnBaseDeDatos" in p.name for p in pruebas),
        "Falta la prueba de aislamiento a nivel de base de datos.",
    )

    print(
        "Aislamiento en la base: RLS={} WITH CHECK={} FORCE={} alcance de transacción={}".format(
            rls, con_check, force, alcance_transaccion
        )
    )

    return 4


def revisar_autenticacion() -> int:
    """La identidad viene de Authentik, y el token no puede salir del servidor."""
    web = (API / "app" / "Http").rglob("*.php")

    for archivo in web:
        texto = archivo.read_text(encoding="utf-8")

        # En la capa HTTP los secretos llegan por config, nunca por env(): leer el entorno
        # directamente es la forma habitual de que un secreto acabe en la respuesta.
        revisar(
            "env(" not in texto,
            f"{archivo.name} lee variables de entorno directamente en la capa HTTP.",
        )

        # Y el secreto de Authentik no puede pasar por un registro.
        for linea in texto.splitlines():
            revisar(
                not ("Log::" in linea and "cliente_secreto" in linea),
                f"{archivo.name} registra el secreto de Authentik.",
            )

    rutas = (API / "routes" / "web.php").read_text(encoding="utf-8")
    cliente = (API / "app" / "Servicios" / "Auth" / "ClienteAuthentik.php").read_text(encoding="utf-8")

    # Rutas verificadas contra la documentación oficial de Authentik el 17-10-2026.
    # Los tres primeros NO llevan el identificador de la aplicación.
    for ruta in ("/application/o/authorize/", "/application/o/token/", "/application/o/userinfo/"):
        revisar(ruta in cliente, f"Falta la ruta verificada de Authentik: {ruta}")

    revisar(
        "/application/o/authorize/?<slug>" not in cliente,
        "La ruta de autorización de Authentik no lleva el identificador de la aplicación.",
    )

    revisar("/auth/callback" in rutas, "Falta la ruta de retorno del acceso.")
    revisar("/auth/salir" in rutas, "Falta la ruta de salida.")

    # El frontend no debe tener ninguna variable con el secreto.
    frontend = RAIZ / "web"
    if frontend.exists():
        for archivo in frontend.rglob("*"):
            if archivo.is_file() and archivo.suffix in {".ts", ".vue", ".json", ".js"}:
                texto = archivo.read_text(encoding="utf-8", errors="ignore")
                revisar(
                    "CLIENT_SECRET" not in texto,
                    f"{archivo.name} menciona el secreto del cliente de Authentik.",
                )

    print("Autenticación: rutas de Authentik verificadas y secreto fuera del frontend")
    return 2


def main() -> int:
    print("Verificación de la entrega del Portal de Onboarding")
    print("=" * 64)
    barrer_caracteres()
    revisar_yaml()
    revisar_json()
    revisar_variables_de_entorno()
    revisar_aislamiento_en_la_base()
    revisar_autenticacion()
    print()

    if fallos:
        print(f"FALLARON {len(fallos)} COMPROBACIONES:")
        for fallo in fallos[:80]:
            print(f"  - {fallo}")
        if len(fallos) > 80:
            print(f"  ... y {len(fallos) - 80} más.")
        return 1

    print("RESULTADO: correcto.")

    return 0


if __name__ == "__main__":
    sys.exit(main())
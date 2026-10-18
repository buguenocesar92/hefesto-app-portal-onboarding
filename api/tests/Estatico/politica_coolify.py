"""Auditoría estática de la política de Coolify.

Por qué existe una comprobación en Python y no solo en PHP: en el entorno donde se
construyó este portal no había PHP ni Composer, así que la suite de PHP no se pudo
ejecutar. Este archivo sí se ejecuta, y lee el código fuente para comprobar las mismas
cosas que la suite de PHP, sin necesitar la base de datos ni la red.

Qué comprueba, en orden de importancia:

  1. Que ninguna operación permitida use un método destructivo.
  2. Que ninguna operación permitida caiga en la lista de prohibidas.
  3. Que el cliente de Coolify no tenga ningún método público que reciba una ruta, un
     método o un cuerpo arbitrario. Si existiera, el portal sería un proxy.
  4. Que no aparezca una llamada HTTP con verbo prohibido en todo el código.
  5. Que la credencial de Coolify no se lea fuera del cliente y la configuración.

Uso:
    python api/tests/estatico/politica_coolify.py
"""

from __future__ import annotations

import pathlib
import re
import sys

RAIZ = pathlib.Path(__file__).resolve().parents[2]  # .../portal-onboarding/api
ENUMS = RAIZ / "app" / "Enums"
CLIENTE = RAIZ / "app" / "Servicios" / "Coolify" / "ClienteCoolify.php"

# Verbos que este portal jamás debe emitir hacia la API de Coolify.
VERBOS_PROHIBIDOS = ("DELETE", "PUT", "PATCH")

# Fragmentos de ruta que delatan una operación fuera de la política.
FRAGMENTOS_PELIGROSOS = (
    "delete",
    "destroy",
    "remove",
    "restart",
    "stop",
    "kill",
    "exec",
    "cancel",
    "rollback",
    "migrate",
    "clone",
    "logs",
    "envs",
    "members",
    "settings",
    "private-key",
)

fallos: list[str] = []
comprobaciones = 0


def revisar(condicion: bool, mensaje: str) -> None:
    global comprobaciones
    comprobaciones += 1
    if not condicion:
        fallos.append(mensaje)


def leer(ruta: pathlib.Path) -> str:
    return ruta.read_text(encoding="utf-8")


def brazo_de_match(texto: str, metodo: str) -> dict[str, str]:
    """Lee el cuerpo de un método del enum que usa brazos agrupados.

    El enum agrupa casos así:

        return match ($this) {
            self::Salud,
            self::EquipoAutenticado,
            ...
            => 'GET',

    Así que no basta con buscar 'self::X => valor': hay que partir por el operador y
    atribuir a cada brazo los nombres que aparecen ANTES de él.
    """
    inicio = texto.find(f"function {metodo}(")
    if inicio == -1:
        return {}

    cuerpo = texto[inicio:]
    fin = cuerpo.find("\n    }")
    cuerpo = cuerpo[:fin] if fin != -1 else cuerpo

    if "match (" in cuerpo:
        cuerpo = cuerpo[cuerpo.find("match (") :]

    tabla: dict[str, str] = {}
    brazos = cuerpo.split("=>")

    for indice, fragmento in enumerate(brazos[:-1]):
        nombres = re.findall(r"self::(\w+)", fragmento)
        valor = re.match(r"\s*'([^']+)'", brazos[indice + 1])

        if valor is None:
            continue

        for nombre in nombres:
            tabla.setdefault(nombre, valor.group(1))

    return tabla


def casos_y_metodos() -> list[tuple[str, str, str]]:
    texto = leer(ENUMS / "OperacionCoolify.php")
    claves = re.findall(r"case\s+(\w+)\s*=\s*'([\w-]+)'\s*;", texto)
    metodos = brazo_de_match(texto, "metodo")
    rutas = brazo_de_match(texto, "ruta")
    return [(clave, metodos.get(nombre, "?"), rutas.get(nombre, "?")) for nombre, clave in claves]


def operaciones_negadas() -> list[tuple[str, str]]:
    texto = leer(ENUMS / "OperacionesProhibidas.php")
    return re.findall(r"'metodo'\s*=>\s*'([A-Z]+)'[^}]*?'ruta'\s*=>\s*'([^']+)'", texto, re.S)


def main() -> int:
    permitidas = casos_y_metodos()
    revisar(len(permitidas) >= 7, f"La lista permitida tiene solo {len(permitidas)} operaciones declaradas.")

    for clave, metodo, ruta in permitidas:
        revisar(
            metodo in ("GET", "POST"),
            f"La operación permitida '{clave}' usa el método {metodo}.",
        )
        revisar(
            ruta.startswith("/"),
            f"La operación permitida '{clave}' tiene una ruta rara: {ruta}",
        )
        bajo = ruta.lower()
        for fragmento in FRAGMENTOS_PELIGROSOS:
            revisar(
                fragmento not in bajo,
                f"La operación permitida '{clave}' contiene el fragmento prohibido '{fragmento}'.",
            )

    negadas = operaciones_negadas()
    revisar(len(negadas) >= 10, f"Solo hay {len(negadas)} operaciones declaradas como prohibidas.")

    metodos_negados = {metodo for metodo, _ in negadas}
    for verbo in VERBOS_PROHIBIDOS:
        revisar(verbo in metodos_negados, f"El verbo {verbo} no aparece en la lista de prohibidas.")

    # 3. El cliente no puede recibir una ruta arbitraria.
    texto_cliente = leer(CLIENTE)
    firmas = re.findall(r"public function (\w+)\(([^)]*)\)", texto_cliente, re.S)
    for nombre, argumentos in firmas:
        limpio = argumentos.replace("\n", " ")
        sospechoso = re.search(r"\$(url|ruta|ruta_base|metodo|endpoint|cuerpo_arbitrario)\b", limpio)
        revisar(
            sospechoso is None,
            f"ClienteCoolify::{nombre} recibe un parámetro que parece una ruta arbitraria.",
        )

    revisar(
        "OperacionCoolify $operacion" in texto_cliente,
        "El cliente no recibe el enum de operación: eso abriría la puerta a un proxy.",
    )
    revisar(
        "OperacionesProhibidas::motivoDelRechazo" in texto_cliente,
        "El cliente no consulta la lista de prohibidas antes de salir.",
    )

    # 4. Ningún verbo prohibido en llamadas HTTP dentro del backend. El propio archivo
    #    de prohibiciones queda fuera: su trabajo es nombrar esos verbos como datos.
    for archivo in (RAIZ / "app").rglob("*.php"):
        if archivo.name == "OperacionesProhibidas.php":
            continue
        contenido = leer(archivo)
        for verbo in VERBOS_PROHIBIDOS:
            revisar(
                f"'{verbo}'" not in contenido and f'"{verbo}"' not in contenido,
                f"{archivo.name} contiene una llamada con el verbo {verbo}.",
            )

    # 5. La credencial solo se lee en el cliente y en la configuración.
    lecturas = []
    for archivo in (RAIZ / "app").rglob("*.php"):
        contenido = leer(archivo)
        if "config('coolify.token')" in contenido or "config(\"coolify.token\")" in contenido:
            lecturas.append(archivo.name)

    revisar(
        set(lecturas) <= {"TokenCoolify.php"},
        f"Se lee la credencial de Coolify fuera del cliente: {lecturas}",
    )

    # El token tampoco puede aparecer en los recursos que se devuelven al navegador.
    for archivo in (RAIZ / "app" / "Http").rglob("*.php"):
        contenido = leer(archivo).lower()
        revisar(
            "coolify.token" not in contenido,
            f"{archivo.name} menciona la credencial de Coolify.",
        )

    print("Auditoría estática de la política de Coolify")
    print("=" * 60)
    print(f"Operaciones permitidas: {len(permitidas)}")
    for clave, metodo, ruta in permitidas:
        print(f"  {metodo:<5} {ruta:<26} {clave}")
    print(f"Operaciones declaradas como prohibidas: {len(negadas)}")
    print(f"Comprobaciones ejecutadas: {comprobaciones}")
    print()

    if fallos:
        print(f"FALLARON {len(fallos)} COMPROBACIONES:")
        for fallo in fallos:
            print(f"  - {fallo}")

        return 1

    print("RESULTADO: correcto. Ninguna operación destructiva está expuesta.")

    return 0


if __name__ == "__main__":
    sys.exit(main())
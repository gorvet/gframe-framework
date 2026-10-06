"""Validate optional skill metadata using a real YAML parser; never load framework code."""
import argparse
import json
from pathlib import Path
import re
import sys

try:
    import yaml
except ImportError:
    raise SystemExit("Se necesita PyYAML para esta comprobación; el verificador PHP no lo requiere.")


class UniqueLoader(yaml.SafeLoader):
    pass


def unique_mapping(loader, node, deep=False):
    loader.flatten_mapping(node)
    result = {}
    for key_node, value_node in node.value:
        key = loader.construct_object(key_node, deep=deep)
        if not isinstance(key, str) or key in result:
            raise ValueError("Claves YAML duplicadas o no textuales.")
        result[key] = loader.construct_object(value_node, deep=deep)
    return result


UniqueLoader.add_constructor(yaml.resolver.BaseResolver.DEFAULT_MAPPING_TAG, unique_mapping)


def validate(directory):
    errors = []
    try:
        data = yaml.load((directory / "agents/openai.yaml").read_text(encoding="utf-8"), Loader=UniqueLoader)
        if not isinstance(data, dict):
            raise ValueError("La metadata debe ser un mapa YAML; {} es válido.")
        for section in ("interface", "policy", "dependencies"):
            if section in data and not isinstance(data[section], dict):
                errors.append(f"{section} debe ser un mapa.")
        interface = data.get("interface", {})
        if isinstance(interface, dict):
            for key in ("display_name", "short_description", "default_prompt", "icon_small", "icon_large", "brand_color"):
                if key in interface and not isinstance(interface[key], str):
                    errors.append(f"interface.{key} debe ser texto.")
            short = interface.get("short_description")
            if isinstance(short, str) and not 25 <= len(short) <= 64:
                errors.append("short_description debe tener entre 25 y 64 caracteres.")
            prompt = interface.get("default_prompt")
            if isinstance(prompt, str) and not re.search(r"\$" + re.escape(directory.name) + r"(?![A-Za-z0-9_-])", prompt):
                errors.append("default_prompt debe mencionar la skill mediante $nombre.")
            color = interface.get("brand_color")
            if isinstance(color, str):
                if not re.fullmatch(r"#[0-9a-fA-F]{6}", color):
                    errors.append("brand_color debe ser un color hexadecimal de seis dígitos.")
            for key in ("icon_small", "icon_large"):
                path = interface.get(key)
                if isinstance(path, str):
                    resolved = (directory / path).resolve()
                    if not path.startswith("./") or not resolved.is_relative_to(directory.resolve()) or not resolved.is_file():
                        errors.append(f"interface.{key} debe resolver a un archivo dentro de la skill con ruta ./.")
        policy = data.get("policy", {})
        if isinstance(policy, dict) and "allow_implicit_invocation" in policy and type(policy["allow_implicit_invocation"]) is not bool:
            errors.append("allow_implicit_invocation debe ser booleano, no texto.")
        dependencies = data.get("dependencies", {})
        if isinstance(dependencies, dict) and "tools" in dependencies:
            tools = dependencies["tools"]
            if not isinstance(tools, list):
                errors.append("dependencies.tools debe ser una lista.")
            else:
                for tool in tools:
                    if not isinstance(tool, dict) or tool.get("type") != "mcp" or not isinstance(tool.get("value"), str) or not tool["value"]:
                        errors.append("Cada herramienta debe declarar type: mcp y un value textual no vacío.")
        # Unknown fields are preserved for forward compatibility, not interpreted as validated contracts.
    except (OSError, UnicodeError, yaml.YAMLError, ValueError) as error:
        errors.append(str(error))
    return errors


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parents[1] / "skills")
    root = parser.parse_args().root
    directories = sorted(path for path in root.glob("gframe-*") if path.is_dir())
    if not directories:
        print("No se encontraron skills de GFrame.", file=sys.stderr)
        return 1
    errors = {path.name: failures for path in directories if (failures := validate(path))}
    if errors:
        print(json.dumps(errors, ensure_ascii=False, indent=2), file=sys.stderr)
        return 1
    print(f"Metadata válida: {len(directories)} skills.")
    return 0


if __name__ == "__main__":
    sys.exit(main())

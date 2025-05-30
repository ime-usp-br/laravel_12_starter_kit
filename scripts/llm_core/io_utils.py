# -*- coding: utf-8 -*-
"""
LLM Core Input/Output Utilities Module.
"""
import sys
import re
import datetime
import json  # Adicionado para update_manifest_file
import traceback  # Adicionado para update_manifest_file
from pathlib import Path
from typing import Tuple, Optional, Dict, Any, List, Set

from . import config as core_config  # Import the core config


def save_llm_response(
    task_name: str,
    response_content: str,
    output_dir_base_override: Optional[Path] = None,
) -> None:
    """Saves the LLM's final response to a timestamped file within a task-specific directory."""

    current_output_dir_base = (
        output_dir_base_override
        if output_dir_base_override is not None
        else core_config.OUTPUT_DIR_BASE
    )

    try:
        task_output_dir = current_output_dir_base / task_name
        task_output_dir.mkdir(parents=True, exist_ok=True)
        timestamp_str = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
        output_filename = f"{timestamp_str}.txt"
        output_filepath = task_output_dir / output_filename
        output_filepath.write_text(response_content, encoding="utf-8")
        print(
            f"  Resposta LLM salva em: {output_filepath.relative_to(core_config.PROJECT_ROOT)}"
        )
    except OSError as e:
        print(
            f"Erro ao criar diretório de saída {task_output_dir}: {e}", file=sys.stderr
        )
    except Exception as e:
        print(f"Erro ao salvar resposta LLM: {e}", file=sys.stderr)


def confirm_step(prompt_message: str) -> Tuple[str, Optional[str]]:
    """
    Asks the user for confirmation (Y/n/q).
    If 'n' is chosen, prompts for an observation/feedback.
    Returns a tuple: (user_choice, observation_or_none).
    """
    while True:
        response = (
            input(f"{prompt_message} (Y/n/q - Sim/Não+Feedback/Sair) [Y]: ")
            .lower()
            .strip()
        )
        if response in ["y", "yes", "s", "sim", ""]:
            return "y", None
        elif response in ["n", "no", "nao"]:
            observation = input(
                "Por favor, insira sua observação/regra para melhorar o passo anterior: "
            ).strip()
            if not observation:
                print(
                    "Observação não pode ser vazia se você deseja refazer. Tente novamente ou escolha 'y'/'q'."
                )
                continue
            return "n", observation
        elif response in ["q", "quit", "sair"]:
            return "q", None
        else:
            print(
                "Entrada inválida. Por favor, digite Y (ou S), n (ou nao), ou q (ou sair)."
            )


def prompt_user_for_missing_essential_file(relative_path_str: str) -> bool:
    """
    Pergunta ao usuário se deseja continuar sem um arquivo essencial ausente ou abortar.
    Retorna True para continuar, False para abortar.
    AC4.1b
    """
    prompt_text = f"  AVISO (AC4.1a): Arquivo essencial '{relative_path_str}' não encontrado no disco. Deseja (C)ontinuar sem este arquivo ou (A)bortar a tarefa? [A]: "
    while True:
        # AC4.1a: Log do aviso (o prompt em si já informa o usuário)
        print(
            prompt_text, end=""
        )  # Imprime o prompt sem newline para input na mesma linha
        choice = input().strip().lower()
        if choice in ["c", "continuar"]:
            return True
        elif choice in ["a", "abortar", ""]:  # Padrão é abortar
            return False
        else:
            print(
                "  Entrada inválida. Por favor, digite 'C' para continuar ou 'A' para abortar."
            )


def parse_pr_content(llm_output: str) -> Tuple[Optional[str], Optional[str]]:
    """Parses the LLM output for create-pr task to extract PR title and body."""
    title: Optional[str] = None
    body: Optional[str] = None

    try:
        title_start_delimiter = core_config.PR_CONTENT_DELIMITER_TITLE
        title_start_index = llm_output.index(title_start_delimiter) + len(
            title_start_delimiter
        )
        body_start_delimiter = core_config.PR_CONTENT_DELIMITER_BODY
        body_start_index = llm_output.index(body_start_delimiter, title_start_index)
        title = llm_output[title_start_index:body_start_index].strip()
        body_content_start_index = body_start_index + len(body_start_delimiter)
        body = llm_output[body_content_start_index:].strip()
    except ValueError:
        print(
            f"Erro: Não foi possível parsear a saída da LLM para o PR. Delimitadores '{core_config.PR_CONTENT_DELIMITER_TITLE}' ou '{core_config.PR_CONTENT_DELIMITER_BODY}' não encontrados ou formato incorreto.",
            file=sys.stderr,
        )
        return None, None
    return title, body


def parse_summaries_from_response(llm_response: str) -> Dict[str, str]:
    """Parses the LLM response to extract individual file summaries."""
    summaries: Dict[str, str] = {}
    pattern = re.compile(
        rf"^{re.escape(core_config.SUMMARY_CONTENT_DELIMITER_START)}(.*?){re.escape(' ---')}\n(.*?)\n^{re.escape(core_config.SUMMARY_CONTENT_DELIMITER_END)}\1{re.escape(' ---')}",
        re.MULTILINE | re.DOTALL,
    )
    matches = pattern.findall(llm_response)
    for filepath, summary in matches:
        summaries[filepath.strip()] = summary.strip()
    return summaries


def find_documentation_files(base_dir: Path) -> List[Path]:
    """
    Finds potential documentation files (.md) in the project root and docs/ directory.
    Returns a list of paths relative to the base_dir.
    """
    print(f"  Escaneando por arquivos de documentação em: {base_dir}")
    found_paths: Set[Path] = set()

    for filename in ["README.md", "CHANGELOG.md"]:
        filepath = base_dir / filename
        if filepath.is_file():
            try:
                found_paths.add(filepath.relative_to(base_dir))
            except ValueError:  # pragma: no cover
                print(
                    f"    Aviso: {filepath} não está sob {base_dir}.", file=sys.stderr
                )

    docs_dir = base_dir / "docs"
    if docs_dir.is_dir():
        for filepath in docs_dir.rglob("*.md"):
            if filepath.is_file():
                try:
                    found_paths.add(filepath.relative_to(base_dir))
                except ValueError:  # pragma: no cover
                    print(
                        f"    Aviso: {filepath} não está sob {base_dir}.",
                        file=sys.stderr,
                    )

    sorted_paths = sorted(list(found_paths), key=lambda p: str(p))
    print(f"  Encontrados {len(sorted_paths)} arquivos de documentação únicos.")
    return sorted_paths


def prompt_user_to_select_doc(doc_files: List[Path]) -> Optional[Path]:
    """
    Displays a numbered list of documentation files and prompts the user for selection.
    doc_files should be a list of paths relative to the project root.
    Returns the selected Path object (still relative to project root) or None if user quits.
    """
    if not doc_files:
        print("  Nenhum arquivo de documentação encontrado para seleção.")
        return None

    print(
        "\nArquivos de documentação encontrados. Por favor, escolha um para atualizar:"
    )
    for i, filepath_relative in enumerate(doc_files):
        print(f"  {i + 1}: {filepath_relative.as_posix()}")
    print("  q: Sair")

    while True:
        choice = (
            input("Digite o número do arquivo para atualizar (ou 'q' para sair): ")
            .strip()
            .lower()
        )
        if choice == "q":
            return None
        try:
            index = int(choice) - 1
            if 0 <= index < len(doc_files):
                selected_path_relative = doc_files[index]
                print(f"  Você selecionou: {selected_path_relative.as_posix()}")
                return selected_path_relative
            else:
                print("  Número inválido. Por favor, tente novamente.")
        except ValueError:
            print("  Entrada inválida. Por favor, digite um número ou 'q'.")


def update_manifest_file(manifest_path: Path, manifest_data: Dict[str, Any]) -> bool:
    """Writes the updated manifest data back to the JSON file."""
    try:
        with open(manifest_path, "w", encoding="utf-8") as f:
            json.dump(manifest_data, f, indent=4, ensure_ascii=False)
        print(f"  Arquivo de manifesto '{manifest_path.name}' atualizado com sucesso.")
        return True
    except Exception as e:
        print(
            f"Erro ao salvar arquivo de manifesto atualizado '{manifest_path.name}': {e}",
            file=sys.stderr,
        )
        traceback.print_exc(file=sys.stderr)
        return False

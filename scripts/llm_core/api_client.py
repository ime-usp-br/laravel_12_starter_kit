# -*- coding: utf-8 -*-
"""
LLM Core API Client Module.
"""
import os
import sys
import time
import traceback
import concurrent.futures
from typing import List, Optional, Dict, Any, Union, Callable

from google import genai
from google.genai import types
from google.genai import errors as google_genai_errors
from google.api_core import exceptions as google_api_core_exceptions
from dotenv import load_dotenv  # Mantenha a importação de dotenv
from tqdm import tqdm

from . import config as core_config

# Module-level globals for API client and state
GEMINI_API_KEYS_LIST: List[str] = []
current_api_key_index: int = 0
genai_client: Optional[genai.Client] = None
api_executor: Optional[concurrent.futures.ThreadPoolExecutor] = None
api_key_loaded_successfully: bool = False
gemini_initialized_successfully: bool = False


def load_api_keys(verbose: bool = False) -> bool:
    """Loads API keys from .env file or environment variables."""
    global GEMINI_API_KEYS_LIST, api_key_loaded_successfully, current_api_key_index

    if api_key_loaded_successfully:
        return True

    # Prioriza variáveis de ambiente do sistema
    api_key_string = os.getenv("GEMINI_API_KEY")

    # Se não encontrar no ambiente, tenta carregar do .env
    if not api_key_string:
        dotenv_path = core_config.PROJECT_ROOT / ".env"
        if dotenv_path.is_file():
            if verbose:
                print(
                    f"  Tentando carregar variáveis de ambiente de: {dotenv_path.relative_to(core_config.PROJECT_ROOT)}"
                )
            # load_dotenv pode ser chamado aqui, mas a fixture já mocka a nível de módulo
            # Se o .env não definir GEMINI_API_KEY, api_key_string continuará None.
            # A fixture mock_load_dotenv_globally garante que load_dotenv não faça I/O real nos testes.
            # A chamada real de load_dotenv é feita aqui se o .env existir.
            load_dotenv(dotenv_path=dotenv_path, verbose=verbose, override=True)
            api_key_string = os.getenv(
                "GEMINI_API_KEY"
            )  # Tenta ler novamente após carregar .env
        elif verbose:
            print(
                f"  Arquivo .env não encontrado em {dotenv_path}. Usando apenas variáveis de ambiente do sistema (se houver)."
            )

    if not api_key_string:
        if verbose:  # Adiciona verbose aqui para o caso de falha
            print(
                "Erro: Variável de ambiente GEMINI_API_KEY não encontrada no sistema nem no arquivo .env.",
                file=sys.stderr,
            )
        api_key_loaded_successfully = False
        return False

    GEMINI_API_KEYS_LIST = [
        key.strip() for key in api_key_string.split("|") if key.strip()
    ]

    if not GEMINI_API_KEYS_LIST:
        print(
            "Erro: Formato da GEMINI_API_KEY inválido ou vazio. Use '|' para separar múltiplas chaves.",
            file=sys.stderr,
        )
        api_key_loaded_successfully = False
        return False

    current_api_key_index = 0
    api_key_loaded_successfully = True
    if verbose:
        print(f"  {len(GEMINI_API_KEYS_LIST)} Chave(s) de API GEMINI carregadas.")
    return True


def initialize_genai_client(verbose: bool = False) -> bool:
    """Initializes or reinitializes the global genai_client using the current API key."""
    global genai_client, gemini_initialized_successfully, GEMINI_API_KEYS_LIST, current_api_key_index

    if not api_key_loaded_successfully:
        if not load_api_keys(verbose):
            return False

    if not GEMINI_API_KEYS_LIST or not (
        0 <= current_api_key_index < len(GEMINI_API_KEYS_LIST)
    ):
        if verbose:
            print(
                "  Aviso: Chaves de API não carregadas ou índice inválido. Impossível inicializar Gemini."
            )
        gemini_initialized_successfully = False
        return False

    active_key = GEMINI_API_KEYS_LIST[current_api_key_index]
    try:
        if verbose:
            print(
                f"  Inicializando Google GenAI Client com Key Index {current_api_key_index}..."
            )
        genai_client = genai.Client(api_key=active_key)
        if verbose:
            print("  Google GenAI Client inicializado com sucesso.")
        gemini_initialized_successfully = True
        return True
    except Exception as e:
        print(
            f"Erro ao inicializar Google GenAI Client com Key Index {current_api_key_index}: {e}",
            file=sys.stderr,
        )
        if verbose:
            traceback.print_exc(file=sys.stderr)
        gemini_initialized_successfully = False
        return False


def startup_api_resources(verbose: bool = False) -> bool:
    """Initializes API keys, client, and executor."""
    global api_executor
    if not api_key_loaded_successfully:
        if not load_api_keys(verbose):
            return False
    if not gemini_initialized_successfully:
        if not initialize_genai_client(verbose):
            return False
    if not api_executor:
        api_executor = concurrent.futures.ThreadPoolExecutor(max_workers=1)
        if verbose:
            print("  API ThreadPoolExecutor inicializado.")
    return True


def shutdown_api_resources(verbose: bool = False):
    """Shuts down the API ThreadPoolExecutor."""
    global api_executor
    if api_executor:
        if verbose:
            print("  Encerrando API ThreadPoolExecutor...")
        api_executor.shutdown(wait=False)
        api_executor = None
        if verbose:
            print("  API ThreadPoolExecutor encerrado.")


def rotate_api_key_and_reinitialize(verbose: bool = False) -> bool:
    """Rotates to the next API key and reinitializes the client."""
    global current_api_key_index, GEMINI_API_KEYS_LIST, gemini_initialized_successfully
    if not GEMINI_API_KEYS_LIST or len(GEMINI_API_KEYS_LIST) <= 1:
        if verbose:
            print(
                "  Aviso: Não é possível rotacionar (apenas uma ou nenhuma chave disponível).",
                file=sys.stderr,
            )
        return False

    start_index = current_api_key_index
    current_api_key_index = (current_api_key_index + 1) % len(GEMINI_API_KEYS_LIST)
    print(
        f"\n---> Rotacionando Chave de API para Índice {current_api_key_index} <---\n"
    )
    gemini_initialized_successfully = False

    if current_api_key_index == start_index:
        print(
            "Aviso: Ciclo completo por todas as chaves de API. Limites de taxa podem persistir.",
            file=sys.stderr,
        )

    return initialize_genai_client(verbose)


GenerateContentConfigType = Union[
    types.GenerationConfig, types.GenerateContentConfig, Dict[str, Any], None
]


def execute_gemini_call(
    model_name: str,
    contents: List[types.Part],
    config: Optional[GenerateContentConfigType] = None,
    sleep_on_retry: float = core_config.DEFAULT_RATE_LIMIT_SLEEP,
    timeout_seconds: int = core_config.DEFAULT_API_TIMEOUT_SECONDS,
    verbose: bool = False,
) -> str:
    """
    Executes a call to the Gemini API with provided model, contents, and config.
    Handles rate limiting with key rotation and timeouts.
    """
    global genai_client, api_executor

    if not gemini_initialized_successfully or not genai_client:
        raise RuntimeError(
            "GenAI client não inicializado. Chame startup_api_resources() primeiro."
        )
    if not api_executor:
        raise RuntimeError(
            "API Executor não inicializado. Chame startup_api_resources() primeiro."
        )

    initial_key_index = current_api_key_index
    keys_tried_in_this_call = {initial_key_index}

    while True:

        def _api_call_task() -> types.GenerateContentResponse:
            if not genai_client:
                raise RuntimeError("Gemini client tornou-se não inicializado na task.")

            api_config_obj: Optional[types.GenerateContentConfig] = None
            if isinstance(config, dict):
                api_config_obj = types.GenerateContentConfig(**config)
            elif isinstance(config, types.GenerateContentConfig):
                api_config_obj = config
            elif isinstance(config, types.GenerationConfig):
                api_config_obj = types.GenerateContentConfig(
                    candidate_count=config.candidate_count,
                    stop_sequences=config.stop_sequences,
                    max_output_tokens=config.max_output_tokens,
                    temperature=config.temperature,
                    top_p=config.top_p,
                    top_k=config.top_k,
                )

            try:
                if verbose:
                    print(
                        f"      -> Enviando para o modelo '{model_name}' com config: {api_config_obj}"
                    )
                return genai_client.models.generate_content(
                    model=model_name,
                    contents=contents,
                    config=api_config_obj,  # Corrected parameter name
                )
            except Exception as inner_e:
                if verbose:
                    print(
                        f"      -> Erro interno na task API ({type(inner_e).__name__}): {inner_e}",
                        file=sys.stderr,
                    )
                raise inner_e

        future = None
        try:
            if verbose:
                print(
                    f"        -> Tentando chamada API com Key Index {current_api_key_index}, Timeout {timeout_seconds}s"
                )

            future = api_executor.submit(_api_call_task)
            response = future.result(timeout=timeout_seconds)

            if response.prompt_feedback and response.prompt_feedback.block_reason:
                block_reason_name = types.BlockedReason(
                    response.prompt_feedback.block_reason
                ).name
                print(
                    f"  Aviso: Prompt bloqueado devido a {block_reason_name}.",
                    file=sys.stderr,
                )
                raise RuntimeError(f"Prompt bloqueado: {block_reason_name}")

            if response.candidates:
                for candidate in response.candidates:
                    if hasattr(
                        candidate, "finish_reason"
                    ) and candidate.finish_reason not in (
                        types.FinishReason.STOP,
                        types.FinishReason.FINISH_REASON_UNSPECIFIED,
                        types.FinishReason.MAX_TOKENS,
                    ):
                        reason_name = types.FinishReason(candidate.finish_reason).name
                        print(
                            f"  Aviso: Candidato finalizado com razão: {reason_name}",
                            file=sys.stderr,
                        )
                        if (
                            hasattr(candidate, "finish_message")
                            and candidate.finish_message
                        ):
                            print(
                                f"  Mensagem de finalização: {candidate.finish_message}",
                                file=sys.stderr,
                            )

            try:
                return response.text
            except (ValueError, AttributeError) as e:
                print(
                    f"Aviso: Não foi possível extrair texto da resposta. Resposta: {response}. Erro: {e}",
                    file=sys.stderr,
                )
                return ""

        except concurrent.futures.TimeoutError:
            print(
                f"  Chamada API excedeu o tempo limite de {timeout_seconds}s. Erro para a tarefa atual.",
                file=sys.stderr,
            )
            raise TimeoutError
        except (
            google_api_core_exceptions.ResourceExhausted,
            google_genai_errors.ServerError,
            google_api_core_exceptions.DeadlineExceeded,
        ) as e:
            print(
                f"  Erro de API ({type(e).__name__}) com Key Index {current_api_key_index}. Aguardando {sleep_on_retry:.1f}s e rotacionando chave...",
                file=sys.stderr,
            )
            if verbose:
                print(f"    Detalhes do erro: {e}")

            for _ in tqdm(
                range(int(sleep_on_retry * 10)),
                desc="Aguardando para nova tentativa/rotação de cota",
                unit="ds",
                leave=False,
                bar_format="{l_bar}{bar}| {n_fmt}/{total_fmt}",
            ):
                time.sleep(0.1)

            if not rotate_api_key_and_reinitialize(verbose):
                print(
                    "Erro: Não foi possível rotacionar a chave de API. Relançando erro original.",
                    file=sys.stderr,
                )
                raise e

            if current_api_key_index in keys_tried_in_this_call:
                print(
                    f"Erro: Ciclo completo de chaves API. Limite/Erro persistente. Relançando erro original.",
                    file=sys.stderr,
                )
                raise e

            keys_tried_in_this_call.add(current_api_key_index)
            if verbose:
                print(
                    f"        -> Tentando novamente chamada API com nova Key Index {current_api_key_index}"
                )
            continue

        except google_genai_errors.APIError as e:
            print(
                f"  Erro de API GenAI ({type(e).__name__}) com Key Index {current_api_key_index}: {e}",
                file=sys.stderr,
            )
            if (
                hasattr(e, "response")
                and hasattr(e.response, "status_code")
                and e.response.status_code == 429
            ):  # Check for specific status code
                print(
                    f"  Erro 429 (Rate Limit) detectado. Aguardando {sleep_on_retry:.1f}s e rotacionando chave...",
                    file=sys.stderr,
                )
                if not rotate_api_key_and_reinitialize(verbose):
                    raise e
                if current_api_key_index in keys_tried_in_this_call:
                    raise e
                keys_tried_in_this_call.add(current_api_key_index)
                continue
            raise e

        except Exception as e:
            print(f"Erro inesperado durante a chamada API: {e}", file=sys.stderr)
            traceback.print_exc()
            raise

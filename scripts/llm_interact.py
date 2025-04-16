#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
llm_interact.py: Script to interact with the Google Gemini API using project context.

This script automates interactions with the Gemini LLM, using context files
generated by 'gerar_contexto_llm.sh' and meta-prompts to streamline
development tasks like generating commit messages, analyzing code, etc.
Includes user confirmation steps and allows providing feedback for retries (AC10/AC11).
Supports enabling Google Search as a tool (AC13).
Supports running the context generation script via a flag (AC14).
Includes abbreviations for flags (AC15).
Supports specifying target doc file for update-doc task (AC21).
Supports listing and selecting doc files if target is not specified for update-doc (AC22).
"""

import argparse
import os
import sys
import subprocess # Added for AC14
from pathlib import Path
import re
import google.genai as genai
from google.genai import types
from google.genai import errors # Import the error module
from dotenv import load_dotenv
import traceback # For debugging unexpected errors
from datetime import datetime # For timestamping output files (AC8)

# --- Configuration ---
# Assumes the script is in /scripts and templates in /project_templates/meta-prompts
BASE_DIR = Path(__file__).resolve().parent.parent
META_PROMPT_DIR = BASE_DIR / "project_templates/meta-prompts"
CONTEXT_DIR_BASE = BASE_DIR / "context_llm/code"
COMMON_CONTEXT_DIR = BASE_DIR / "context_llm/common" # Directory for common context files (AC7)
OUTPUT_DIR_BASE = BASE_DIR / "llm_outputs" # Directory for saving outputs, should be in .gitignore (AC8)
CONTEXT_GENERATION_SCRIPT = BASE_DIR / "gerar_contexto_llm.sh" # Path to context script (AC14)
TIMESTAMP_DIR_REGEX = r'^\d{8}_\d{6}$' # Regex to validate directory name format
# Gemini model to use (choose an appropriate model for tasks)
GEMINI_MODEL_GENERAL_TASKS = 'gemini-2.5-pro-preview-03-25' # Do not change
GEMINI_MODEL_RESOLVE = 'gemini-2.5-pro-preview-03-25' # Do not change
# Message to encourage web search (AC13 Observação Adicional)
WEB_SEARCH_ENCOURAGEMENT_PT = "\n\nPara garantir a melhor resposta possível, sinta-se à vontade para pesquisar na internet usando a ferramenta de busca disponível."

# --- Helper Functions ---

def find_available_tasks(prompt_dir: Path) -> dict[str, Path]:
    """
    Find available tasks (meta-prompts) in the specified directory.

    Args:
        prompt_dir: The Path to the directory containing meta-prompt files.

    Returns:
        A dictionary mapping task names to the Paths of the files.
        Returns an empty dictionary if the directory doesn't exist or contains no prompts.
    """
    tasks = {}
    if not prompt_dir.is_dir():
        print(f"Error: Meta-prompt directory not found: {prompt_dir}", file=sys.stderr)
        return tasks
    # Expected pattern: meta-prompt-task_name.txt
    for filepath in prompt_dir.glob("meta-prompt-*.txt"):
        if filepath.is_file():
            task_name = filepath.stem.replace("meta-prompt-", "")
            if task_name:
                tasks[task_name] = filepath
    return tasks

def find_latest_context_dir(context_base_dir: Path) -> Path | None:
    """
    Find the most recent context directory within the base directory.

    Args:
        context_base_dir: The Path to the base directory where context
                          directories (timestamped) are located.

    Returns:
        A Path object for the latest directory found, or None if
        no valid directory is found or the base directory doesn't exist.
    """
    if not context_base_dir.is_dir():
        print(f"Error: Context base directory not found: {context_base_dir}", file=sys.stderr)
        return None

    valid_context_dirs = []
    for item in context_base_dir.iterdir():
        # Ensure it's a directory and matches the timestamp format, excluding the common dir
        if item.is_dir() and re.match(TIMESTAMP_DIR_REGEX, item.name):
            valid_context_dirs.append(item)

    if not valid_context_dirs:
        print(f"Error: No valid timestamped context directories (YYYYMMDD_HHMMSS format) found in {context_base_dir}", file=sys.stderr)
        return None

    # Sort directories by name (timestamp) in descending order
    latest_context_dir = sorted(valid_context_dirs, reverse=True)[0]
    return latest_context_dir

def load_and_fill_template(template_path: Path, variables: dict) -> str:
    """
    Load a meta-prompt template and replace placeholders with provided variables.

    Args:
        template_path: The Path to the template file.
        variables: A dictionary where keys are variable names (without __)
                   and values are the data for substitution.

    Returns:
        The template content with variables substituted.
        Returns an empty string if the template cannot be read or an error occurs.
    """
    try:
        content = template_path.read_text(encoding='utf-8')
        # Helper function to handle substitution
        def replace_match(match):
            var_name = match.group(1)
            # Returns the variable value from the dictionary or an empty string if not found
            # Ensures the value is a string for substitution
            return str(variables.get(var_name, ''))

        # Regex to find placeholders like __VARIABLE_EXAMPLE__
        filled_content = re.sub(r'__([A-Z_]+)__', replace_match, content)
        return filled_content
    except FileNotFoundError:
        print(f"Error: Template file not found: {template_path}", file=sys.stderr)
        return ""
    except Exception as e:
        print(f"Error reading or processing template {template_path}: {e}", file=sys.stderr)
        return ""


def _load_files_from_dir(context_dir: Path, context_parts: list[types.Part]) -> None:
    """Helper function to load .txt, .json and .md files from a directory into context_parts."""
    file_patterns = ["*.txt", "*.json", "*.md"]
    loaded_count = 0
    if not context_dir or not context_dir.is_dir():
        print(f"    - Directory not found or invalid: {context_dir}", file=sys.stderr)
        return

    print(f"    Scanning directory: {context_dir.relative_to(BASE_DIR)}")
    for pattern in file_patterns:
        for filepath in context_dir.glob(pattern):
            if filepath.is_file():
                try:
                    # print(f"      - Reading {filepath.name}") # Verbose logging removed
                    content = filepath.read_text(encoding='utf-8')
                    # Add file name at the beginning of content for LLM origin tracking
                    # Use keyword argument 'text='
                    relative_path = filepath.relative_to(BASE_DIR)
                    context_parts.append(types.Part.from_text(text=f"--- START OF FILE {relative_path} ---\n{content}\n--- END OF FILE {relative_path} ---"))
                    loaded_count += 1
                except Exception as e:
                    print(f"      - Warning: Could not read file {filepath.name}: {e}", file=sys.stderr)
    if loaded_count == 0:
        print(f"      - No context files (.txt, .json, .md) found in this directory.")

def prepare_context_parts(primary_context_dir: Path, common_context_dir: Path | None = None) -> list[types.Part]:
    """
    List context files (.txt, .json, .md) from primary and optionally common directories,
    and prepare them as types.Part.

    Args:
        primary_context_dir: The Path to the primary (e.g., timestamped) context directory.
        common_context_dir: Optional Path to the common context directory.

    Returns:
        A list of types.Part objects representing the content of the files.
    """
    context_parts = []
    print("  Loading context files...")

    # Load from primary directory
    print("  Loading from primary context directory...")
    _load_files_from_dir(primary_context_dir, context_parts)

    # Load from common directory (AC7)
    if common_context_dir:
        print("\n  Loading from common context directory...")
        if common_context_dir.exists() and common_context_dir.is_dir():
             _load_files_from_dir(common_context_dir, context_parts)
        else:
             print(f"    - Common context directory not found or is not a directory: {common_context_dir}")

    print(f"\n  Total context files loaded: {len(context_parts)}.")
    return context_parts

def save_llm_response(task_name: str, response_content: str) -> None:
    """
    Saves the LLM's final response to a timestamped file within a task-specific directory.

    Args:
        task_name: The name of the task (e.g., 'resolve-ac', 'commit-mesage').
        response_content: The string content of the LLM's final response.
    """
    try:
        task_output_dir = OUTPUT_DIR_BASE / task_name
        task_output_dir.mkdir(parents=True, exist_ok=True) # Create dirs if they don't exist

        timestamp_str = datetime.now().strftime("%Y%m%d_%H%M%S")
        output_filename = f"{timestamp_str}.txt" # Or use a more specific extension if needed, e.g., .diff, .md
        output_filepath = task_output_dir / output_filename

        output_filepath.write_text(response_content, encoding='utf-8')
        print(f"  LLM Response saved to: {output_filepath.relative_to(BASE_DIR)}")

    except OSError as e:
        print(f"Error creating output directory {task_output_dir}: {e}", file=sys.stderr)
    except Exception as e:
        print(f"Error saving LLM response to file: {e}", file=sys.stderr)
        traceback.print_exc()

def parse_arguments(available_tasks: list[str]) -> argparse.Namespace:
    """
    Parse command-line arguments, including examples in the epilog.

    Args:
        available_tasks: A list of available task names.

    Returns:
        A Namespace object containing the parsed arguments.
    """
    script_name = Path(sys.argv[0]).name # Get script name for examples

    # --- Build epilog string with examples ---
    epilog_lines = ["\nExamples:"]
    sorted_tasks = sorted(available_tasks)

    for task_name in sorted_tasks:
        if task_name == "commit-mesage":
            example = f"  {script_name} {task_name} --issue 28 (ou -i 28)"
            epilog_lines.append(example)
        elif task_name == "resolve-ac":
            example = f"  {script_name} {task_name} --issue 28 --ac 5 --observation \"Ensure API key from .env\" (ou -i 28 -a 5 -o \"...\") [-w] [-g]"
            epilog_lines.append(example)
        elif task_name == "analise-ac":
            example = f"  {script_name} {task_name} --issue 28 --ac 4 (ou -i 28 -a 4)"
            epilog_lines.append(example)
        elif task_name == "update-doc": # AC21: Added example for update-doc
            example = f"  {script_name} {task_name} --issue 28 --doc-file docs/README.md (ou -i 28 -d docs/README.md) [-g]" # AC22: User may omit -d
            epilog_lines.append(example)
        else:
            # Generic example for other tasks
            example = f"  {script_name} {task_name} [-i ISSUE] [-a AC] [-o OBSERVATION] [-w] [-g]"
            epilog_lines.append(example)

    epilog_text = "\n".join(epilog_lines)

    # --- Create parser ---
    parser = argparse.ArgumentParser(
        description="Interact with Google Gemini using project context and meta-prompts.",
        epilog=epilog_text, # Add examples to the help message end
        formatter_class=argparse.RawDescriptionHelpFormatter # Preserve formatting
    )

    task_choices_str = ", ".join(sorted_tasks)
    parser.add_argument(
        "task",
        choices=sorted_tasks,
        help=(f"The task to perform, based on available meta-prompts in "
              f"'{META_PROMPT_DIR.relative_to(BASE_DIR)}'.\nAvailable tasks: {task_choices_str}"),
        metavar="TASK"
    )
    # --- Arguments for meta-prompt variables (AC15: Added short flags) ---
    parser.add_argument("-i", "--issue", help="Issue number (e.g., 28). Fills __NUMERO_DA_ISSUE__.")
    parser.add_argument("-a", "--ac", help="Acceptance Criteria number (e.g., 3). Fills __NUMERO_DO_AC__.")
    parser.add_argument("-o", "--observation", help="Additional observation/instruction for the task. Fills __OBSERVACAO_ADICIONAL__.", default="")
    # --- AC21: Add --doc-file argument (Optional for AC22) ---
    parser.add_argument("-d", "--doc-file", help="Target documentation file path for 'update-doc' task. If omitted, you will be prompted to choose. Fills __ARQUIVO_DOC_ALVO__.")
    # --- End AC21 ---
    parser.add_argument(
        "-w", "--web-search",
        action="store_true", # Makes it a boolean flag
        help="Enable Google Search as a tool for the Gemini model (AC13)."
    )
    parser.add_argument( # AC14
        "-g", "--generate-context",
        action="store_true",
        help="Run the context generation script (gerar_contexto_llm.sh) before interacting with Gemini (AC14)."
    )

    return parser.parse_args()

def find_documentation_files(base_dir: Path) -> list[Path]:
    """
    Find potential documentation files (.md) in the project.

    Args:
        base_dir: The root directory of the project.

    Returns:
        A sorted list of relative Path objects for documentation files.
    """
    print("  Scanning for documentation files...")
    found_paths = set() # Use a set to avoid duplicates

    # Check specific root files
    for filename in ["README.md", "CHANGELOG.md"]: # Add more root files if needed
        filepath = base_dir / filename
        if filepath.is_file():
            found_paths.add(filepath.relative_to(base_dir))

    # Check docs directory recursively
    docs_dir = base_dir / "docs"
    if docs_dir.is_dir():
        for filepath in docs_dir.rglob("*.md"):
            # Add more specific filtering here if needed (e.g., ignore subdirs)
            if filepath.is_file():
                 found_paths.add(filepath.relative_to(base_dir))

    print(f"  Found {len(found_paths)} unique documentation files.")
    # Return a sorted list of Path objects based on their string representation
    return sorted(list(found_paths), key=lambda p: str(p))


def prompt_user_to_select_doc(doc_files: list[Path]) -> Path | None:
    """
    Displays a numbered list of doc files and prompts the user for selection.

    Args:
        doc_files: A list of relative Path objects for the documentation files.

    Returns:
        The selected relative Path object, or None if the user quits.
    """
    print("\nMultiple documentation files found. Please choose one to update:")
    for i, filepath in enumerate(doc_files):
        print(f"  {i + 1}: {filepath}")
    print("  q: Quit")

    while True:
        choice = input("Enter the number of the file to update (or 'q' to quit): ").strip().lower()
        if choice == 'q':
            return None
        try:
            index = int(choice) - 1
            if 0 <= index < len(doc_files):
                selected_path = doc_files[index]
                print(f"  You selected: {selected_path}")
                return selected_path # Return the relative path object
            else:
                print("  Invalid number. Please try again.")
        except ValueError:
            print("  Invalid input. Please enter a number or 'q'.")


def confirm_step(prompt: str) -> tuple[str, str | None]:
    """
    Asks the user for confirmation to proceed, redo, or quit.
    If redo ('n') is chosen, prompts for an observation.

    Args:
        prompt: The message to display to the user.

    Returns:
        A tuple: (user's choice ('y', 'n', 'q'), observation string or None).
        Converts choice to lowercase.
    """
    while True:
        response = input(f"{prompt} (Y/n/q - Yes/No/Quit) [Y]: ").lower().strip()
        if response in ['y', 'yes', '']:
            return 'y', None
        elif response in ['n', 'no']:
            # AC10: Ask for observation when user wants to redo
            observation = input("Please enter your observation/rule to improve the previous step: ").strip()
            if not observation:
                print("Observation cannot be empty if you want to redo. Please try again or choose 'y'/'q'.")
                continue # Ask again
            return 'n', observation
        elif response in ['q', 'quit']:
            return 'q', None
        else:
            print("Invalid input. Please enter Y, n, or q.")

def execute_gemini_call(client, model, contents, config: types.GenerateContentConfig | None = None) -> str: # Accept config
    """Executes a call to the Gemini API and returns the text response."""
    try:
        # Pass the config if provided
        response = client.models.generate_content(
            model=model,
            contents=contents,
            config=config # Pass the config here
        )
        # Handle potential API errors more gracefully if needed
        # Check response.prompt_feedback for safety issues, etc.
        if response.prompt_feedback and response.prompt_feedback.block_reason:
             print(f"  Warning: Prompt blocked due to {response.prompt_feedback.block_reason}.", file=sys.stderr)
             # Depending on the reason, might want to raise an error or return specific message
        # Check candidates for finish_reason
        if response.candidates:
             for candidate in response.candidates:
                 if candidate.finish_reason != types.FinishReason.STOP and candidate.finish_reason != types.FinishReason.FINISH_REASON_UNSPECIFIED:
                     print(f"  Warning: Candidate finished with reason: {candidate.finish_reason.name}", file=sys.stderr)
                     if hasattr(candidate, 'finish_message') and candidate.finish_message: # Check if finish_message exists
                         print(f"  Finish message: {candidate.finish_message}", file=sys.stderr)
        # Return text, even if warnings occurred, unless a critical APIError was raised
        return response.text
    except errors.APIError as e:
        print(f"  Gemini API Error: Code {e.code} - {e.message}", file=sys.stderr)
        if hasattr(e, 'details'): # Check if details exist
            print(f"  Error details: {e.details}", file=sys.stderr)
        else:
            print(f"  Error details: {e}", file=sys.stderr)
        raise # Re-raise the error to be caught by the calling loop
    except Exception as e:
        print(f"  Unexpected Error: {e}", file=sys.stderr)
        traceback.print_exc()
        raise # Re-raise the error


def modify_prompt_with_observation(original_prompt: str, observation: str) -> str:
    """Appends the user observation to the original prompt for retrying."""
    modified_prompt = f"{original_prompt}\n\n--- USER FEEDBACK FOR RETRY ---\n{observation}\n--- END FEEDBACK ---"
    print("\n  >>> Prompt modified with observation for retry <<<")
    # print(modified_prompt) # Optionally print the modified prompt
    return modified_prompt

# --- Main Execution ---
if __name__ == "__main__":
    # --- Load .env variables ---
    dotenv_path = BASE_DIR / '.env'
    if dotenv_path.is_file():
        print(f"Loading environment variables from: {dotenv_path.relative_to(BASE_DIR)}")
        load_dotenv(dotenv_path=dotenv_path, verbose=True)
    else:
        print(f"Warning: .env file not found at {dotenv_path}. Relying on system environment variables.", file=sys.stderr)
    # --- End Load .env ---

    available_tasks_dict = find_available_tasks(META_PROMPT_DIR)
    available_task_names = list(available_tasks_dict.keys())

    if not available_task_names:
        print(f"Error: No meta-prompt files found in '{META_PROMPT_DIR}'. Exiting.", file=sys.stderr)
        sys.exit(1)

    try:
        args = parse_arguments(available_task_names)
        selected_task = args.task
        selected_meta_prompt_path = available_tasks_dict[selected_task]
        GEMINI_MODEL = GEMINI_MODEL_RESOLVE if selected_task == "resolve-ac" else GEMINI_MODEL_GENERAL_TASKS

        print(f"\nLLM Interaction Script")
        print(f"========================")
        print(f"Selected Task: {selected_task}")
        print(f"Using Meta-Prompt: {selected_meta_prompt_path.relative_to(BASE_DIR)}")
        print(f"Using Model: {GEMINI_MODEL}")
        print(f"Web Search Enabled: {args.web_search}") # Log the flag status (AC13)
        print(f"Generate Context Flag: {args.generate_context}") # Log the flag status (AC14)

        # --- AC14: Run Context Generation Script ---
        if args.generate_context:
            print(f"\nRunning context generation script: {CONTEXT_GENERATION_SCRIPT.relative_to(BASE_DIR)}...")
            if not CONTEXT_GENERATION_SCRIPT.is_file():
                print(f"Error: Context generation script not found at {CONTEXT_GENERATION_SCRIPT}", file=sys.stderr)
                sys.exit(1)
            if not os.access(CONTEXT_GENERATION_SCRIPT, os.X_OK):
                 print(f"Error: Context generation script ({CONTEXT_GENERATION_SCRIPT}) is not executable. Please run 'chmod +x {CONTEXT_GENERATION_SCRIPT}'.", file=sys.stderr)
                 sys.exit(1)

            try:
                # Use subprocess.run for better control and error capturing
                result = subprocess.run([str(CONTEXT_GENERATION_SCRIPT)], capture_output=True, text=True, check=False, cwd=BASE_DIR)
                if result.returncode == 0:
                    print("Context generation script completed successfully.")
                    # print("Output:\n", result.stdout) # Optionally print stdout
                else:
                    print(f"Error: Context generation script failed with exit code {result.returncode}.", file=sys.stderr)
                    print(f"Stderr:\n{result.stderr}", file=sys.stderr)
                    print(f"Stdout:\n{result.stdout}", file=sys.stderr) # Show stdout even on failure
                    sys.exit(1) # Exit if context generation fails as requested by user
            except Exception as e:
                print(f"Error running context generation script: {e}", file=sys.stderr)
                traceback.print_exc()
                sys.exit(1)
        # --- End AC14 ---

        # --- Configure GenAI Client with API Key ---
        api_key = os.environ.get('GEMINI_API_KEY')
        if not api_key:
            print("Error: GEMINI_API_KEY environment variable not set (checked both system env and .env file).", file=sys.stderr)
            print("Please set the GEMINI_API_KEY in your .env file or as a system environment variable.", file=sys.stderr)
            sys.exit(1)
        try:
            print(f"\nInitializing Google GenAI Client...")
            genai_client = genai.Client(api_key=api_key) 
            print("Google GenAI Client initialized successfully.")
        except Exception as e:
            print(f"Error initializing Google GenAI Client: {e}", file=sys.stderr)
            sys.exit(1)
        # --- End Configure GenAI Client ---


        latest_context_dir = find_latest_context_dir(CONTEXT_DIR_BASE)
        if latest_context_dir is None:
            print("Fatal Error: Could not find a valid context directory. Exiting.", file=sys.stderr)
            sys.exit(1)
        print(f"Latest Context Directory: {latest_context_dir.relative_to(BASE_DIR)}")

        # --- Populate task_variables ---
        task_variables = {
            "NUMERO_DA_ISSUE": args.issue if args.issue else "",
            "NUMERO_DO_AC": args.ac if args.ac else "",
            "OBSERVACAO_ADICIONAL": args.observation,
            "ARQUIVO_DOC_ALVO": "" # Default to empty
        }

        # --- AC22: Handle document file selection for update-doc task ---
        if selected_task == "update-doc":
            doc_file_path_str = args.doc_file
            if not doc_file_path_str:
                print("\n--doc-file not provided for 'update-doc' task.")
                found_docs = find_documentation_files(BASE_DIR)
                if not found_docs:
                    print("Error: No documentation files (.md in root or docs/) found to choose from.", file=sys.stderr)
                    sys.exit(1)
                selected_doc_path_relative = prompt_user_to_select_doc(found_docs)
                if not selected_doc_path_relative:
                    print("User chose to quit. Exiting.")
                    sys.exit(0)
                # Convert relative Path object back to string for the variable dictionary
                doc_file_path_str = str(selected_doc_path_relative)
            else:
                 # Validate if the provided relative path exists
                provided_path = BASE_DIR / args.doc_file
                if not provided_path.is_file():
                     print(f"Error: Provided document file '{args.doc_file}' not found relative to project root.", file=sys.stderr)
                     sys.exit(1)
                 # Use the provided relative path string
                doc_file_path_str = args.doc_file

            task_variables["ARQUIVO_DOC_ALVO"] = doc_file_path_str
            if not task_variables["ARQUIVO_DOC_ALVO"]: # Safety check
                 print("Error: Target document file could not be determined for update-doc task.", file=sys.stderr)
                 sys.exit(1)
            print(f"Target document file set to: {task_variables['ARQUIVO_DOC_ALVO']}")
        # --- End AC22 ---

        print(f"\nFinal Variables for template: {task_variables}")

        # Load initial meta-prompt
        meta_prompt_content_original = load_and_fill_template(selected_meta_prompt_path, task_variables)
        if not meta_prompt_content_original:
             print(f"Error loading or filling the meta-prompt. Exiting.", file=sys.stderr)
             sys.exit(1)
        meta_prompt_content_current = meta_prompt_content_original # Keep track of the current version

        # AC13 Observação Adicional: Append web search encouragement to meta-prompt
        if args.web_search:
            print("  Appending web search encouragement to meta-prompt...")
            meta_prompt_content_current += WEB_SEARCH_ENCOURAGEMENT_PT

        # Prepare context parts (reading .txt, .json, .md files)
        context_parts = prepare_context_parts(latest_context_dir, COMMON_CONTEXT_DIR)
        if not context_parts:
             print("Warning: No context files loaded. The AI might lack sufficient information.", file=sys.stderr)

        # --- Prepare Tools based on flags (AC13) ---
        tools_list = []
        if args.web_search:
            print("  Configuring Google Search Retrieval tool...")
            # Using default config for GoogleSearchRetrieval
            google_search_retrieval_tool = types.Tool(
                google_search_retrieval=types.GoogleSearchRetrieval()
            )
            tools_list.append(google_search_retrieval_tool)
            print("  Google Search Retrieval tool added.")
        # Add other tools here if needed based on future args

        # --- Create base GenerateContentConfig if tools are needed ---
        base_config = None
        if tools_list:
            # Use GenerationConfig instead of GenerateContentConfig for tools
            base_config = types.GenerationConfig(tools=tools_list)
            print("  GenerationConfig created with tools.")
        # Add other base config options here if necessary (e.g., safety_settings)


        print("\nStarting interaction with Gemini API...")

        # --- Step 1 Loop (Meta-Prompt + Context -> Final Prompt) ---
        prompt_final_content = None
        while True:
            print(f"\nStep 1: Sending Meta-Prompt and Context (Model: {GEMINI_MODEL})...")
            # Pass prompt using keyword argument 'text='
            contents_etapa1 = [types.Part.from_text(text=meta_prompt_content_current)] + context_parts
            try:
                # Pass the base_config to the execution call for step 1 (AC13)
                prompt_final_content = execute_gemini_call(genai_client, GEMINI_MODEL, contents_etapa1, config=base_config)
                print("\n------------------------")
                print("  >>> Final Prompt Received (Step 1):")
                print("  ```")
                print(prompt_final_content.strip())
                print("  ```")
                print("------------------------")

                user_choice_step1, observation_step1 = confirm_step("Proceed to Step 2 with this prompt?")

                if user_choice_step1 == 'y':
                    break # Exit Step 1 loop, proceed to Step 2
                elif user_choice_step1 == 'q':
                    print("Exiting after Step 1 as requested.")
                    sys.exit(0)
                elif user_choice_step1 == 'n':
                    # AC11: Incorporate observation into meta-prompt for retry
                    print(f"Received observation for Step 1 retry: '{observation_step1}'")
                    meta_prompt_content_current = modify_prompt_with_observation(meta_prompt_content_current, observation_step1)
                    # Continue loop to retry Step 1 with modified prompt
                else: # Should not happen due to confirm_step loop
                    print("Internal error in confirmation logic. Exiting.", file=sys.stderr)
                    sys.exit(1)

            except Exception as e: # Catches API errors and others raised by execute_gemini_call
                print(f"  An error occurred during Step 1 execution: {e}", file=sys.stderr)
                retry_choice, _ = confirm_step("Retry Step 1?")
                if retry_choice == 'q':
                    print("Exiting due to error in Step 1.")
                    sys.exit(1)
                elif retry_choice == 'n':
                     print("Exiting due to error in Step 1.")
                     sys.exit(1)
                # If 'y', loop continues to retry Step 1


        if not prompt_final_content: # Should ideally not happen if loop breaks on 'y'
            print("Error: Could not obtain final prompt from Step 1. Aborting.", file=sys.stderr)
            sys.exit(1)

        # --- Step 2 Loop (Final Prompt + Context -> Final Response) ---
        resposta_final_content = None
        prompt_final_content_current = prompt_final_content # Keep track of current final prompt

        # AC13 Observação Adicional: Append web search encouragement to final prompt
        if args.web_search:
            print("  Appending web search encouragement to final prompt...")
            prompt_final_content_current += WEB_SEARCH_ENCOURAGEMENT_PT

        while True:
            print(f"\nStep 2: Sending Final Prompt and Context (Model: {GEMINI_MODEL})...")
            # Pass final prompt using keyword argument 'text='
            contents_etapa2 = [types.Part.from_text(text=prompt_final_content_current)] + context_parts
            try:
                 # Pass the base_config to the execution call for step 2 (AC13)
                resposta_final_content = execute_gemini_call(genai_client, GEMINI_MODEL, contents_etapa2, config=base_config)
                print("\n------------------------")
                print("  >>> Final Response Received (Step 2):")
                print("  ```")
                print(resposta_final_content.strip())
                print("  ```")
                print("------------------------")

                user_choice_step2, observation_step2 = confirm_step("Save this response?")

                if user_choice_step2 == 'y':
                    break # Exit Step 2 loop, proceed to save
                elif user_choice_step2 == 'q':
                    print("Exiting without saving the response as requested.")
                    sys.exit(0)
                elif user_choice_step2 == 'n':
                     # AC11: Incorporate observation into final prompt for retry
                    print(f"Received observation for Step 2 retry: '{observation_step2}'")
                    prompt_final_content_current = modify_prompt_with_observation(prompt_final_content_current, observation_step2)
                    # Continue loop to retry Step 2 with modified prompt
                else: # Should not happen
                    print("Internal error in confirmation logic. Exiting.", file=sys.stderr)
                    sys.exit(1)

            except Exception as e: # Catches API errors and others
                print(f"  An error occurred during Step 2 execution: {e}", file=sys.stderr)
                retry_choice, _ = confirm_step("Retry Step 2?")
                if retry_choice == 'q':
                    print("Exiting due to error in Step 2.")
                    sys.exit(1)
                elif retry_choice == 'n':
                     print("Exiting due to error in Step 2.")
                     sys.exit(1)
                # If 'y', loop continues to retry Step 2

        # --- Save Final Response (AC8) ---
        if resposta_final_content is not None: # Check if content exists
            print("\nSaving Final Response...")
            save_llm_response(selected_task, resposta_final_content.strip())
        else:
            print("\nNo final response was generated to save.", file=sys.stderr) # Should not happen if loop breaks on 'y'
            sys.exit(1)
        # --- End Save Final Response ---

    except SystemExit as e:
        if e.code != 0:
             print(f"\nScript exited with code {e.code}.", file=sys.stderr)
        sys.exit(e.code)
    except Exception as e:
        print(f"\nUnexpected error during execution: {e}", file=sys.stderr)
        traceback.print_exc()
        sys.exit(1)

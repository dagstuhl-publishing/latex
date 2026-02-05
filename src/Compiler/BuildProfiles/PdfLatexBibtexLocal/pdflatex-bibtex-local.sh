#!/bin/bash

if [ -z "${PDF_LATEX_BIN}" ]; then
  PDF_LATEX_BIN="pdflatex"
fi

if [[ $1 == '--version' ]]; then
    ${PDF_LATEX_BIN} --version
    exit 0
fi

WORK_DIR="$(dirname "$1")"
FILE_NAME="$(basename "$1" .tex)"

if [[ ${PDF_LATEX_BIN} == 'pdflatex' || -z ${PDF_LATEX_BIN} ]]; then
    LATEX_CMD="pdflatex -interaction=nonstopmode -halt-on-error ${LATEX_OPTIONS} \"${FILE_NAME}\""
else
    LATEX_CMD="${PDF_LATEX_BIN} \"${FILE_NAME}\""
fi
BIBTEX_CMD="bibtex \"${FILE_NAME}\""


function run_latex_pass() {
    rm -f "${FILE_NAME}.log"
    eval "${LATEX_CMD} > /dev/null"
    LATEX_EXIT_CODE=$?
    echo "- LaTeX pass -> exit code: ${LATEX_EXIT_CODE}"
}

function run_bibtex_pass() {
    eval "${BIBTEX_CMD} > /dev/null"
    BIBTEX_EXIT_CODE=$?
    echo "- BibTeX pass -> exit code: ${BIBTEX_EXIT_CODE}"
}

function another_run_needed() {
    if grep -q "Temporary extra page added at the end. Rerun to get it removed." "${FILE_NAME}.log"; then
        echo -n "- WARNING: temporary-page added "
        return 1;
    fi

    if grep -q "Label(s) may have changed. Rerun" "${FILE_NAME}.log"; then
        echo -n "- WARNING: label(s) may have changed "
        return 1;
    fi

    if grep -q "There were undefined references" "${FILE_NAME}.log"; then
        echo -n "- WARNING: undefined references "
        return 1;
    fi
    return 0;
}

function print_exit_codes() {
    echo
    echo "Last LaTeX exit code [${LATEX_EXIT_CODE}], Last BibTeX exit code [${BIBTEX_EXIT_CODE}]";
}



echo "LaTeX build info"
echo -n "- LaTeX version: "
${PDF_LATEX_BIN} --version | head -1
echo "- \$HOME: ${HOME}"
echo "- \$PATH: ${PATH}"
echo "- Work dir: ${WORK_DIR}"
echo "- LaTeX-Command: ${LATEX_CMD}"
echo "- BibTeX-Command: ${BIBTEX_CMD}"
echo
echo "Starting build"
echo "- Mode: ${MODE}"

cd "${WORK_DIR}"

# --- latex-only mode ---
if [[ ${MODE} == 'latex-only' ]]; then
    run_latex_pass

    another_run_needed
    ANOTHER_RUN_NEEDED=$?
    if [[ ${ANOTHER_RUN_NEEDED} -ne 0 ]]; then
      echo "-> re-run latex"
      run_latex_pass
    fi

    print_exit_codes
    exit
fi

# --- bibtex-only mode ---
if [[ ${MODE} == 'bibtex-only' ]]; then
    run_bibtex_pass
    print_exit_codes
    exit
fi

# --- full mode ---
rm -f "${FILE_NAME}.aux" "${FILE_NAME}.vtc"
echo "- removed aux and vtc file(s)"

[ ! -f "${FILE_NAME}.bbl" ] || mv "${FILE_NAME}.bbl" "${FILE_NAME}.bbl.old"
echo "- moved bbl file to bbl.old"

run_latex_pass

if [[ ${BIB_MODE} == "bibtex" ]]; then
    run_bibtex_pass
    run_latex_pass
fi

if [[ ${LATEX_EXIT_CODE} -ne 0 ]]; then
    # IMPORTANT:
    # - the following extra run is necesary to obtain "label multiply defined" error messages (if this error occurs)
    # - these are not contained in the log of the first run (TeXLive 2023/2024)
    another_run_needed
    ANOTHER_RUN_NEEDED=$?
    if [[ ${ANOTHER_RUN_NEEDED} -ne 0 ]]; then
      echo "-> rerun latex once"
      run_latex_pass
    fi

    if [[ ${LATEX_EXIT_CODE} -ne 0 ]]; then
      echo "- compilation failed"
      print_exit_codes
      exit
    fi
fi

run_latex_pass

another_run_needed
ANOTHER_RUN_NEEDED=$?
if [[ ${ANOTHER_RUN_NEEDED} -ne 0 ]]; then
  echo "-> rerun latex once"
  run_latex_pass
fi

print_exit_codes
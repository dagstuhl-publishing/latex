#!/bin/bash

# LATEX_BIN
# BIBTEX_BIN
# LATEX_OPTIONS
# FILE_NAME
# WORK_DIR

LATEX_CMD="${LATEX_BIN} -interaction=nonstopmode ${LATEX_OPTIONS} \"${FILE_NAME}\""
BIBTEX_CMD="${BIBTEX_BIN} \"${FILE_NAME}\""

function run_latex_pass() {
    rm -f "${FILE_NAME}.log"
    eval "${LATEX_CMD} > /dev/null"
    LATEX_EXIT_CODE=$?
    echo "${LATEX_CMD} -> exit code: ${LATEX_EXIT_CODE}"
}

function run_bibtex_pass() {
    eval "${BIBTEX_CMD} > /dev/null"
    BIBTEX_EXIT_CODE=$?
    echo "${BIBTEX_CMD} -> exit code: ${BIBTEX_EXIT_CODE}"
}

function print_exit_codes() {
    echo "Last LaTeX exit code [${LATEX_EXIT_CODE}], Last BibTeX exit code [${BIBTEX_EXIT_CODE}]";
}

echo ${LATEX_MODE}

cd "${WORK_DIR}"

if [[ ${LATEX_MODE} != 'simple' ]]; then
    rm -f "${FILE_NAME}.aux" "${FILE_NAME}.vtc"
    echo "Removed aux file"
fi

[ ! -f "${FILE_NAME}.bbl" ] || mv "${FILE_NAME}.bbl" "${FILE_NAME}.bbl.old"

run_latex_pass

if [[ ${LATEX_MODE} == 'simple' ]]; then
    print_exit_codes
    exit
fi

if grep -q "Temporary extra page added at the end. Rerun to get it removed." "${FILE_NAME}.log"; then
    run_latex_pass
fi

if [[ ${LATEX_EXIT_CODE} -ne 0 ]]; then
    run_latex_pass
fi

if [[ ${LATEX_EXIT_CODE} -ne 0 ]]; then
    echo "Compilation failed"
    print_exit_codes
    exit
fi

if [[ ${BIB_MODE} == "bibtex" ]]; then
    run_bibtex_pass
fi

run_latex_pass
run_latex_pass

if grep -q "Label(s) may have changed. Rerun" "${FILE_NAME}.log"; then
    run_latex_pass
fi

print_exit_codes
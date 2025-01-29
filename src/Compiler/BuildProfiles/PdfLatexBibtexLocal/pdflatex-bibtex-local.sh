#!/bin/bash

if [[ $1 == '--version' ]]; then
    pdflatex --version
    exit 0
fi


WORK_DIR="$(dirname "$1")"
FILE_NAME="$(basename "$1" .tex)"

LATEX_CMD="pdflatex -interaction=nonstopmode ${LATEX_OPTIONS} \"${FILE_NAME}\""
BIBTEX_CMD="bibtex \"${FILE_NAME}\""



function run_latex_pass() {
    rm -f "${FILE_NAME}.log"
    eval "${LATEX_CMD} > /dev/null"
    LATEX_EXIT_CODE=$?
    echo "- LaTeX pass -> exit code: ${LATEX_EXIT_CODE}"
}

function run_latex_pass_if_extra_page_occurs() {
    if grep -q "Temporary extra page added at the end. Rerun to get it removed." "${FILE_NAME}.log"; then
        echo "- temporary extra-page issue -> rerun pdflatex"
        run_latex_pass
    fi
}

function run_bibtex_pass() {
    eval "${BIBTEX_CMD} > /dev/null"
    BIBTEX_EXIT_CODE=$?
    echo "- BibTeX pass -> exit code: ${BIBTEX_EXIT_CODE}"
}

function print_exit_codes() {
    echo
    echo "Last LaTeX exit code [${LATEX_EXIT_CODE}], Last BibTeX exit code [${BIBTEX_EXIT_CODE}]";
}



echo "LaTeX build info"
echo -n "- LaTeX version: "
pdflatex --version | head -1
echo "- Work dir: ${WORK_DIR}"
echo "- LaTeX-Command: ${LATEX_CMD}"
echo "- BibTeX-Command: ${LATEX_CMD}"
echo
echo "Starting build"
echo "- Mode: ${MODE}"

cd "${WORK_DIR}"

if [[ ${MODE} == 'latex-only' ]]; then
    run_latex_pass
    run_latex_pass_if_extra_page_occurs
    print_exit_codes
    exit
fi

if [[ ${MODE} == 'bibtex-only' ]]; then
    run_bibtex_pass
    print_exit_codes
    exit
fi

rm -f "${FILE_NAME}.aux" "${FILE_NAME}.vtc"
echo "- removed aux and vtc file(s)"

[ ! -f "${FILE_NAME}.bbl" ] || mv "${FILE_NAME}.bbl" "${FILE_NAME}.bbl.old"
echo "- moved bbl file to bbl.old"

run_latex_pass

if [[ ${LATEX_EXIT_CODE} -ne 0 ]]; then
    echo "Compilation failed"
    print_exit_codes
    exit
fi

if [[ ${BIB_MODE} == "bibtex" ]]; then
    run_bibtex_pass
    run_latex_pass
fi

run_latex_pass

if grep -q "Label(s) may have changed. Rerun" "${FILE_NAME}.log"; then
    run_latex_pass
fi

print_exit_codes
# Históricos escolares → CSV de enturmação

Converte os PDFs de histórico (UNI7) num CSV com **apenas** o necessário para a enturmação:
matrícula, nome, currículo, turno e a situação de cada disciplina. CPF, RG e data de
nascimento **não** são lidos. Não versione os PDFs nem o CSV gerado (dados pessoais).

1. Texto dos PDFs (Poppler `pdftotext`; no Windows: https://github.com/oschwartz10612/poppler-windows):

       for f in historicos/*.pdf; do pdftotext -layout "$f" "txt/$(basename "${f%.pdf}").txt"; done

2. CSV normalizado:

       python3 -I tools/historicos/parse_historicos.py txt historico-alunos.csv

3. Importação no sistema e enturmação (tela **Enturmação**, coordenação):

       php artisan alunos:importar-historico historico-alunos.csv --fonte="Históricos 24/10/2025"

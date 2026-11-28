"""Extrai dos históricos (texto do pdftotext -layout) só o necessário para a enturmação.
Não lê CPF/RG/nascimento. Uso: python3 -I parse_historicos.py <pasta_txt> <saida.csv>"""
import csv, glob, os, re, sys, unicodedata

CODE = r'(UNI7[A-Z]\d{3}|GSER\d{5,7})'
def norm(s): return unicodedata.normalize('NFKD', s).encode('ascii', 'ignore').decode().upper()

rows = []
for path in sorted(glob.glob(os.path.join(sys.argv[1], '*.txt'))):
    text = open(path, encoding='utf-8', errors='replace').read()
    m = re.search(r'MATR[IÍ]CULA\s+(\d+)\s+NOME\s+(.+?)\s{2,}|MATR[IÍ]CULA\s+(\d+)\s+NOME\s+(.+)$', text, re.M)
    matricula = (m.group(1) or m.group(3)).strip(); nome = (m.group(2) or m.group(4)).strip()
    cur = re.search(r'C[OÓ]DIGO CURR[IÍ]CULO.*\n\s*(\S+)\s+.*?\s(MANH[AÃ]|TARDE|NOITE)\s*$', text, re.M)
    curriculo, turno = (cur.group(1), cur.group(2)) if cur else ('', '')
    section = None
    for line in text.splitlines():
        u = norm(line)
        if 'DISCIPLINAS CURSADAS / DISPENSADAS' in u: section = 'cursada'; continue
        if 'CURSANDO / REPROVADAS' in u: section = 'cursando'; continue
        if re.search(r'^\s*FALTA CURSAR\s*$', u): section = 'falta'; continue
        if 'ELETIVAS CURSADAS' in u or 'OPTATIVAS CURSADAS' in u or 'ATIVIDADE COMPLEMENTAR' in u or 'RESUMO GERAL' in u: section = None; continue
        if not section: continue
        mm = re.match(r'^\s*([A-Z]{0,3}\d{1,5})\s+' + CODE + r'\s+(.+?)\s{2,}(.*)$', line)
        if not mm: continue
        periodo, codigo, disciplina, resto = mm.groups()
        resto_u = norm(resto)
        if section == 'cursada':
            situacao = 'dispensada' if ('DISPENS' in resto_u or 'APROVEIT' in resto_u) else 'aprovada'
            media = re.search(r'(\d+,\d+)', resto)
        elif section == 'cursando':
            situacao = 'matriculada' if 'MATRICULAD' in resto_u else ('trancada' if 'TRANC' in resto_u or 'CANCEL' in resto_u or 'ABANDON' in resto_u else 'reprovada')
            media = None
        else:
            situacao = 'falta_cursar'; media = None
        ch = re.search(r'\b(\d{2,3})\b', resto)
        rows.append({
            'matricula': matricula, 'nome': nome, 'curriculo': curriculo, 'turno': turno,
            'periodo_letivo': periodo if section != 'falta' else '',
            'periodo_matriz': periodo if section == 'falta' else '',
            'codigo': codigo, 'disciplina': disciplina.strip(), 'carga_horaria': ch.group(1) if ch else '',
            'situacao': situacao, 'resultado': resto.strip() if section != 'falta' else '',
        })

with open(sys.argv[2], 'w', newline='', encoding='utf-8') as f:
    w = csv.DictWriter(f, fieldnames=list(rows[0].keys()), delimiter=';'); w.writeheader(); w.writerows(rows)

students = {}
for r in rows: students.setdefault(r['matricula'], r)
print(len(students), 'alunos,', len(rows), 'linhas')
from collections import Counter
print('currículos:', Counter(s['curriculo'] for s in students.values()))
print('turnos:', Counter(s['turno'] for s in students.values()))
print('situações:', Counter(r['situacao'] for r in rows))

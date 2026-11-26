"""Converte os Guias do Professor (docx, versão remota) do curso de Robótica em JSON da plataforma."""
import re, json, sys, os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from codigos_rob import C as CODIGO

UP = 'A-ZÁÉÍÓÚÂÊÔÃÕÇÀ'
LO = 'a-záéíóúâêôãõçà'
ROTULOS = re.compile(r'^(GUIA DO PROFESSOR(?: · CONDUÇÃO DO PROJETO)?|CÓDIGO|RESULTADO ESPERADO|DESAFIO EXTRA|DESAFIO · \d+ MIN|ATENÇÃO|FÓRMULA|IDEIA-CHAVE|DICA|REGRA|APRESENTAÇÃO FINAL · AULA \d+|PROJETO|SEGURANÇA|CURIOSIDADE|IMPORTANTE)\s*')
FRONT = re.compile(rf'(?:(?<=[{LO}0-9\.\)”"%!?])(?=[{UP}](?:[{LO}]| ))|(?<=\s[{UP}])(?=[{UP}][{LO}])|(?<=[{UP}]{{2}})(?=[{UP}][{LO}]{{2}}))')

def limpa(s):
    s = s.replace('\\`', '`').replace('\\*', '').replace('\\_', '_').replace('\\[', '[').replace('\\]', ']').replace('\\>', '>').replace('\\<', '<').replace('\\!', '!').replace('\\-', '-').replace('\\#', '#')
    s = s.replace('**', '')
    return re.sub(r'\s+', ' ', s).strip()

def celulas(l):
    return [limpa(c) for c in l.strip().strip('|').split('|')]

def separa_titulo(s):
    m = FRONT.search(s)
    return (s[:m.start()].strip(), s[m.start():].strip()) if m else ('', s)

def rotulo_bonito(r):
    r = r.capitalize().replace(' min', ' min')
    return re.sub(r'· (\d+) min', r'· \1 min', r).replace('Ideia-chave', 'Ideia-chave')

def caixa(conteudo):
    """'DICAO semáforo...' -> ('DICA', 'O semáforo...')"""
    m = ROTULOS.match(conteudo)
    if not m: return None, conteudo
    return m.group(1), conteudo[m.end():].strip()

def guia_professor(txt):
    txt = txt.replace('•', '\n- ')
    for k in ['Preparação:', 'O que observar:', 'Respostas esperadas:', 'Adaptação:', 'Sinais de alerta:']:
        txt = txt.replace(k, f'\n\n**{k}**')
    return re.sub(r'\n{3,}', '\n\n', txt).strip()

def converte_bloco(linhas, chave=None):
    """Linhas de uma aula -> (conteúdo do aluno, notas do professor)."""
    out, notas = [], []
    i = 0
    while i < len(linhas):
        l = linhas[i].rstrip()
        if not l.strip() or re.match(r'^\|\s*(\|\s*)*$', l) or re.match(r'^\|\s*:-:', l):
            i += 1; continue
        if l.startswith('|'):
            # junta as linhas da tabela
            tab = []
            while i < len(linhas) and linhas[i].startswith('|'):
                if not re.match(r'^\|\s*(:-:\s*\|\s*)+$', linhas[i]) and not re.match(r'^\|\s*(\|\s*)+$', linhas[i]):
                    tab.append(celulas(linhas[i]))
                i += 1
            tab = [r for r in tab if any(r)]
            if not tab: continue
            if len(tab[0]) == 1 and len(tab) == 1:
                rot, txt = caixa(tab[0][0])
                if rot and rot.startswith('GUIA DO PROFESSOR'):
                    notas.append(guia_professor(txt))
                elif rot and rot.startswith('CÓDIGO'):
                    out.append('```\n' + CODIGO.get(chave, txt) + '\n```')
                elif rot:
                    out.append(f'[[{rotulo_bonito(rot)}]] {txt}')
                else:
                    out.append(txt)
            elif all(len(r) == 2 for r in tab):
                if tab[0][0].lower() == 'sintoma': tab = tab[1:]
                if all(re.fullmatch(r'\d+', r[0] or '') for r in tab):
                    for n, (num, txt) in enumerate(tab, 1):
                        t, corpo = separa_titulo(txt)
                        out.append(f'{num}. **{t}:** {corpo}' if t else f'{num}. {corpo}')
                elif all(re.search(r'por (aluno|grupo|dupla|trio|turma)|^\d', r[1] or '') for r in tab):
                    out.extend(f'- {a} ({b})' for a, b in tab)
                else:
                    out.extend(f'- **{a}:** {b}' for a, b in tab)
            else:
                for r in tab:
                    out.append('- ' + ' · '.join(c for c in r if c))
            continue
        s = limpa(l)
        if re.match(r'^(Conceito|Entenda|Mão na massa|Caderno de bordo|Vocabulário|Palavras novas|E se não funcionar|Desafio|Teste|Meu registro)', s) and len(s) < 60:
            if s.startswith('Meu registro'):
                i += 1; continue
            out.append('## ' + s.replace('Palavras novas', 'Vocabulário').replace('Entenda', 'Conceito'))
        elif s == 'MATERIAIS':
            out.append('**Materiais**')
        elif re.match(r'^(Data:|Visto do professor)', s):
            pass
        else:
            out.append(s)
        i += 1
    return '\n\n'.join(out).strip(), '\n\n'.join(notas).strip()

def modulo(caminho, num, titulo_mod):
    t = open(caminho).read()
    linhas = t.split('\n')
    cab = [k for k, l in enumerate(linhas) if re.match(r'^\| AULA \d', l)]
    aulas = []
    # objetivos do módulo
    ini = t[:t.find('| AULA 1 ')]
    m = re.search(r'Ao fim do módulo, (.*?)\n', ini)
    obj = re.search(r'Ao fim deste módulo você vai\n(.*?)\n\nMapa das aulas', ini, re.S)
    objetivos = [limpa(x) for x in (obj.group(1).split('\n') if obj else []) if x.strip()]
    projeto = re.search(r'\| PROJETO([^|]+)\|', t)
    proj_titulo, proj_desc = separa_titulo(limpa(projeto.group(1))) if projeto else ('Projeto final', '')
    intro = []
    if m: intro.append('Ao fim do módulo, ' + limpa(m.group(1)))
    if objetivos:
        intro.append('## O que você vai aprender'); intro += ['- ' + o for o in objetivos]
    if projeto:
        intro.append('## Projeto do módulo'); intro.append(f'**{proj_titulo}:** {proj_desc}')
    aulas.append({'titulo': f'Boas-vindas ao Módulo {num}', 'tipo': 'TEXTO', 'conteudo': '\n\n'.join(intro), 'duracao_min': 5})

    rubrica = []
    rb = re.search(r'Rubrica de avaliação\n\n(.*?)\n\n(?!\|)', t, re.S)
    if rb:
        for l in rb.group(1).split('\n'):
            c = celulas(l)
            if len(c) == 2 and c[0] and c[0] not in ('Critério', ':-:'):
                rubrica.append(f'{c[0]}: {c[1]}')
    for k, ini_l in enumerate(cab):
        fim = cab[k + 1] if k + 1 < len(cab) else len(linhas)
        h = linhas[ini_l]
        mm = re.match(r'^\| AULA (\d+) · MÓDULO \d · (\d+) min · (?:remota|presencial)?\s*(.*?) \|$', h)
        if mm:
            n, dur, tit = int(mm.group(1)), int(mm.group(2)), limpa(mm.group(3))
            conteudo, notas = converte_bloco(linhas[ini_l + 1:fim], f'R{num}A{n}')
            aulas.append({'titulo': f'Aula {n} · {tit}', 'tipo': 'TEXTO', 'conteudo': conteudo, 'notas': notas, 'duracao_min': dur})
        else:
            mp = re.match(r'^\| AULA (\d+)(.*) \|$', h)
            n, corpo = int(mp.group(1)), limpa(mp.group(2))
            tit, resto = separa_titulo(corpo)
            partes = [p.strip() for p in FRONT.split(resto) if p.strip()]
            entrega = [p for p in partes if p.startswith('Entrega:')]
            passos = [p for p in partes if not p.startswith('Entrega:')]
            desc, passos = (passos[0], passos[1:]) if passos else ('', [])
            txt = [desc, '## Passo a passo'] + [f'{j}. {p}' for j, p in enumerate(passos, 1)]
            if entrega: txt.append('[[Entrega]] ' + entrega[0].replace('Entrega:', '').strip())
            aulas.append({'titulo': f'Projeto · Aula {n} · {tit}', 'tipo': 'TEXTO', 'conteudo': '\n\n'.join(txt), 'duracao_min': 60})
    # parte final do projeto: esqueleto, apresentação, guia
    final = '\n'.join(linhas[cab[-1] + 1:])
    _, notas_proj = converte_bloco([l for l in final.split('\n') if 'GUIA DO PROFESSOR' in l])
    ult = aulas[-1]
    ult['tipo'] = 'EXERCICIO'
    ap = re.search(r'\| APRESENTAÇÃO FINAL[^|]*?·\s*AULA \d+([^|]+)\|', t)
    esq = re.search(r'Esqueleto do programa\n\n\|[^\n]*\n\|[^\n]*\n\| CÓDIGO([^|]+)\|', t)
    if esq: ult['conteudo'] += '\n\n## Esqueleto do programa\n\n```\n' + CODIGO.get(f'R{num}AP', limpa(esq.group(1))) + '\n```'
    if ap: ult['conteudo'] += '\n\n## Apresentação final\n\n' + limpa(ap.group(1))
    ult['notas'] = notas_proj
    ult['exercicio'] = {
        'enunciado': f'{proj_titulo}. {proj_desc}\n\nEnvie o link do projeto no simulador (Tinkercad ou Wokwi) ou de um vídeo mostrando o robô funcionando, e conte no recado o que cada integrante fez.',
        'rubrica': rubrica or ['Funcionamento', 'Uso dos conceitos do módulo', 'Registro no caderno de bordo', 'Apresentação'],
        'tipo_entrega': 'LINK_VIDEO',
    }
    return {'titulo': titulo_mod, 'aulas': aulas}

if __name__ == '__main__':
    W = sys.argv[1]
    mods = [
        ('Professor_Modulo_1_Primeiros_Passos', 'Primeiros passos: lógica, eletricidade e blocos'),
        ('Professor_Modulo_2_Eletronica_e_Arduino', 'Eletrônica e Arduino'),
        ('Professor_Modulo_3_Cpp_Sensores_e_Atuadores', 'C++, sensores e atuadores'),
        ('Professor_Modulo_4_Mecanica_e_Impressao_3D', 'Mecânica, modelagem e impressão 3D'),
        ('Professor_Modulo_5_IoT_e_Automacao', 'IoT e automação'),
        ('Professor_Modulo_6_Robotica_Avancada', 'Robótica avançada'),
    ]
    curso = {
        'slug': 'robotica-e-programacao',
        'titulo': 'Robótica e Programação',
        'descricao': 'Curso de 2 anos em 6 módulos: do primeiro robô e da lógica em blocos à eletrônica com Arduino, C++, modelagem e impressão 3D, IoT e robótica avançada. Aulas remotas com simulador e projeto final em cada módulo.',
        'nivel': 'INICIANTE', 'carga_horaria': 0,
        'modulos': [modulo(os.path.join(W, a + '.txt'), i, t) for i, (a, t) in enumerate(mods, 1)],
    }
    curso['carga_horaria'] = round(sum(a['duracao_min'] for m in curso['modulos'] for a in m['aulas']) / 60)
    json.dump(curso, open(sys.argv[2], 'w'), ensure_ascii=False, indent=1)
    for m in curso['modulos']:
        print(m['titulo'], len(m['aulas']), 'aulas; rubrica:', len(m['aulas'][-1]['exercicio']['rubrica']), '; notas vazias:', sum(1 for a in m['aulas'][1:] if not a.get('notas')))

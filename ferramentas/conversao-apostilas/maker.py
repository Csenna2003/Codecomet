"""Converte a Apostila Completa (aluno e professor) do Currículo Maker em JSON da plataforma."""
import re, json, sys, os

LO = 'a-záéíóúâêôãõçà'
ENC = re.compile(r'^(\d+) (.+?) (\d+) min$')

def linhas(txt):
    txt = re.sub(r'\n\n\d+ Currículo Maker de 70 horas · adororobotica\.com\.br', '', txt)
    out = []
    for l in txt.split('\n'):
        l = re.sub(r'\s+', ' ', l.replace('\\', '')).strip()
        if not l or l.startswith('Adoro Robótica · Currículo Maker') or l.startswith('ADORO ROBÓTICA · CURRÍCULO MAKER · APOSTILA'):
            continue
        if out and re.match(rf'^[{LO}(,;.]', l) and not ENC.match(out[-1]):
            out[-1] += ' ' + l
        else:
            out.append(l)
    return out

def trilhas(bloco):
    """[cabeçalho A+B, texto A, cabeçalho C, texto B, texto C] -> (A, B, C)"""
    a, b, c = [], [], []
    fase = 0
    for l in bloco:
        if l.startswith('TRILHA A'): fase = 1; continue
        if l.startswith('TRILHA C'): fase = 2; continue
        if fase == 1: a.append(l)
        elif fase == 2: (b if not b else c).append(l) if len(b) == 0 or len(c) > 0 or True else None
    # na fase 2 o primeiro texto é da trilha B e o seguinte da C
    resto = [l for l in bloco[bloco.index(next(x for x in bloco if x.startswith('TRILHA C'))) + 1:]] if any(x.startswith('TRILHA C') for x in bloco) else []
    b = resto[:1]; c = resto[1:]
    return ' '.join(a), ' '.join(b), ' '.join(c)

def encontro(ls):
    """Linhas de um encontro (sem o cabeçalho) -> conteúdo do aluno."""
    out, agenda, i = [], [], 0
    while i < len(ls):
        l = ls[i]
        if l.startswith('Meta:'):
            out.append('[[Meta]] ' + l[5:].strip())
        elif re.match(r'^\d+ min ', l):
            agenda.append('- ' + l)
        elif l.startswith('PERGUNTA PARA PENSAR'):
            if agenda: out.append('## Roteiro do encontro'); out += agenda; agenda = []
            out.append('[[Pergunta para pensar]] ' + l.replace('PERGUNTA PARA PENSAR', '').strip())
        elif l.startswith('SEU DESAFIO POR TRILHA'):
            j = i + 1
            while j < len(ls) and not ls[j].startswith('VOCÊ ENTREGA'): j += 1
            a, b, c = trilhas(ls[i + 1:j])
            out.append('## Seu desafio por trilha')
            out += [f'- **Trilha A (6 a 10 anos):** {a}', f'- **Trilha B (11 a 14 anos):** {b}', f'- **Trilha C (15 a 17 anos):** {c}']
            i = j; continue
        elif l.startswith('VOCÊ ENTREGA'):
            out.append('[[Você entrega]] ' + l.replace('VOCÊ ENTREGA', '').strip())
        elif not out and not l.startswith('Meta'):
            pass
        else:
            out.append(f'*Temas: {l}*' if len(out) == 1 and not agenda and len(l) < 120 and not l.endswith('.') else l)
        i += 1
    if agenda: out += ['## Roteiro do encontro'] + agenda
    return '\n\n'.join(out)

def notas_professor(prof_linhas):
    """{(módulo, encontro): notas} a partir de 'Encontros detalhados' do guia do professor."""
    notas, mod, enc, buf = {}, 0, None, []
    def fecha():
        if enc and buf: notas[(mod, enc)] = '\n\n'.join(buf)
    dentro = False
    for l in prof_linhas:
        if l == 'Encontros detalhados':
            fecha(); mod += 1; enc = None; buf = []; dentro = True; continue
        if not dentro: continue
        m = ENC.match(l)
        if m:
            fecha(); enc, buf = int(m.group(1)), []; continue
        if l.startswith('ERROS COMUNS'):
            buf.append('**Erros comuns:**'); continue
        if l.startswith('PERGUNTA DE MEDIAÇÃO'):
            buf.append('**Pergunta de mediação:** ' + l.replace('PERGUNTA DE MEDIAÇÃO', '').strip()); continue
        if l.startswith('EVIDÊNCIA'):
            buf.append('**Evidência de aprendizagem:** ' + l.replace('EVIDÊNCIA', '').strip()); continue
        if buf and buf[-1] == '**Erros comuns:**' or (buf and buf[-1].startswith('- ') and not l.startswith(('PERGUNTA', 'EVIDÊNCIA', 'DIFERENCIA', 'TRILHA'))):
            if not re.match(r'^\d+ min ', l) and not l.startswith(('Meta:', 'DIFERENCIAÇÃO', 'TRILHA')):
                buf.append('- ' + l)
        if l in ('Sobre o módulo', 'Glossário'): dentro = False
    fecha()
    return notas

def modulo(ls, num, notas, titulo_mod):
    sobre = ls.index('Sobre o módulo')
    desc = ls[sobre + 1]
    obj = []
    k = ls.index('O que você vai aprender') + 1 if 'O que você vai aprender' in ls else len(ls)
    while k < len(ls) and ls[k].endswith('.') and len(obj) < 8:
        obj.append(ls[k]); k += 1
    # objetivos que continuam depois do cabeçalho da página
    for l in ls[k:k + 6]:
        if l.endswith('.') and not l.startswith(('Usar apenas', 'Nunca')) and len(obj) < 8 and l not in obj and not ENC.match(l) and 'h de aula' not in l:
            if ls.index(l) < ls.index('Combinados de segurança') if 'Combinados de segurança' in ls else True:
                obj.append(l)
    seg = []
    if 'Combinados de segurança' in ls:
        j = ls.index('Combinados de segurança') + 1
        while j < len(ls) and ls[j] != 'Materiais e ferramentas': seg.append(ls[j]); j += 1
    mat = []
    if 'MATERIAIS' in ls:
        j = ls.index('MATERIAIS') + 1
        while j < len(ls) and ls[j] not in ('FERRAMENTAS', 'Projeto do módulo'): mat.append(ls[j]); j += 1
    proj_t = proj_d = ''
    entreg = []
    if 'PROJETO FINAL' in ls:
        j = ls.index('PROJETO FINAL'); proj_t, proj_d = ls[j + 1], ls[j + 2]
        if 'ENTREGÁVEIS' in ls:
            j = ls.index('ENTREGÁVEIS') + 1
            while j < len(ls) and ls[j] != 'Seus encontros': entreg.append(ls[j]); j += 1
    intro = [desc, '## O que você vai aprender'] + ['- ' + o for o in obj]
    if seg: intro += ['## Combinados de segurança'] + ['- ' + s for s in seg]
    if mat: intro += ['## Materiais'] + ['- ' + m for m in mat]
    if proj_t: intro += ['## Projeto do módulo', f'**{proj_t}:** {proj_d}', '**Entregáveis:**'] + ['- ' + e for e in entreg]
    aulas = [{'titulo': f'Boas-vindas ao Módulo {num}', 'tipo': 'TEXTO', 'conteudo': '\n\n'.join(intro), 'duracao_min': 10}]
    ini = ls.index('Seus encontros') + 1
    cabs = [i for i in range(ini, len(ls)) if ENC.match(ls[i])]
    fim_mod = ls.index('Glossário') if 'Glossário' in ls else len(ls)
    for k, c in enumerate(cabs):
        f = cabs[k + 1] if k + 1 < len(cabs) else fim_mod
        m = ENC.match(ls[c])
        n = int(m.group(1))
        aulas.append({'titulo': f'Encontro {n} · {m.group(2)}', 'tipo': 'TEXTO', 'conteudo': encontro(ls[c + 1:f]),
                      'notas': notas.get((num, n), ''), 'duracao_min': int(m.group(3))})
    # projeto: encontro "Projeto: ..." vira exercício
    proj = [a for a in aulas if 'Projeto' in a['titulo'] and 'planejamento' not in a['titulo'].lower()] or [aulas[-2]]
    alvo = proj[-1]
    alvo['tipo'] = 'EXERCICIO'
    alvo['exercicio'] = {
        'enunciado': f'Projeto do Módulo {num}: {proj_t}. {proj_d}\n\nEntregáveis: ' + '; '.join(entreg) +
                     '.\n\nEnvie o link do vídeo ou das fotos do projeto (YouTube, Google Drive, Tinkercad…) e conte no recado o que cada integrante fez.',
        'rubrica': ['Processo: passou pelo Ciclo Maker (ideação, protótipo, teste e iteração)', 'Conhecimento técnico: o projeto funciona e usa o conteúdo do módulo',
                    'Colaboração: a equipe dividiu papéis e trabalhou junto', 'Documentação: diário de bordo com versões, fotos e aprendizados',
                    'Comunicação: apresentação clara do projeto e das decisões'],
        'tipo_entrega': 'LINK_VIDEO',
    }
    # glossário
    if 'Glossário' in ls:
        g, j = [], fim_mod + 1
        while j + 1 < len(ls) and ls[j] not in ('Diário de bordo', 'Autoavaliação'):
            g.append(f'- **{ls[j]}:** {ls[j + 1]}'); j += 2
        if g:
            aulas.append({'titulo': f'Glossário do Módulo {num}', 'tipo': 'TEXTO', 'conteudo': '\n\n'.join(g), 'duracao_min': 5, 'obrigatoria': False})
    return {'titulo': titulo_mod, 'aulas': aulas}

if __name__ == '__main__':
    W = sys.argv[1]
    al = linhas(open(os.path.join(W, 'Apostila_Completa_Aluno_Curriculo_Maker.txt')).read())
    pr = linhas(open(os.path.join(W, 'Apostila_Completa_Professor_Curriculo_Maker.txt')).read())
    notas = notas_professor(pr)
    idx = [i for i, l in enumerate(al) if l == 'Sobre o módulo']
    titulos = ['STEAM e fundamentos de eletrônica', 'Programação: lógica e blocos visuais', 'Arduino básico: hardware programável',
               'Kits educacionais: RoboLat e micro:bit', 'Robótica: complexidade e autonomia', 'Impressão 3D: modelagem e materialização de ideias']
    mods = [modulo(al[idx[k]:idx[k + 1] if k + 1 < len(idx) else len(al)], k + 1, notas, titulos[k]) for k in range(len(idx))]
    # apresentação do curso, antes do primeiro módulo
    ap = al[:idx[0]]
    def trecho(ini, fim):
        if ini not in ap: return []
        a = ap.index(ini) + 1; b = ap.index(fim) if fim in ap else len(ap)
        return ap[a:b]
    intro = [*trecho('Apresentação do curso', 'A essência do movimento maker')]
    intro += ['## A essência do movimento maker'] + ['- ' + l for l in trecho('A essência do movimento maker', 'O Ciclo Maker')]
    intro += ['## O Ciclo Maker'] + ['- ' + l for l in trecho('O Ciclo Maker', 'O ritmo de cada encontro (1h30)')]
    intro += ['## O ritmo de cada encontro (1h30)'] + ['- ' + l for l in trecho('O ritmo de cada encontro (1h30)', 'Três trilhas na mesma turma')]
    intro += ['## Três trilhas na mesma turma'] + ['- ' + l for l in trecho('Três trilhas na mesma turma', 'Como você será avaliado')]
    intro += ['## Como você será avaliado'] + ['- ' + l for l in trecho('Como você será avaliado', 'Certificação do curso')]
    intro += ['## Certificação do curso'] + ['- ' + l for l in trecho('Certificação do curso', 'Sobre o módulo')]
    mods[0]['aulas'].insert(0, {'titulo': 'Como funciona o curso', 'tipo': 'TEXTO', 'conteudo': '\n\n'.join(intro), 'duracao_min': 10})
    curso = {
        'slug': 'formacao-maker',
        'titulo': 'Formação Maker',
        'descricao': 'Currículo Maker de 70 horas em 6 módulos: eletrônica, programação em blocos, Arduino, kits educacionais, robótica e modelagem e impressão 3D. Encontros de 1h30 com desafios em três trilhas por idade (6 a 17 anos).',
        'nivel': 'INICIANTE',
        'modulos': mods,
    }
    curso['carga_horaria'] = 70
    json.dump(curso, open(sys.argv[2], 'w'), ensure_ascii=False, indent=1)
    for m in mods:
        ex = [a['titulo'] for a in m['aulas'] if a['tipo'] == 'EXERCICIO']
        print(m['titulo'], len(m['aulas']), 'aulas; exercício:', ex, '; com notas:', sum(1 for a in m['aulas'] if a.get('notas')))

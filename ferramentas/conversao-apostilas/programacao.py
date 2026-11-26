"""Converte os Guias do Professor (PDF, versão remota) do curso de Programação em JSON da plataforma."""
import re, json, sys, os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from codigos import C as CODIGO

UP = 'A-ZÁÉÍÓÚÂÊÔÃÕÇÀ'
LO = 'a-záéíóúâêôãõçà'
CAB = re.compile(r'^AULA (\d+) · MÓDULO \d · (\d+) MIN · REMOTA (.+)$')
SECOES = ['Revisão e desafio de abertura', 'Conceito e demonstração', 'Prática guiada', 'E se não funcionar?', 'Desafio de casa', 'Vocabulário']

def limpa(s):
    for a, b in [('\\`', '`'), ('\\[', '['), ('\\]', ']'), ('\\!', '!'), ('\\>', '>'), ('\\<', '<'), ('\\_', '_'), ('\\*', '*'), ('\\-', '-'), ('\\#', '#'), ('\\.', '.')]:
        s = s.replace(a, b)
    return re.sub(r'[ \t]+', ' ', s).strip()

MARCOS = re.compile(r'^(AULA \d|RESULTADO ESPERADO|DICA |REGRA DE OURO|DESAFIO|Nível |Roteiro minutado|Tempo Momento|MATERIAIS|CÓDIGO DE REFERÊNCIA|Guia do professor|Sintoma Causa|Vocabulário|LEMBRA|RETOMADA|AO FINAL DA AULA|Caderno de bordo|Revisão e desafio|Conceito e demonstração|Prática guiada|E se não funcionar|Desafio de casa|Preparação |O que observar |Respostas esperadas |Adaptação )')

def linhas_limpas(txt):
    out = []
    codigo = False
    for l in txt.split('\n'):
        l = limpa(l)
        if not l or re.match(r'^Criar, Experimentar e Aprender página', l) or l.startswith('Robótica e Programação · '):
            continue
        if re.match(r'^(CÓDIGO|META)[A-Z ]* DE REFERÊNCIA$', l):
            codigo = True; out.append('CÓDIGO DE REFERÊNCIA'); continue
        if codigo:
            if 'RESULTADO ESPERADO' in l:
                antes, depois = l.split('RESULTADO ESPERADO', 1)
                if antes.strip(): out.append(antes.strip())
                out.append('RESULTADO ESPERADO' + depois)
                codigo = False
                continue
            else:
                out.append(l); continue
        anterior = out[-1] if out else ''
        pode = anterior and not MARCOS.match(l) and not MARCOS.match(anterior) and anterior != 'CÓDIGO DE REFERÊNCIA'
        # termo de vocabulário que começa com minúscula (random, import, gap...) não é continuação
        novo_termo = anterior.endswith('.') and re.match(rf'^[\w.()=!<>\-]+(?: [\w.()\-]+)? [{UP}][{LO}]', l)
        # quebra de linha do PDF: continua a linha anterior
        if pode and not novo_termo and (re.match(rf'^[{LO}(,;:.)]', l) or (len(anterior) > 70 and not re.search(r'[.!?:]$', anterior) and not re.match(r'^\d+\b', l))):
            out[-1] += ' ' + l
        else:
            out.append(l)
    return out

def rotulo(l):
    """'REGRA DE OURO Computador...' -> ('Regra de ouro', 'Computador...')"""
    m = re.match(rf'^([{UP}0-9 ·\-–(),?!]+)\s+([{UP}][{LO}].*|[{UP}] .*|“.*|".*)$', l)
    if not m: return None, l
    r = m.group(1).strip()
    if len(r) < 4 or not re.search(rf'[{UP}]{{3}}', r): return None, l
    return r.capitalize(), m.group(2)

def termo(l):
    """'Bandeira verde Botão que inicia...' -> ('Bandeira verde', 'Botão que inicia...')"""
    m = re.match(rf'^(\S+(?: [^\s{UP}]\S*)*?) ([{UP}].*)$', l)
    return (m.group(1), m.group(2)) if m else (None, l)

def converte(linhas, chave=None):
    out, notas = [], []
    sec = None
    modo = 'aluno'
    passo = None
    esperado = 1
    # passos que o PDF juntou na mesma linha: "... tentativas. 4 Dê pistas"
    linhas = [x for l in linhas for x in re.split(rf'(?<=[.!?:)\"]) (?=[1-6] [{UP}][{LO}]+ )', l)]
    for l in linhas:
        if l == 'Roteiro minutado':
            modo = 'roteiro'; notas.append('**Roteiro minutado**'); continue
        if l == 'Guia do professor':
            modo = 'guia'; continue
        if modo == 'roteiro':
            if l.startswith('Revisão e desafio de abertura'):
                modo = 'aluno'
            else:
                if l != 'Tempo Momento O que fazer': notas.append('- ' + l)
                continue
        if modo == 'guia':
            for k in ['Preparação', 'O que observar', 'Respostas esperadas', 'Adaptação']:
                if l.startswith(k + ' '):
                    notas.append(f'**{k}:**'); l = l[len(k) + 1:]
            # linha que continua a frase anterior (quebra de linha do PDF): junta no mesmo parágrafo
            if (notas and not l.startswith('•') and not notas[-1].startswith('**')
                    and not re.search(r'[.!?:)]$', notas[-1])):
                notas[-1] += ' ' + l
            else:
                notas.append('- ' + l.lstrip('• ') if l.startswith('•') else l)
            continue
        sec_nova = next((s for s in SECOES if l.startswith(s)), None)
        if sec_nova and len(l) < 60:
            sec = sec_nova
            esperado = 1
            out.append('## ' + l); passo = None; continue
        if l in ('MATERIAIS',):
            out.append('**Materiais**'); continue
        if l == 'Sintoma Causa provável e solução':
            continue
        if l == 'CÓDIGO DE REFERÊNCIA':
            out.append('```'); sec = 'codigo'; continue
        if sec == 'codigo':
            r, _ = rotulo(l)
            if r and r.startswith('Resultado esperado'):
                if chave in CODIGO: out[-1] = '```\n' + CODIGO[chave]
                out[-1] += '\n```'; sec = 'pratica'
            else:
                out[-1] += '\n' + l; continue
        if l.startswith('LEMBRA?') or l.startswith('RETOMADA DA AULA ANTERIOR'):
            out.append('**Lembra?**'); continue
        if l.startswith('AO FINAL DA AULA, EU CONSIGO'):
            itens = [x.strip() for x in l.replace('AO FINAL DA AULA, EU CONSIGO', '').split('☐') if x.strip()]
            out.append('**Ao final da aula, eu consigo:**'); out.extend('- ' + x for x in itens); sec = 'checklist'; continue
        if sec == 'checklist' and '☐' in l:
            out.extend('- ' + x.strip() for x in l.split('☐') if x.strip()); continue
        if l.startswith('•'):
            out.append('- ' + l.lstrip('• ')); continue
        if sec == 'Vocabulário':
            t, d = termo(l)
            if t: out.append(f'- **{t}:** {d}'); continue
        if sec == 'Prática guiada' and re.fullmatch(r'\d', l) and int(l) == esperado:
            passo = l; esperado += 1; continue
        m = re.match(r'^(\d) (.+)$', l)
        material = bool(re.search(r'\d+ por (aluno|dupla|grupo|turma|trio)\b', l))
        if m and sec == 'Prática guiada' and (material or (m.group(1) != passo and int(m.group(1)) != esperado)):
            # não é o próximo passo: é continuação de um item de material ("Lista de / 5 perguntas...")
            if out and out[-1].startswith('- '):
                out[-1] += ' ' + l
                partes = re.sub(r'(por (?:aluno|dupla|grupo|turma|trio)) ', '\\1\\n', out[-1][2:]).split('\n')
                out[-1:] = ['- ' + x for x in partes]
                continue
            m = None
        if sec == 'Prática guiada' and (m or passo):
            if m and (not passo or m.group(1) != passo):
                passo, l = m.group(1), m.group(2)
                esperado = int(passo) + 1
                out.append(f'{passo}. **{l}**'); continue
            out.append(f'{passo}. {l}' if not out[-1].startswith(f'{passo}. **') else '   ' + l)
            if out[-1].startswith('   '):
                out[-2] = out[-2] + ' ' + l; out.pop()
            passo = None; continue
        if sec == 'Prática guiada' and re.search(r' 1 por (aluno|dupla|grupo)|\d+ por (aluno|dupla)| a escolher$', l):
            out.extend('- ' + x for x in re.sub(r'(por (?:aluno|dupla|grupo|turma|trio)) ', '\\1\\n', l).split('\n')); continue
        r, corpo = rotulo(l)
        if r and not l.startswith('Nível'):
            out.append(f'[[{r}]] {corpo}'); continue
        if l.startswith('Caderno de bordo '):
            out.append('[[Caderno de bordo]] ' + l[len('Caderno de bordo '):]); continue
        if sec == 'E se não funcionar?':
            m2 = re.search(rf'(?<=.{{8}}) (?=[{UP}][{LO}])', l)
            out.append(f'- **{l[:m2.start()]}:** {l[m2.end():]}' if m2 else '- ' + l); continue
        out.append(l)
    txt = '\n\n'.join(out)
    return re.sub(r'\n{3,}', '\n\n', txt).strip(), '\n\n'.join(notas).strip()

def modulo(caminho, num, titulo_mod):
    linhas = linhas_limpas(open(caminho).read())
    cabs = [i for i, l in enumerate(linhas) if CAB.match(l)]
    intro = linhas[:cabs[0]]
    obj = []
    if 'Ao fim deste módulo você vai' in intro:
        k = intro.index('Ao fim deste módulo você vai') + 1
        while k < len(intro) and intro[k].startswith('•'):
            obj.append(intro[k].lstrip('• ')); k += 1
    sub = next((l for l in intro if '·' in l and 'aulas' in l), '')
    aulas = [{'titulo': f'Boas-vindas ao Módulo {num}', 'tipo': 'TEXTO', 'duracao_min': 5,
              'conteudo': '\n\n'.join(([sub] if sub else []) + (['## O que você vai aprender'] + ['- ' + o for o in obj] if obj else []))}]
    for k, ini in enumerate(cabs):
        fim = cabs[k + 1] if k + 1 < len(cabs) else len(linhas)
        m = CAB.match(linhas[ini])
        conteudo, notas = converte(linhas[ini + 1:fim], f'M{num}A{m.group(1)}')
        aulas.append({'titulo': f'Aula {m.group(1)} · {m.group(3)}', 'tipo': 'TEXTO', 'conteudo': conteudo, 'notas': notas, 'duracao_min': int(m.group(2))})
    proj = [a for a in aulas if re.search(r'Projeto', a['titulo']) and not re.search(r'Apresenta', a['titulo'])]
    doc = [a for a in aulas if 'Documentação' in a['titulo']]
    alvo = proj[-1] if proj else (doc[-1] if doc else aulas[-1])
    res = re.search(r'\[\[Resultado esperado\]\] (.+)', alvo['conteudo'])
    alvo['tipo'] = 'EXERCICIO'
    alvo['exercicio'] = {
        'enunciado': f'Projeto do Módulo {num}: {titulo_mod}.\n\n' + (res.group(1) if res else 'Entregue o projeto do módulo funcionando.') +
                     '\n\nEnvie o link do projeto (Scratch, Wokwi, Replit, Trinket, GitHub ou Google Drive) ou de um vídeo mostrando o funcionamento, e cole o código principal se houver.',
        'rubrica': ['Funcionamento: o projeto roda e cumpre o que foi proposto', 'Lógica do código: usa os conceitos do módulo de forma correta',
                    'Organização: código claro, com nomes e comentários', 'Apresentação: explica como funciona e um erro que corrigiu'],
        'tipo_entrega': 'LINK_VIDEO',
    }
    return {'titulo': titulo_mod, 'aulas': aulas}

if __name__ == '__main__':
    W = sys.argv[1]
    mods = [
        ('Professor_Modulo_1_Logica_e_Blocos_Scratch', 'Lógica e blocos com Scratch'),
        ('Professor_Modulo_2_Python_Basico_Thonny', 'Python básico'),
        ('Professor_Modulo_3_Python_para_Dados_matplotlib', 'Python para dados'),
        ('Professor_Modulo_4_Web_HTML_CSS_JavaScript', 'Web: HTML, CSS e JavaScript'),
        ('Professor_Modulo_5_Dispositivos_e_IoT_ESP32_Wokwi', 'Dispositivos e IoT com ESP32 e Wokwi'),
        ('Professor_Modulo_6_Software_IA_e_Projeto_de_Conclusao', 'Software, IA e projeto de conclusão'),
    ]
    curso = {
        'slug': 'curso-de-programacao',
        'titulo': 'Curso de Programação',
        'descricao': 'Do Scratch ao Python, à web e à Internet das Coisas com ESP32, terminando com inteligência artificial e um projeto de conclusão. 6 módulos com aulas remotas de 60 minutos.',
        'nivel': 'INICIANTE',
        'modulos': [modulo(os.path.join(W, a + '.txt'), i, t) for i, (a, t) in enumerate(mods, 1)],
    }
    curso['carga_horaria'] = round(sum(a['duracao_min'] for m in curso['modulos'] for a in m['aulas']) / 60)
    json.dump(curso, open(sys.argv[2], 'w'), ensure_ascii=False, indent=1)
    for m in curso['modulos']:
        ex = [a['titulo'] for a in m['aulas'] if a['tipo'] == 'EXERCICIO']
        print(m['titulo'], len(m['aulas']), 'aulas; exercício:', ex, '; sem notas:', sum(1 for a in m['aulas'][1:] if not a.get('notas')))

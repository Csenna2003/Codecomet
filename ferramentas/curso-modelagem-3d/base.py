"""Ajudantes para escrever o curso no formato dos pacotes da plataforma (cursos/app/conteudo/*.json).

Formatação do texto (a mesma do editor de aulas): parágrafos separados por linha em branco,
"## " subtítulo, "- " lista, "1. " lista numerada, "[[Rótulo]] texto" quadro de destaque,
**negrito** e `código`.
"""
import re

def texto(*blocos):
    """Junta blocos de texto. Uma lista Python vira itens de lista (um bloco por item)."""
    out = []
    for b in blocos:
        if isinstance(b, (list, tuple)):
            out += [x if re.match(r'^(- |\d+\. )', x) else '- ' + x for x in b]
        else:
            out.append(b.strip())
    return '\n\n'.join(out)

def numerada(*itens):
    return [f'{i}. {x}' for i, x in enumerate(itens, 1)]

def aula(titulo, minutos, conteudo, notas='', tipo='TEXTO', obrigatoria=True, **extra):
    a = {'titulo': titulo, 'tipo': tipo, 'conteudo': conteudo, 'notas': notas, 'duracao_min': minutos}
    if not obrigatoria: a['obrigatoria'] = False
    a.update(extra)
    return a

def quiz(titulo, minutos, intro, perguntas, notas=''):
    """perguntas: [(pergunta, [opções], índice_da_correta)]"""
    q = [{'pergunta': p, 'opcoes': o, 'correta': c} for p, o, c in perguntas]
    for x in q:
        assert 0 <= x['correta'] < len(x['opcoes']) and len(x['opcoes']) >= 3, x
    return aula(titulo, minutos, intro, notas, tipo='QUIZ', quiz=q)

def projeto(titulo, minutos, conteudo, enunciado, rubrica, notas=''):
    return aula(titulo, minutos, conteudo, notas, tipo='EXERCICIO',
                exercicio={'enunciado': enunciado, 'rubrica': rubrica, 'tipo_entrega': 'LINK_VIDEO'})

def notas(preparacao, observar, respostas=None, adaptacao=None):
    """Notas do professor no mesmo formato das apostilas convertidas."""
    partes = ['**Preparação:** ' + preparacao, '**O que observar:**'] + ['- ' + o for o in observar]
    if respostas: partes += ['**Respostas esperadas:**'] + ['- ' + r for r in respostas]
    if adaptacao: partes += ['**Adaptação:**'] + ['- ' + a for a in adaptacao]
    return '\n\n'.join(partes)

def modulo(titulo, aulas):
    return {'titulo': titulo, 'aulas': aulas}

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


def aula_padrao(numero, titulo, minutos, cabecalho, desafio, conceito, destaque, vocabulario, materiais, passos,
                resultado, problemas, dica, caderno, extra, notas='', simulador=None, prefixo='Aula',
                min_conceito=10, min_pratica=30):
    """Aula no mesmo roteiro dos cursos de Robótica e Programação:
    cabeçalho · Desafio · Conceito · Vocabulário · Mão na massa · Resultado esperado ·
    E se não funcionar? · Caderno de bordo · Desafio extra.

    destaque: ('Ideia-chave' | 'Regra' | 'Atenção', texto)
    passos: [(título, texto)]; problemas: [(sintoma, o que fazer)]
    simulador: 'simuladores/nome.html' (aparece no fim da aula, com desafios que se corrigem sozinhos)
    """
    blocos = [cabecalho, f'[[Desafio · 5 min]] {desafio}', f'## Conceito · {min_conceito} min'] + list(conceito)
    blocos.append(f'[[{destaque[0]}]] {destaque[1]}')
    blocos += ['## Vocabulário'] + [f'- **{t}:** {d}' for t, d in vocabulario]
    blocos += [f'## Mão na massa · {min_pratica} min', '**Materiais**'] + ['- ' + m for m in materiais]
    blocos += [f'{i}. **{t}:** {d}' for i, (t, d) in enumerate(passos, 1)]
    blocos.append(f'[[Resultado esperado]] {resultado}')
    blocos += ['## E se não funcionar?'] + [f'- **{s}:** {o}' for s, o in problemas]
    if dica: blocos.append(f'[[Dica]] {dica}')
    blocos += ['## Caderno de bordo · 5 min', caderno, f'[[Desafio extra]] {extra}']
    a = aula(f'{prefixo} {numero} · {titulo}', minutos, '\n\n'.join(blocos), notas,
             tipo='SIMULADOR' if simulador else 'TEXTO')
    if simulador: a['simulador_url'] = simulador
    return a


def boas_vindas(numero, aprender, projeto_titulo, projeto_texto, materiais=None, seguranca=None, extra=None):
    """Primeira aula de cada módulo, como nos outros cursos."""
    blocos = list(extra or [])
    blocos += ['## O que você vai aprender'] + ['- ' + a for a in aprender]
    if materiais: blocos += ['## Materiais'] + ['- ' + m for m in materiais]
    if seguranca: blocos += ['## Combinados de segurança'] + ['- ' + s for s in seguranca]
    blocos += ['## Projeto do módulo', f'**{projeto_titulo}:** {projeto_texto}']
    return aula(f'Boas-vindas ao Módulo {numero}', 10, '\n\n'.join(blocos))

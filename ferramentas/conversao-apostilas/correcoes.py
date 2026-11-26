"""Gera a lista de correções de texto entre duas versões dos pacotes de cursos.

A plataforma aplica cada correção só onde o texto ainda é exatamente o "antes"
(Administração → Cursos prontos → Atualizar textos). Textos que o administrador
já editou ficam como estão.

    python3 correcoes.py PASTA_ANTIGA PASTA_NOVA ../../cursos/app/conteudo/correcoes/NOME.json

As pastas têm os mesmos nomes de arquivo de cursos/app/conteudo/.
"""
import json, os, sys

def campos(curso):
    """((campo, módulo, aula), texto), pela posição: títulos de aula se repetem entre módulos."""
    for mi, m in enumerate(curso['modulos']):
        for ai, a in enumerate(m['aulas']):
            yield ('conteudo', mi, ai), a.get('conteudo') or ''
            yield ('notas', mi, ai), a.get('notas') or ''
            if a.get('exercicio'):
                yield ('enunciado', mi, ai), a['exercicio']['enunciado']

def titulo(curso, chave):
    return curso['modulos'][chave[1]]['aulas'][chave[2]]['titulo']

def main(antiga, nova, saida):
    lista = []
    for nome in sorted(os.listdir(nova)):
        if not nome.endswith('.json'): continue
        velho = json.load(open(os.path.join(antiga, nome)))
        novo = json.load(open(os.path.join(nova, nome)))
        if velho['slug'] != novo['slug']: raise SystemExit(f'{nome}: o slug mudou')
        antes = dict(campos(velho))
        for chave, depois in campos(novo):
            if chave not in antes or titulo(velho, chave) != titulo(novo, chave):
                raise SystemExit(f'{nome}: módulos ou aulas mudaram de lugar ({chave}); use um pacote novo')
            if antes[chave] != depois:
                if not antes[chave]: raise SystemExit(f'{nome}: campo vazio virou texto ({chave}); não dá para localizar com segurança')
                lista.append({'slug': novo['slug'], 'campo': chave[0], 'aula': titulo(novo, chave), 'antes': antes[chave], 'depois': depois})
    os.makedirs(os.path.dirname(saida), exist_ok=True)
    json.dump({'descricao': 'Correções de texto das apostilas convertidas', 'correcoes': lista},
              open(saida, 'w'), ensure_ascii=False, indent=1)
    print(len(lista), 'correções ->', saida)

if __name__ == '__main__':
    main(*sys.argv[1:4])

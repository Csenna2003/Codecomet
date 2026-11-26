"""Gera o pacote do curso de Modelagem 3D para a plataforma.

    python3 gerar.py ../../cursos/app/conteudo/4-modelagem-3d.json

O curso foi escrito para a Adoro Robótica, seguindo a sequência do material da escola
(Tinkercad e depois Fusion 360) e o formato dos outros cursos prontos.
"""
import json, re, sys
from m1 import M1
from m2 import M2
from m3 import M3
from m4 import M4
from m5 import M5
from m6 import M6

MODULOS = [M1, M2, M3, M4, M5, M6]

CURSO = {
    'slug': 'modelagem-3d',
    'titulo': 'Modelagem 3D: do Tinkercad ao Fusion 360',
    'descricao': 'Curso completo de modelagem e impressão 3D: do primeiro cubo no Tinkercad ao CAD paramétrico no Fusion 360. '
                 'Medidas, folgas e encaixes, fatiamento e solução de problemas de impressão, projetos que resolvem problemas reais '
                 'e um projeto final com portfólio. A partir de 11 anos, também para adultos.',
    'nivel': 'INICIANTE',
    # Suba a versão quando mudar a organização das aulas: o admin vê "Atualizar para a versão nova"
    # nos cursos já importados que ainda não têm alunos. (v2: roteiro padrão das aulas e simuladores.)
    'versao': 2,
    # carga horária = soma da duração das aulas, arredondada
    'carga_horaria': round(sum(a['duracao_min'] for m in MODULOS for a in m['aulas']) / 60),
    'modulos': MODULOS,
}

def conferir(curso):
    """Pega erros de autoria antes de gerar: campos vazios, quiz inválido, projeto sem rubrica."""
    erros = []
    for mi, m in enumerate(curso['modulos'], 1):
        projetos = [a for a in m['aulas'] if a.get('exercicio')]
        if len(projetos) != 1: erros.append(f'Módulo {mi}: precisa de exatamente um projeto')
        for a in m['aulas']:
            onde = f"M{mi} {a['titulo']}"
            if len(a['conteudo']) < (60 if a['tipo'] == 'QUIZ' else 200): erros.append(f'{onde}: texto curto demais')
            for t in (a['conteudo'], a.get('notas', ''), (a.get('exercicio') or {}).get('enunciado', '')):
                # rótulo sem nada depois (seguido de outro rótulo, de título ou do fim), "Rótulo: ." ou título vazio
                if (re.search(r'\*\*[^*\n]+:\*\*[ \t]*(?:\n\s*)*(?=\*\*[^*\n]+:\*\*|##|\Z)', t)
                        or re.search(r':\s*\.(\s|$)|^##\s*$|^\[\[[^\]]+\]\]\s*$', t, re.M)):
                    erros.append(f'{onde}: campo vazio')
            if a['tipo'] == 'QUIZ' and len(a.get('quiz', [])) < 5: erros.append(f'{onde}: quiz com menos de 5 perguntas')
            if a.get('exercicio') and len(a['exercicio']['rubrica']) < 4: erros.append(f'{onde}: rubrica curta')
    if erros: raise SystemExit('\n'.join(erros))

if __name__ == '__main__':
    conferir(CURSO)
    json.dump(CURSO, open(sys.argv[1], 'w'), ensure_ascii=False, indent=1)
    aulas = [a for m in MODULOS for a in m['aulas']]
    print(f"{CURSO['titulo']}: {len(MODULOS)} módulos, {len(aulas)} aulas, "
          f"{sum(a['duracao_min'] for a in aulas) / 60:.1f} h de aula, "
          f"{sum(1 for a in aulas if a['tipo'] == 'QUIZ')} quizzes, "
          f"{sum(1 for a in aulas if a.get('exercicio'))} projetos, "
          f"{sum(1 for a in aulas if a.get('notas'))} aulas com notas do professor")

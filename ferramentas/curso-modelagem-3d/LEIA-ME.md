# Curso de Modelagem 3D

Gera `cursos/app/conteudo/4-modelagem-3d.json`, que aparece em **Administração → Cursos prontos**.

Diferente dos outros cursos, este não foi convertido de apostila: foi escrito para a plataforma,
seguindo a sequência do material da escola (Tinkercad e depois Fusion 360, como no planejamento
"Modelagem 3D") e o formato dos outros cursos (aulas, quiz, projeto com rubrica e notas do professor).

| Módulo | Aulas | Projeto |
|---|---|---|
| 1. Primeiros passos no Tinkercad | 8 | Chaveiro personalizado |
| 2. Medidas e precisão | 8 | Organizador sob medida |
| 3. Da tela à impressora | 9 | Teste de folgas da impressora |
| 4. Projetar para resolver problemas | 8 | Peça que resolve |
| 5. CAD paramétrico com Fusion 360 (ou Onshape) | 9 | Caixa paramétrica com tampa |
| 6. Projeto final: do problema ao portfólio | 9 | Projeto final com portfólio |

Cada aula segue o mesmo roteiro dos cursos de Robótica e Programação (função `aula_padrao` em
`base.py`): cabeçalho (nível · organização · ferramenta), Desafio de abertura, Conceito com Ideia-chave
ou Regra, Vocabulário, Mão na massa (materiais e passos), Resultado esperado, E se não funcionar?,
Caderno de bordo e Desafio extra. Oito aulas têm simulador próprio da plataforma
(`cursos/simuladores/*.html`), com desafios que se corrigem sozinhos.

O texto de cada módulo está em `m1.py` … `m6.py`, com os ajudantes de `base.py`.
`gerar.py` confere o conteúdo (campos vazios, quizzes, rubricas) antes de gravar o pacote:

```bash
python3 gerar.py ../../cursos/app/conteudo/4-modelagem-3d.json
```

O campo `versao` do pacote diz qual é a versão do curso. Quando mudar a organização das aulas,
suba a versão: nos sites onde o curso já foi importado e ainda não tem alunos, o admin vê o botão
**Atualizar para a versão nova**.

Depois de importado, o curso pode ser editado aula por aula pela própria plataforma
(Administração → curso → Editar conteúdo). Se mudar o texto aqui depois que o curso já foi
importado no site, gere as correções com `../conversao-apostilas/correcoes.py`.

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

O texto de cada módulo está em `m1.py` … `m6.py`, com os ajudantes de `base.py`.
`gerar.py` confere o conteúdo (campos vazios, quizzes, rubricas) antes de gravar o pacote:

```bash
python3 gerar.py ../../cursos/app/conteudo/4-modelagem-3d.json
```

Depois de importado, o curso pode ser editado aula por aula pela própria plataforma
(Administração → curso → Editar conteúdo). Se mudar o texto aqui depois que o curso já foi
importado no site, gere as correções com `../conversao-apostilas/correcoes.py`.

# Conversão das apostilas em cursos da plataforma

Gera os arquivos `cursos/app/conteudo/*.json`, que aparecem em **Administração → Cursos prontos**.

Fonte: as apostilas da pasta **Adoro Robotica** do Google Drive, exportadas como texto
(uma por arquivo, com o mesmo nome do original e extensão `.txt`):

| Curso | Arquivos usados |
|---|---|
| Robótica e Programação | `Professor_Modulo_N_*.docx` da pasta *Curso de robótica 2 Anos Remoto* |
| Curso de Programação | `Professor_Modulo_N_*.pdf` da pasta *Curso de programação completo* |
| Formação Maker | `Apostila_Completa_Aluno_Curriculo_Maker.pdf` e `Apostila_Completa_Professor_Curriculo_Maker.pdf` |

Usamos a versão do professor porque ela contém todo o conteúdo do aluno; o "Guia do professor"
de cada aula vira as **Notas do professor**, visíveis só para a equipe.

O texto exportado perde as quebras de linha dos quadros de código. Por isso o código de referência
foi reconstruído à mão em `codigos.py` (Programação) e `codigos_rob.py` (Robótica). Se uma apostila
mudar o código de uma aula, atualize o bloco correspondente nesses arquivos.

```bash
python3 robotica.py    PASTA_DOS_TXT ../../cursos/app/conteudo/1-robotica-e-programacao.json
python3 programacao.py PASTA_DOS_TXT ../../cursos/app/conteudo/2-curso-de-programacao.json
python3 maker.py       PASTA_DOS_TXT ../../cursos/app/conteudo/3-formacao-maker.json
```

## Corrigir cursos que já foram importados

A plataforma não importa de novo um curso que já existe (o progresso dos alunos ficaria órfão).
Quando um conversor muda, gere também a lista de correções de texto, comparando os pacotes antigos
com os novos (guarde uma cópia dos antigos antes de regenerar):

```bash
python3 correcoes.py PASTA_ANTIGA PASTA_NOVA ../../cursos/app/conteudo/correcoes/AAAA-MM-assunto.json
```

Em **Administração → Cursos prontos** aparece **Atualizar textos** nos cursos com correções. Cada texto
(aula, nota do professor ou enunciado do projeto) só é trocado se ainda for exatamente o original
importado; o que o administrador editou fica como está. O script recusa a geração se módulos ou aulas
mudaram de lugar ou se surgiram aulas novas: nesse caso o curso precisa de um pacote novo.

Os conversores param com erro quando a apostila sai do formato esperado (desafio por trilha que não dá
para separar, projeto do módulo sem título ou entregáveis), em vez de gerar campos vazios.

### Correções de novembro de 2026 (`2026-11-textos-apostilas.json`, 100 textos)

- **Formação Maker:** em 26 encontros a Trilha C ficava vazia (as trilhas A e B vinham na mesma linha
  do PDF e o texto escorregava uma posição); nos módulos 2 e 6 o projeto final ficava sem título,
  descrição e entregáveis ("Projeto do Módulo 2: ." / "Entregáveis: ."), porque o título vinha na
  mesma linha de "PROJETO FINAL".
- **Robótica e Programação:** rótulo "Sinais de alerta" vazio e repetido nas notas dos projetos dos
  módulos 1 e 2.
- **Curso de Programação:** 79 frases das notas do professor partidas ao meio pela quebra de linha do PDF.

